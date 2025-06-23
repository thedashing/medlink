<?php
// Fichier : MEDECINE/get_available_days_for_month.php

require_once '../../includes/auth_check.php';

// Assurez-vous que le fuseau horaire est cohérent avec votre base de données et votre application JS
date_default_timezone_set('Africa/Ouagadougou'); 

require_once '../../includes/Database.php';

header('Content-Type: application/json');

$doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
$clinic_id = filter_input(INPUT_POST, 'clinic_id', FILTER_VALIDATE_INT);
$year_month = htmlspecialchars(trim($_POST['year_month'] ?? '')); // Format YYYY-MM
$service_duration = filter_input(INPUT_POST, 'service_duration', FILTER_VALIDATE_INT);

$days_with_slots = [];

if (!$doctor_id || !$clinic_id || empty($year_month) || !$service_duration) {
    echo json_encode(['error' => 'Paramètres manquants ou invalides.']);
    exit();
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Calculer le premier et le dernier jour du mois demandé
    $first_day_of_month = new DateTime($year_month . '-01');
    $last_day_of_month = new DateTime($first_day_of_month->format('Y-m-t'));

    $current_date_iterator = clone $first_day_of_month;
    
    // Obtenir la date d'aujourd'hui sans l'heure pour des comparaisons précises de jour
    // Le 19 juin 2025 à 14h51 (heure actuelle à Ouagadougou)
    $today_date_only = (new DateTime('now', new DateTimeZone('Africa/Ouagadougou')))->setTime(0, 0, 0); 
    // Obtenir le timestamp actuel avec l'heure pour la vérification des créneaux dans le jour actuel
    $current_timestamp_with_time = (new DateTime('now', new DateTimeZone('Africa/Ouagadougou')))->getTimestamp();

    while ($current_date_iterator <= $last_day_of_month) {
        $date_str = $current_date_iterator->format('Y-m-d');
        
        // --- Gérer les jours entièrement passés ---
        // Si le jour en cours d'itération est avant la date d'aujourd'hui, on le saute.
        if ($current_date_iterator < $today_date_only) {
            $current_date_iterator->modify('+1 day');
            continue; // Passe au jour suivant
        }
        // --- FIN GESTION JOURS PASSÉS ---

        $day_of_week_english = $current_date_iterator->format('l'); // Ex: 'Monday', 'Tuesday'
        
        // Mappage vers le français pour votre base de données si 'day_of_week' est en français
        $day_of_week_map = [
            'Sunday' => 'Dimanche', 'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
            'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi'
        ];
        $day_of_week_french = $day_of_week_map[$day_of_week_english];

        // 1. Récupérer le planning du médecin pour ce jour et cette clinique
        $stmt_schedule = $pdo->prepare("SELECT start_time, end_time FROM doctor_schedules
                                        WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id AND day_of_week = :day_of_week AND is_available = TRUE");
        $stmt_schedule->execute([
            ':doctor_id' => $doctor_id,
            ':clinic_id' => $clinic_id,
            ':day_of_week' => $day_of_week_french // Utilisez le jour en français
        ]);
        $schedules = $stmt_schedule->fetchAll(PDO::FETCH_ASSOC);

        // 2. Récupérer les rendez-vous déjà pris pour ce jour, ce médecin et cette clinique
        $stmt_booked = $pdo->prepare("SELECT appointment_datetime, end_datetime FROM appointments
                                      WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id
                                      AND DATE(appointment_datetime) = :date AND status IN ('pending', 'confirmed')");
        $stmt_booked->execute([
            ':doctor_id' => $doctor_id,
            ':clinic_id' => $clinic_id,
            ':date' => $date_str
        ]);
        $booked_appointments = $stmt_booked->fetchAll(PDO::FETCH_ASSOC);

        $has_at_least_one_slot = false;

        foreach ($schedules as $schedule) {
            $start_timestamp = strtotime($date_str . ' ' . $schedule['start_time']);
            $end_timestamp = strtotime($date_str . ' ' . $schedule['end_time']);

            while ($start_timestamp + ($service_duration * 60) <= $end_timestamp) {
                $slot_start_datetime = new DateTime(date('Y-m-d H:i:s', $start_timestamp));
                $slot_end_datetime = new DateTime(date('Y-m-d H:i:s', $start_timestamp + ($service_duration * 60)));
                $is_booked = false;

                // Vérifier si ce créneau chevauche un rendez-vous existant
                foreach ($booked_appointments as $booked_app) {
                    $booked_start = new DateTime($booked_app['appointment_datetime']);
                    $booked_end = new DateTime($booked_app['end_datetime']);

                    if (($slot_start_datetime < $booked_end) && ($slot_end_datetime > $booked_start)) {
                        $is_booked = true;
                        break;
                    }
                }
                
                // Vérifier si le créneau est dans le passé par rapport à l'heure actuelle
                // Cette vérification n'est pertinente que si $current_date_iterator est AUJOURD'HUI.
                if ($current_date_iterator->format('Y-m-d') === $today_date_only->format('Y-m-d') && $start_timestamp < $current_timestamp_with_time) {
                    $is_booked = true;
                }

                if (!$is_booked) {
                    $has_at_least_one_slot = true;
                    // Si on trouve un seul créneau disponible, on marque le jour et on sort.
                    break 2; // Sort de la boucle interne (while) ET de la boucle externe (foreach $schedules)
                }
                $start_timestamp += ($service_duration * 60); // Passe au créneau suivant
            }
        }

        if ($has_at_least_one_slot) {
            $days_with_slots[] = $date_str;
        }

        $current_date_iterator->modify('+1 day'); // Passe au jour suivant
    }

    echo json_encode($days_with_slots);

} catch (PDOException $e) {
    // En production, vous devriez logger l'erreur de manière sécurisée
    error_log("Error in get_available_days_for_month.php: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur serveur lors de la récupération des jours disponibles.']);
}