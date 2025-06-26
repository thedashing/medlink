<?php
// Fichier : MEDECINE/get_available_days_for_month.php

ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/../../logs/php-error.log'); 
error_reporting(E_ALL);

require_once '../../includes/auth_check.php';
date_default_timezone_set('Africa/Ouagadougou'); 
require_once '../../includes/Database.php';

header('Content-Type: application/json');

$doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
$clinic_id = filter_input(INPUT_POST, 'clinic_id', FILTER_VALIDATE_INT);
$year_month = filter_input(INPUT_POST, 'year_month', FILTER_SANITIZE_STRING); // Format YYYY-MM
$service_duration = filter_input(INPUT_POST, 'service_duration', FILTER_VALIDATE_INT);

$days_with_slots = [];

if (!$doctor_id || !$clinic_id || empty($year_month) || !$service_duration) {
    echo json_encode(['error' => 'Paramètres manquants ou invalides.']);
    exit();
}

if (!preg_match('/^\d{4}-\d{2}$/', $year_month)) { // Validation pour YYYY-MM
    echo json_encode(['error' => 'Format de année-mois invalide.']);
    exit();
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $first_day_of_month = new DateTime($year_month . '-01');
    $last_day_of_month = new DateTime($first_day_of_month->format('Y-m-t'));

    $current_date_iterator = clone $first_day_of_month;
    
    $today_date_only = (new DateTime('now', new DateTimeZone('Africa/Ouagadougou')))->setTime(0, 0, 0); 
    $current_timestamp_with_time = (new DateTime('now', new DateTimeZone('Africa/Ouagadougou')))->getTimestamp();

    // Mapping des jours de la semaine (anglais vers français)
    $day_of_week_map = [
        'Sunday' => 'Dimanche', 'Monday' => 'Lundi', 'Tuesday' => 'Mardi', 'Wednesday' => 'Mercredi',
        'Thursday' => 'Jeudi', 'Friday' => 'Vendredi', 'Saturday' => 'Samedi'
    ];

    while ($current_date_iterator <= $last_day_of_month) {
        $date_str = $current_date_iterator->format('Y-m-d');
        
        // Sauter les jours entièrement passés
        if ($current_date_iterator < $today_date_only) {
            $current_date_iterator->modify('+1 day');
            continue;
        }

        $day_of_week_english = $current_date_iterator->format('l');
        $day_of_week_french = $day_of_week_map[$day_of_week_english];

        // 1. Récupérer le planning du médecin pour ce jour et cette clinique
        $stmt_schedule = $pdo->prepare("SELECT start_time, end_time FROM doctor_schedules
                                        WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id AND day_of_week = :day_of_week AND is_available = TRUE");
        $stmt_schedule->execute([
            ':doctor_id' => $doctor_id,
            ':clinic_id' => $clinic_id,
            ':day_of_week' => $day_of_week_french
        ]);
        $schedules = $stmt_schedule->fetchAll(PDO::FETCH_ASSOC);

        // Si pas de planning pour ce jour, on passe au jour suivant
        if (empty($schedules)) {
            $current_date_iterator->modify('+1 day');
            continue;
        }

        // 2. Récupérer les rendez-vous déjà pris pour ce jour, ce médecin et cette clinique
        $stmt_booked = $pdo->prepare("SELECT appointment_datetime, end_datetime FROM appointments
                                        WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id
                                        AND DATE(appointment_datetime) = :date AND status IN ('pending', 'confirmed', 'completed')");
        $stmt_booked->execute([
            ':doctor_id' => $doctor_id,
            ':clinic_id' => $clinic_id,
            ':date' => $date_str
        ]);
        $booked_appointments = $stmt_booked->fetchAll(PDO::FETCH_ASSOC);

        // 3. Récupérer les périodes d'indisponibilité du médecin qui chevauchent ce jour
        // Important: une période d'indisponibilité peut s'étendre sur plusieurs jours.
        // On vérifie si la période commence avant ou pendant le jour en cours ET finit après ou pendant le jour en cours.
        $stmt_unavailable = $pdo->prepare("SELECT start_datetime, end_datetime FROM doctor_unavailable_periods
                                            WHERE doctor_id = :doctor_id
                                            AND DATE(start_datetime) <= :date AND DATE(end_datetime) >= :date");
        $stmt_unavailable->execute([
            ':doctor_id' => $doctor_id,
            ':date' => $date_str
        ]);
        $unavailable_periods = $stmt_unavailable->fetchAll(PDO::FETCH_ASSOC);

        // Combiner tous les intervalles occupés
        $occupied_intervals = [];
        foreach ($booked_appointments as $booked_app) {
            $occupied_intervals[] = [
                'start' => strtotime($booked_app['appointment_datetime']),
                'end' => strtotime($booked_app['end_datetime'])
            ];
        }
        foreach ($unavailable_periods as $period) {
            // Pour les indisponibilités, on s'assure de prendre la partie qui tombe dans le jour actuel
            // et de limiter les timestamps au début/fin de journée si la période est plus longue.
            $period_start_ts = strtotime($period['start_datetime']);
            $period_end_ts = strtotime($period['end_datetime']);
            
            // Limiter l'indisponibilité au jour actuel
            $day_start_ts = strtotime($date_str . ' 00:00:00');
            $day_end_ts = strtotime($date_str . ' 23:59:59');

            $occupied_intervals[] = [
                'start' => max($period_start_ts, $day_start_ts),
                'end' => min($period_end_ts, $day_end_ts)
            ];
        }
        
        // Trier les intervalles occupés (facultatif mais bonne pratique)
        usort($occupied_intervals, function($a, $b) {
            return $a['start'] <=> $b['start'];
        });

        $has_at_least_one_slot = false;
        $increment_interval_minutes = 15; // Pas pour la génération des créneaux potentiels

        foreach ($schedules as $schedule) {
            $schedule_start_timestamp = strtotime($date_str . ' ' . $schedule['start_time']);
            $schedule_end_timestamp = strtotime($date_str . ' ' . $schedule['end_time']);

            $current_slot_start_timestamp = $schedule_start_timestamp;

            while (($current_slot_start_timestamp + ($service_duration * 60)) <= $schedule_end_timestamp) {
                $slot_end_timestamp = $current_slot_start_timestamp + ($service_duration * 60);
                $is_available = true;

                // Vérifier si le créneau est dans le passé par rapport à l'heure actuelle
                if ($current_date_iterator->format('Y-m-d') === $today_date_only->format('Y-m-d') && $current_slot_start_timestamp < $current_timestamp_with_time) {
                    $is_available = false;
                }

                // Vérifier les chevauchements avec tous les intervalles occupés (rendez-vous et indisponibilités)
                if ($is_available) {
                    foreach ($occupied_intervals as $occupied_interval) {
                        if (($current_slot_start_timestamp < $occupied_interval['end']) && 
                            ($slot_end_timestamp > $occupied_interval['start'])) {
                            $is_available = false;
                            break; 
                        }
                    }
                }
                
                if ($is_available) {
                    $has_at_least_one_slot = true;
                    break 2; // Sort de la boucle `while` ET du `foreach ($schedules)`
                }
                
                $current_slot_start_timestamp += ($increment_interval_minutes * 60);
            }
        }

        if ($has_at_least_one_slot) {
            $days_with_slots[] = $date_str;
        }

        $current_date_iterator->modify('+1 day');
    }

    echo json_encode($days_with_slots);

} catch (PDOException $e) {
    error_log("Database Error in get_available_days_for_month.php: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur serveur lors de la récupération des jours disponibles.']);
} catch (Exception $e) {
    error_log("General Error in get_available_days_for_month.php: " . $e->getMessage());
    echo json_encode(['error' => 'Une erreur inattendue est survenue.']);
}
?>