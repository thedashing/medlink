<?php
// Fichier : MEDECINE/book_appointment.php



// Assurez-vous que le fuseau horaire est défini de manière cohérente ici et dans les fichiers AJAX
date_default_timezone_set('Africa/Ouagadougou');
require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';

// Rediriger si non connecté ou non patient (pour la prise de RDV)
if (!is_logged_in()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}
if (get_user_role() !== 'patient') {
    header('Location: index.php?error=unauthorized_access');
    exit();
}

$message = '';
$message_type = '';

$doctor_id = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
$clinic_id = filter_input(INPUT_GET, 'clinic_id', FILTER_VALIDATE_INT); // Peut être nul initialement

$doctor = null;
$clinics_for_doctor = [];
$services_for_clinic_doctor = []; // Sera rempli dynamiquement par JS après le premier chargement initial

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Récupérer les informations de base du médecin
    if ($doctor_id) {
        $stmt = $pdo->prepare("SELECT d.id, d.first_name, d.last_name, d.bio, d.language, d.profile_picture_url
                               FROM doctors d
                               WHERE d.id = :doctor_id");
        $stmt->execute([':doctor_id' => $doctor_id]);
        $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$doctor) {
            $message = "Médecin non trouvé.";
            $message_type = 'error';
        } else {
            // Récupérer les cliniques où le médecin exerce
            $stmt_clinics = $pdo->prepare("SELECT cl.id, cl.name, cl.address, cl.city
                                            FROM clinics cl
                                            JOIN clinic_doctors cd ON cl.id = cd.clinic_id
                                            WHERE cd.doctor_id = :doctor_id
                                            ORDER BY cl.name");
            $stmt_clinics->execute([':doctor_id' => $doctor_id]);
            $clinics_for_doctor = $stmt_clinics->fetchAll(PDO::FETCH_ASSOC);

            // Si aucune clinique n'est spécifiée dans l'URL, prendre la première par défaut
            if (!$clinic_id && !empty($clinics_for_doctor)) {
                $clinic_id = $clinics_for_doctor[0]['id']; // Définit la clinique par défaut
            }
            // Si clinic_id est maintenant défini, récupérer les spécialités pour cette clinique
            if ($clinic_id) {
                $stmt_clinic_specialties = $pdo->prepare("SELECT GROUP_CONCAT(s.name SEPARATOR ', ') AS specialties_names
                                                        FROM doctor_clinic_specialties dcs
                                                        JOIN specialties s ON dcs.specialty_id = s.id
                                                        WHERE dcs.doctor_id = :doctor_id AND dcs.clinic_id = :clinic_id
                                                        GROUP BY dcs.doctor_id");
                $stmt_clinic_specialties->execute([
                    ':doctor_id' => $doctor_id,
                    ':clinic_id' => $clinic_id
                ]);
                $clinic_specialties_row = $stmt_clinic_specialties->fetch(PDO::FETCH_ASSOC);
                $doctor['specialties_names'] = $clinic_specialties_row ? $clinic_specialties_row['specialties_names'] : 'Non spécifiées pour cette clinique';

                // Initialiser les services pour la clinique/médecin sélectionné au chargement initial
                // Le JS se chargera de recharger si la clinique change via AJAX
                $stmt_services = $pdo->prepare("SELECT id, name, price, description, duration_minutes
                                                FROM services
                                                WHERE clinic_id = :clinic_id AND doctor_id = :doctor_id
                                                ORDER BY name");
                $stmt_services->execute([':clinic_id' => $clinic_id, ':doctor_id' => $doctor_id]);
                $services_for_clinic_doctor = $stmt_services->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } else {
        $message = "ID de médecin manquant.";
        $message_type = 'error';
    }

} catch (PDOException $e) {
    $message = "Erreur de base de données : " . $e->getMessage();
    $message_type = 'error';
    error_log("DB Error on book_appointment.php: " . $e->getMessage());
}

// --- Traitement de la prise de rendez-vous (POST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_appointment'])) {
    $selected_doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $selected_clinic_id = filter_input(INPUT_POST, 'clinic_id', FILTER_VALIDATE_INT);
    $selected_service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
    $selected_datetime_str = htmlspecialchars(trim($_POST['selected_datetime']));

    $patient_user_id = get_user_id();

    if (!$selected_doctor_id || !$selected_clinic_id || !$selected_service_id || empty($selected_datetime_str) || !$patient_user_id) {
        $message = "Informations de rendez-vous incomplètes.";
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            $stmt_patient_info = $pdo->prepare("SELECT id FROM patients WHERE user_id = :user_id");
            $stmt_patient_info->execute([':user_id' => $patient_user_id]);
            $patient_db_id = $stmt_patient_info->fetchColumn();

            if (!$patient_db_id) {
                throw new Exception("ID de patient introuvable pour l'utilisateur connecté.");
            }

            $stmt_service = $pdo->prepare("SELECT duration_minutes FROM services WHERE id = :service_id AND doctor_id = :doctor_id AND clinic_id = :clinic_id");
            $stmt_service->execute([
                ':service_id' => $selected_service_id,
                ':doctor_id' => $selected_doctor_id,
                ':clinic_id' => $selected_clinic_id
            ]);
            $service_info = $stmt_service->fetch(PDO::FETCH_ASSOC);

            if (!$service_info) {
                $message = "Service sélectionné invalide ou non disponible pour ce médecin/clinique.";
                $message_type = 'error';
            } else {
                $duration_minutes = $service_info['duration_minutes'];
                $appointment_start_timestamp = strtotime($selected_datetime_str);
                $appointment_end_timestamp = $appointment_start_timestamp + ($duration_minutes * 60);
                $appointment_end_datetime_str = date('Y-m-d H:i:s', $appointment_end_timestamp);
                
                $pdo->beginTransaction();

                if ($appointment_start_timestamp < time()) {
                    throw new Exception("Vous ne pouvez pas prendre de rendez-vous dans le passé.");
                }

                // Pour la revérification finale au moment de la soumission POST:
                // Nous allons réutiliser la logique de `get_available_slots.php`
                // soit en l'incluant, soit en dupliquant la logique ici.
                // Pour éviter de dupliquer beaucoup de code, le plus simple est d'inclure le fichier
                // ou de refactoriser la fonction dans un fichier d'utilitaire commun.
                // Pour l'exemple, nous allons inclure une version simplifiée ou supposer qu'elle est accessible.
                
                // OPTION 1: Inclure le fichier get_available_slots.php (moins recommandé pour la robustesse)
                // require_once 'get_available_slots.php'; // Cela pourrait causer des conflits de session ou d'en-tête JSON
                
                // OPTION 2 (Préférable): Refactoriser la logique de get_available_slots_ajax dans une fonction utilisable ici
                // Ou, pour cet exemple, comme la logique est courte, la répéter avec les objets DateTime pour plus de sécurité
                
                // Revérification des créneaux au moment de la soumission pour éviter les doubles réservations
                $day_of_week_map = [
                    'Sunday' => 'Dimanche', 'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
                    'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi'
                ];
                $english_day_of_week = date('l', $appointment_start_timestamp);
                $french_day_of_week = $day_of_week_map[$english_day_of_week];
                
                $stmt_schedule_check = $pdo->prepare("SELECT start_time, end_time FROM doctor_schedules
                                                        WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id AND day_of_week = :day_of_week AND is_available = TRUE");
                $stmt_schedule_check->execute([
                    ':doctor_id' => $selected_doctor_id,
                    ':clinic_id' => $selected_clinic_id,
                    ':day_of_week' => $english_day_of_week
                ]);
                $schedules_for_day = $stmt_schedule_check->fetchAll(PDO::FETCH_ASSOC);
                echo "<script>console.log(" . json_encode($english_day_of_week) . ");</script>";
                echo "<script>console.log(" . json_encode($selected_clinic_id) . ");</script>";
                echo "<script>console.log(" . json_encode($french_day_of_week) . ");</script>";


                if (empty($schedules_for_day)) {
                    throw new Exception("Le médecin n'a pas de disponibilité pour ce jour et cette clinique.");
                }

                $stmt_booked_check = $pdo->prepare("SELECT appointment_datetime, end_datetime FROM appointments
                                                    WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id
                                                    AND DATE(appointment_datetime) = :date AND status IN ('pending', 'confirmed')");
                $stmt_booked_check->execute([
                    ':doctor_id' => $selected_doctor_id,
                    ':clinic_id' => $selected_clinic_id,
                    ':date' => date('Y-m-d', $appointment_start_timestamp)
                ]);
                $existing_appointments = $stmt_booked_check->fetchAll(PDO::FETCH_ASSOC);

                $slot_is_truly_available = false;
                $requested_slot_start = new DateTime($selected_datetime_str);
                $requested_slot_end = new DateTime($appointment_end_datetime_str);

                foreach ($schedules_for_day as $schedule) {
                    $schedule_start = new DateTime(date('Y-m-d', $appointment_start_timestamp) . ' ' . $schedule['start_time']);
                    $schedule_end = new DateTime(date('Y-m-d', $appointment_start_timestamp) . ' ' . $schedule['end_time']);

                    // Vérifier si le créneau demandé est bien dans une plage horaire de travail du médecin
                    if ($requested_slot_start >= $schedule_start && $requested_slot_end <= $schedule_end) {
                        $is_overlapping_with_booked = false;
                        foreach ($existing_appointments as $booked_app) {
                            $booked_start = new DateTime($booked_app['appointment_datetime']);
                            $booked_end = new DateTime($booked_app['end_datetime']);

                            if (($requested_slot_start < $booked_end) && ($requested_slot_end > $booked_start)) {
                                $is_overlapping_with_booked = true;
                                break;
                            }
                        }
                        if (!$is_overlapping_with_booked) {
                            $slot_is_truly_available = true;
                            break; // Le créneau est valide et non réservé
                        }
                    }
                }

                if (!$slot_is_truly_available) {
                    throw new Exception("Ce créneau n'est plus disponible ou est en conflit. Veuillez rafraîchir et choisir un autre.");
                }

                $stmt_insert = $pdo->prepare("INSERT INTO appointments 
                    (patient_id, doctor_id, clinic_id, service_id, appointment_datetime, end_datetime, status, payment_status, booking_channel)
                    VALUES (:patient_id, :doctor_id, :clinic_id, :service_id, :appointment_datetime, :end_datetime, 'pending', 'unpaid', 'app')");
                $stmt_insert->execute([
                    ':patient_id' => $patient_db_id,
                    ':doctor_id' => $selected_doctor_id,
                    ':clinic_id' => $selected_clinic_id,
                    ':service_id' => $selected_service_id,
                    ':appointment_datetime' => $selected_datetime_str,
                    ':end_datetime' => $appointment_end_datetime_str
                ]);
                
                $pdo->commit();
                $message = "Rendez-vous pris avec succès ! Un email de confirmation sera envoyé.";
                $message_type = 'success';
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Erreur lors de la prise de rendez-vous : " . $e->getMessage();
            $message_type = 'error';
            error_log("Appointment booking error: " . $e->getMessage());
        }
    }
}

?>