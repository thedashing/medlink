<?php

// Fichier : MEDECINE/get_available_slots.php

// Toujours s'assurer que les erreurs ne sont pas affichées en production
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
// Assurez-vous que ce chemin est correct et que le répertoire est accessible en écriture par le serveur web
ini_set('error_log', __DIR__ . '/../../logs/php-error.log'); 
error_reporting(E_ALL); // Pour capturer toutes les erreurs dans le log

require_once '../../includes/auth_check.php'; // Vérifie l'authentification de l'utilisateur

// Définir le fuseau horaire de l'application. C'est crucial pour des calculs de temps précis.
// Assurez-vous que cela correspond à la configuration de votre serveur de base de données si possible.
date_default_timezone_set('Africa/Ouagadougou'); 

require_once '../../includes/Database.php'; // Votre classe de connexion à la base de données

header('Content-Type: application/json'); // Indiquer que la réponse est du JSON

// Filtrer et valider les entrées POST
$doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
$clinic_id = filter_input(INPUT_POST, 'clinic_id', FILTER_VALIDATE_INT);
$date_str = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING); // Nettoyage de la chaîne de date
$service_duration = filter_input(INPUT_POST, 'service_duration', FILTER_VALIDATE_INT);

$available_slots = []; // Initialise le tableau pour stocker les créneaux disponibles

// Vérification préliminaire des paramètres. Un paramètre manquant ou invalide => erreur.
if (!$doctor_id || !$clinic_id || empty($date_str) || !$service_duration) {
    echo json_encode(['error' => 'Paramètres manquants ou invalides.']);
    exit();
}

