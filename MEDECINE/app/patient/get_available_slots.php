<?php

// Fichier : MEDECINE/get_available_slots.php
require_once '../../includes/auth_check.php';

date_default_timezone_set('Africa/Ouagadougou'); // Ou 'UTC' si votre base de données est en UTC
require_once '../../includes/Database.php';

header('Content-Type: application/json'); // Indiquer que la réponse est du JSON

$doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
$clinic_id = filter_input(INPUT_POST, 'clinic_id', FILTER_VALIDATE_INT);
$date_str = htmlspecialchars(trim($_POST['date'] ?? ''));
$service_duration = filter_input(INPUT_POST, 'service_duration', FILTER_VALIDATE_INT);

$available_slots = [];

if (!$doctor_id || !$clinic_id || empty($date_str) || !$service_duration) {
    echo json_encode(['error' => 'Paramètres manquants ou invalides.']);
    exit();
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $day_of_week = date('l', strtotime($date_str)); // Ex: 'Monday'

    // 1. Récupérer le planning du médecin pour ce jour et cette clinique
    $stmt = $pdo->prepare("SELECT start_time, end_time FROM doctor_schedules
                            WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id AND day_of_week = :day_of_week AND is_available = TRUE");
    $stmt->execute([
        ':doctor_id' => $doctor_id,
        ':clinic_id' => $clinic_id,
        ':day_of_week' => $day_of_week
    ]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);


    // 2. Récupérer les rendez-vous déjà pris pour ce médecin, cette clinique et ce jour
    $stmt = $pdo->prepare("SELECT appointment_datetime, end_datetime FROM appointments
                            WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id
                            AND DATE(appointment_datetime) = :date AND status IN ('pending', 'confirmed')");
    $stmt->execute([
        ':doctor_id' => $doctor_id,
        ':clinic_id' => $clinic_id,
        ':date' => $date_str
    ]);
    $booked_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $today = new DateTime();
    $today->setTime(0, 0, 0); // Pour comparer juste la date

    foreach ($schedules as $schedule) {
        
        $start_timestamp = strtotime($date_str . ' ' . $schedule['start_time']);
        $end_timestamp = strtotime($date_str . ' ' . $schedule['end_time']);

        // S'assurer que le créneau de début ne commence pas avant la date/heure actuelle
        // Seulement si le jour actuel est le jour demandé
        $current_datetime = new DateTime();
        $current_datetime_timestamp = $current_datetime->getTimestamp();

        while ($start_timestamp + ($service_duration * 60) <= $end_timestamp) {
            $slot_start_datetime = new DateTime(date('Y-m-d H:i:s', $start_timestamp));
            $slot_end_datetime = new DateTime(date('Y-m-d H:i:s', $start_timestamp + ($service_duration * 60)));

            $is_booked = false;

            // Vérifier si ce créneau chevauche un rendez-vous existant
            foreach ($booked_appointments as $booked_app) {
                $booked_start = new DateTime($booked_app['appointment_datetime']);
                $booked_end = new DateTime($booked_app['end_datetime']);

                // Vérifier les chevauchements
                if ( ($slot_start_datetime < $booked_end) && ($slot_end_datetime > $booked_start) ) {
                    $is_booked = true;
                    break;
                }
            }

            // Vérifier si le créneau est dans le passé
            if ($start_timestamp < $current_datetime_timestamp) {
                $is_booked = true; // Marquer comme non disponible si dans le passé
            }

            if (!$is_booked) {
                $available_slots[] = [
                    'start' => date('H:i', $start_timestamp),
                    'full_start' => date('Y-m-d H:i:s', $start_timestamp)
                ];
            }
            $start_timestamp += ($service_duration * 60); // Passer au créneau suivant
        }
    }

    echo json_encode($available_slots);

} catch (PDOException $e) {
    // En production, loguer l'erreur
    error_log("Error in get_available_slots.php: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur serveur lors de la récupération des créneaux.']);
}
?>