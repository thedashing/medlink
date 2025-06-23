<?php
// Fichier : MEDECINE/app/medecin/doctor_appointments.php



date_default_timezone_set('Africa/Ouagadougou');
require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once 'Messaging.php'; // Inclure la classe Messaging

// Seuls les médecins peuvent accéder à cette page
require_login('doctor');

$user_id = get_user_id(); // L'ID de l'utilisateur connecté (qui est un médecin)
$doctor_id = null; // L'ID du docteur correspondant à cet utilisateur
$appointments = [];
$message = '';
$message_type = '';

// Instancier la classe Messaging
$messaging = new Messaging();

// Récupérer l'ID du docteur lié à cet utilisateur
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $stmt_doctor_id = $pdo->prepare("SELECT id FROM doctors WHERE user_id = :user_id");
    $stmt_doctor_id->execute([':user_id' => $user_id]);
    $doctor_info = $stmt_doctor_id->fetch(PDO::FETCH_ASSOC);

    if ($doctor_info) {
        $doctor_id = $doctor_info['id'];
    } else {
        throw new Exception("Aucun profil de docteur trouvé pour votre compte.");
    }

    // Traitement des actions (confirmer, annuler, terminer, payer) - Déplacé avant la récupération des RDV pour un rechargement propre
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? '';
        $appointment_id_to_act = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);

        if ($appointment_id_to_act) {
            $pdo->beginTransaction();
            try {
                // Initialise $stmt_update pour éviter l'erreur si non défini pour certains cas
                $stmt_update = null;
                $stmt_insert_record = null; // Pour le cas 'complete_and_record'

                switch ($action) {
                    case 'confirm':
                        $new_status = 'confirmed';
                        $success_msg = 'Rendez-vous confirmé avec succès.';
                        $stmt_update = $pdo->prepare("UPDATE appointments SET status = :new_status WHERE id = :appointment_id AND doctor_id = :doctor_id");
                        $stmt_update->execute([
                            ':new_status' => $new_status,
                            ':appointment_id' => $appointment_id_to_act,
                            ':doctor_id' => $doctor_id
                        ]);
                        // Envoyer la notification de confirmation au patient
                        $messaging->sendAppointmentNotification($appointment_id_to_act, 'confirmed', $user_id);
                        break;
                    case 'cancel':
                        $new_status = 'cancelled';
                        $success_msg = 'Rendez-vous annulé avec succès.';
                        $stmt_update = $pdo->prepare("UPDATE appointments SET status = :new_status WHERE id = :appointment_id AND doctor_id = :doctor_id");
                        $stmt_update->execute([
                            ':new_status' => $new_status,
                            ':appointment_id' => $appointment_id_to_act,
                            ':doctor_id' => $doctor_id
                        ]);
                        // Envoyer la notification d'annulation au patient
                        $messaging->sendAppointmentNotification($appointment_id_to_act, 'cancelled', $user_id);
                        break;
                    case 'complete_and_record':
                        $new_status = 'completed';
                        $success_msg = 'Rendez-vous marqué comme terminé et dossier médical enregistré.';

                        // Récupérer les infos du patient pour le dossier médical
                        $stmt_get_patient_id = $pdo->prepare("SELECT patient_id FROM appointments WHERE id = :appointment_id");
                        $stmt_get_patient_id->execute([':appointment_id' => $appointment_id_to_act]);
                        $patient_info = $stmt_get_patient_id->fetch(PDO::FETCH_ASSOC);
                        $patient_id_for_record = $patient_info['patient_id'] ?? null;

                        if (!$patient_id_for_record) {
                            throw new Exception("Impossible de trouver le patient pour ce rendez-vous.");
                        }

                        // Récupérer les données du formulaire de la modale
                        $diagnosis = htmlspecialchars(trim($_POST['diagnosis'] ?? ''));
                        $treatment = htmlspecialchars(trim($_POST['treatment'] ?? ''));
                        $notes = htmlspecialchars(trim($_POST['notes'] ?? ''));

                        // Insérer le dossier médical
                        $stmt_insert_record = $pdo->prepare("INSERT INTO medical_records (appointment_id, patient_id, doctor_id, diagnosis, treatment, notes) VALUES (:appointment_id, :patient_id, :doctor_id, :diagnosis, :treatment, :notes)");
                        $stmt_insert_record->execute([
                            ':appointment_id' => $appointment_id_to_act,
                            ':patient_id' => $patient_id_for_record,
                            ':doctor_id' => $doctor_id,
                            ':diagnosis' => $diagnosis,
                            ':treatment' => $treatment,
                            ':notes' => $notes
                        ]);

                        // Mettre à jour le statut du rendez-vous
                        $stmt_update = $pdo->prepare("UPDATE appointments SET status = :new_status WHERE id = :appointment_id AND doctor_id = :doctor_id");
                        $stmt_update->execute([
                            ':new_status' => $new_status,
                            ':appointment_id' => $appointment_id_to_act,
                            ':doctor_id' => $doctor_id
                        ]);
                        // Envoyer la notification de fin de rendez-vous au patient
                        $messaging->sendAppointmentNotification($appointment_id_to_act, 'completed', $user_id);
                        break;
                    case 'mark_as_paid':
                        $success_msg = 'Paiement enregistré avec succès.';
                        $stmt_update = $pdo->prepare("UPDATE appointments SET payment_status = 'paid' WHERE id = :appointment_id AND doctor_id = :doctor_id");
                        $stmt_update->execute([
                            ':appointment_id' => $appointment_id_to_act,
                            ':doctor_id' => $doctor_id
                        ]);
                        // Vous pouvez ajouter une notification de paiement si nécessaire, par exemple :
                        // $messaging->sendAppointmentNotification($appointment_id_to_act, 'payment_received', $user_id);
                        break;
                    default:
                        $message = "Action invalide.";
                        $message_type = 'error';
                        break;
                }

                // Vérifiez si $stmt_update a été exécuté et a affecté des lignes
                // Ou si c'est l'action 'complete_and_record' et l'insertion a eu lieu
                if (($stmt_update && $stmt_update->rowCount() > 0) || ($action === 'complete_and_record' && $stmt_insert_record && $stmt_insert_record->rowCount() > 0)) {
                    $pdo->commit();
                    $message = $success_msg;
                    $message_type = 'success';
                    // Redirection pour éviter la soumission multiple du formulaire
                    header('Location: doctor_appointments.php?message=' . urlencode($message) . '&type=' . $message_type);
                    exit();
                } else {
                    $pdo->rollBack();
                    $message = "Impossible de mettre à jour le rendez-vous. Peut-être qu'il est déjà dans cet état ou n'existe pas.";
                    $message_type = 'error';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Erreur lors de l'action: " . $e->getMessage();
                $message_type = 'error';
                error_log("Transaction error in doctor_appointments.php: " . $e->getMessage());
            }
        }
    }

    // Récupérer les rendez-vous du médecin
    $stmt_appointments = $pdo->prepare("SELECT
                                            a.id AS appointment_id,
                                            a.appointment_datetime,
                                            a.end_datetime,
                                            a.status,
                                            a.payment_status,
                                            p.id AS patient_db_id,
                                            p.first_name AS patient_first_name,
                                            p.last_name AS patient_last_name,
                                            u.email AS patient_email,
                                            cl.name AS clinic_name,
                                            cl.address AS clinic_address,
                                            s.name AS service_name,
                                            s.price AS service_price
                                        FROM
                                            appointments a
                                        JOIN
                                            patients p ON a.patient_id = p.id
                                        JOIN
                                            users u ON p.user_id = u.id
                                        JOIN
                                            clinics cl ON a.clinic_id = cl.id
                                        JOIN
                                            services s ON a.service_id = s.id
                                        WHERE
                                            a.doctor_id = :doctor_id
                                        ORDER BY
                                            a.appointment_datetime ASC");
    $stmt_appointments->execute([':doctor_id' => $doctor_id]);
    $appointments = $stmt_appointments->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    error_log("Error retrieving doctor appointments: " . $e->getMessage());
}

// Récupérer les messages des paramètres GET après une redirection
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars(urldecode($_GET['message']));
    $message_type = htmlspecialchars($_GET['type']);
}

// Organiser les rendez-vous par date pour un affichage plus clair
$appointments_by_date = [];
foreach ($appointments as $appt) {
    $date = date('Y-m-d', strtotime($appt['appointment_datetime']));
    if (!isset($appointments_by_date[$date])) {
        $appointments_by_date[$date] = [];
    }
    $appointments_by_date[$date][] = $appt;
}
ksort($appointments_by_date); // Trier par date

?>