// Vérifier le format de la date pour s'assurer qu'elle est valide
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
    echo json_encode(['error' => 'Format de date invalide.']);
    exit();
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection(); // Obtient l'instance PDO de la base de données

    // Obtenir le jour de la semaine en anglais (ex: 'Monday')
    // Il est crucial que cela corresponde exactement au format stocké dans `doctor_schedules`.
    // Si votre `day_of_week` est en français, ajustez cette ligne :
    // $day_of_week = date('l', strtotime($date_str)); // Ex: 'Monday'
    // $day_of_week_map = ['Sunday' => 'Dimanche', 'Monday' => 'Lundi', ...];
    // $day_of_week = $day_of_week_map[$day_of_week_english];
    // Pour l'instant, je garde 'l' car c'était votre format initial.
    $day_of_week = date('l', strtotime($date_str)); 

    // 1. Récupérer les plages horaires de travail du médecin pour ce jour et cette clinique
    $stmt = $pdo->prepare("SELECT start_time, end_time FROM doctor_schedules
                            WHERE doctor_id = :doctor_id 
                            AND clinic_id = :clinic_id 
                            AND day_of_week = :day_of_week 
                            AND is_available = TRUE"); 
    $stmt->execute([
        ':doctor_id' => $doctor_id,
        ':clinic_id' => $clinic_id,
        ':day_of_week' => $day_of_week
    ]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Si aucune plage horaire n'est définie pour ce jour, il n'y a pas de créneaux.
    if (empty($schedules)) {
        echo json_encode([]);
        exit();
    }

    // 2. Récupérer les rendez-vous déjà pris pour ce médecin, cette clinique et ce jour
    $stmt_booked = $pdo->prepare("SELECT appointment_datetime, end_datetime FROM appointments
                            WHERE doctor_id = :doctor_id 
                            AND clinic_id = :clinic_id
                            AND DATE(appointment_datetime) = :date 
                            AND status IN ('pending', 'confirmed', 'completed')");
    $stmt_booked->execute([
        ':doctor_id' => $doctor_id,
        ':clinic_id' => $clinic_id,
        ':date' => $date_str
    ]);
    $booked_appointments = $stmt_booked->fetchAll(PDO::FETCH_ASSOC);

    // 3. NOUVEAU : Récupérer les périodes d'indisponibilité du médecin pour ce jour
    $stmt_unavailable = $pdo->prepare("SELECT start_datetime, end_datetime FROM doctor_unavailable_periods
                                        WHERE doctor_id = :doctor_id
                                        AND DATE(start_datetime) <= :date 
                                        AND DATE(end_datetime) >= :date"); // Cherche les périodes qui chevauchent la date
    $stmt_unavailable->execute([
        ':doctor_id' => $doctor_id,
        ':date' => $date_str
    ]);
    $unavailable_periods = $stmt_unavailable->fetchAll(PDO::FETCH_ASSOC);

    // Combiner tous les intervalles occupés (rendez-vous et indisponibilités) en timestamps
    $occupied_intervals = [];

    // Ajouter les rendez-vous
    foreach ($booked_appointments as $booked_app) {
        $occupied_intervals[] = [
            'start' => strtotime($booked_app['appointment_datetime']),
            'end' => strtotime($booked_app['end_datetime'])
        ];
    }

    // Ajouter les périodes d'indisponibilité
    foreach ($unavailable_periods as $period) {
        // Pour les indisponibilités qui peuvent s'étendre sur plusieurs jours,
        // nous limitons l'intervalle à la journée actuelle pour la comparaison.
        $period_start_ts = strtotime($period['start_datetime']);
        $period_end_ts = strtotime($period['end_datetime']);
        
        $day_start_ts = strtotime($date_str . ' 00:00:00');
        $day_end_ts = strtotime($date_str . ' 23:59:59');

        $occupied_intervals[] = [
            'start' => max($period_start_ts, $day_start_ts), // Le début de l'indisponibilité ne peut pas être avant le début du jour
            'end' => min($period_end_ts, $day_end_ts)       // La fin de l'indisponibilité ne peut pas être après la fin du jour
        ];
    }
    
    // Trier les intervalles occupés par heure de début pour optimiser les vérifications (facultatif mais recommandé pour de grandes quantités)
    usort($occupied_intervals, function($a, $b) {
        return $a['start'] <=> $b['start'];
    });

    $current_datetime = new DateTime();
    $current_datetime_timestamp = $current_datetime->getTimestamp();

    // Itérer sur chaque plage horaire définie dans le planning du médecin
    foreach ($schedules as $schedule) {
        // Calculer les timestamps de début et de fin de la plage horaire du médecin pour la date donnée
        $schedule_start_timestamp = strtotime($date_str . ' ' . $schedule['start_time']);
        $schedule_end_timestamp = strtotime($date_str . ' ' . $schedule['end_time']);

        // Définir le pas d'incrémentation (par exemple, toutes les 15 minutes)
        $increment_interval_minutes = 15; 
        // Initialiser le premier créneau potentiel
        $current_slot_start_timestamp = $schedule_start_timestamp;

        // Boucler tant que le créneau de fin potentiel ne dépasse pas la fin de la plage horaire du médecin
        while (($current_slot_start_timestamp + ($service_duration * 60)) <= $schedule_end_timestamp) {
            
            $slot_end_timestamp = $current_slot_start_timestamp + ($service_duration * 60);
            $is_available = true; // Présumer que le créneau est disponible

            // 1. Vérifier si le créneau est dans le passé
            // Cette vérification est cruciale pour le jour courant.
            if ($current_slot_start_timestamp < $current_datetime_timestamp) {
                $is_available = false;
            }

            // 2. Vérifier les chevauchements avec TOUS les intervalles occupés (rendez-vous ET indisponibilités)
            if ($is_available) { // Seulement si pas déjà marqué comme non disponible
                foreach ($occupied_intervals as $occupied_interval) {
                    // Les conditions de chevauchement :
                    // (Slot commence avant la fin de l'intervalle occupé ET Slot finit après le début de l'intervalle occupé)
                    if (($current_slot_start_timestamp < $occupied_interval['end']) && 
                        ($slot_end_timestamp > $occupied_interval['start'])) {
                        $is_available = false; // Il y a un chevauchement
                        break; // Pas besoin de vérifier d'autres intervalles pour ce créneau
                    }
                }
            }
            
            // Si le créneau est disponible après toutes les vérifications
            if ($is_available) {
                $available_slots[] = [
                    'start' => date('H:i', $current_slot_start_timestamp), // Heure formatée (ex: "09:00")
                    'full_start' => date('Y-m-d H:i:s', $current_slot_start_timestamp) // Date et heure complètes
                ];
            }

            // Passer au prochain créneau potentiel
            $current_slot_start_timestamp += ($increment_interval_minutes * 60); // Avance de l'intervalle de génération
        }
    }

    echo json_encode($available_slots); // Retourne les créneaux disponibles au format JSON

} catch (PDOException $e) {
    // En cas d'erreur de base de données, loguer l'erreur et renvoyer un message générique.
    error_log("Database Error in get_available_slots.php: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur serveur lors de la récupération des créneaux.']);
} catch (Exception $e) {
    // Pour toute autre erreur inattendue
    error_log("General Error in get_available_slots.php: " . $e->getMessage());
    echo json_encode(['error' => 'Une erreur inattendue est survenue.']);
}
?>