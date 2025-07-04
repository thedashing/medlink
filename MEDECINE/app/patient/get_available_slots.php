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
    $day_of_week = date('l', strtotime($date_str)); 

    // 1. Récupérer les plages horaires de travail du médecin pour ce jour et cette clinique
    // is_available = TRUE est une bonne condition pour les horaires actifs
    $stmt = $pdo->prepare("SELECT start_time, end_time FROM doctor_schedules
                            WHERE doctor_id = :doctor_id 
                            AND clinic_id = :clinic_id 
                            AND day_of_week = :day_of_week 
                            AND is_available = TRUE"); // S'assurer que le planning est actif
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

    // 2. Récupérer les rendez-vous déjà pris (confirmés ou en attente) pour ce médecin, cette clinique et ce jour
    // Il est crucial d'inclure end_datetime pour un calcul précis des chevauchements.
    $stmt = $pdo->prepare("SELECT appointment_datetime, end_datetime FROM appointments
                            WHERE doctor_id = :doctor_id 
                            AND clinic_id = :clinic_id
                            AND DATE(appointment_datetime) = :date 
                            AND status IN ('pending', 'confirmed', 'completed')"); // Inclure 'completed' si vous voulez empêcher de re-réserver sur des rdv passés mais non annulés
    $stmt->execute([
        ':doctor_id' => $doctor_id,
        ':clinic_id' => $clinic_id,
        ':date' => $date_str
    ]);
    $booked_appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convertir les rendez-vous réservés en timestamps pour faciliter la comparaison
    $booked_intervals = [];
    foreach ($booked_appointments as $booked_app) {
        $booked_intervals[] = [
            'start' => strtotime($booked_app['appointment_datetime']),
            'end' => strtotime($booked_app['end_datetime'])
        ];
    }

    // Récupérer la date et l'heure actuelles (pour ne pas proposer de créneaux passés)
    $current_datetime = new DateTime();
    $current_datetime_timestamp = $current_datetime->getTimestamp();

    // Itérer sur chaque plage horaire définie dans le planning du médecin
    foreach ($schedules as $schedule) {
        // Calculer les timestamps de début et de fin de la plage horaire du médecin pour la date donnée
        $schedule_start_timestamp = strtotime($date_str . ' ' . $schedule['start_time']);
        $schedule_end_timestamp = strtotime($date_str . ' ' . $schedule['end_time']);

        // Le pas d'incrémentation pour générer les créneaux. 
        // Si les services peuvent commencer à n'importe quelle minute,
        // vous pourriez le définir à 1 minute, mais cela générerait beaucoup de calculs.
        // Un pas de 15 ou 30 minutes est courant pour les RDV.
        // Actuellement, vous incrémentez par la durée du service, ce qui est bien
        // si les RDV sont censés s'enchaîner sans interruption.
        $increment_interval_minutes = 15; // Par exemple, vérifier toutes les 15 minutes si un créneau de X minutes est libre.
                                        // Ou utiliser $service_duration si les créneaux sont de cette taille exacte.
                                        // Pour votre logique actuelle qui génère des slots de $service_duration,
                                        // $start_timestamp += ($service_duration * 60) est correct.

        // Initialiser le premier créneau potentiel
        $current_slot_start_timestamp = $schedule_start_timestamp;

        // Boucler tant que le créneau de fin potentiel ne dépasse pas la fin de la plage horaire du médecin
        while (($current_slot_start_timestamp + ($service_duration * 60)) <= $schedule_end_timestamp) {
            
            $slot_end_timestamp = $current_slot_start_timestamp + ($service_duration * 60);
            $is_available = true; // Présumer que le créneau est disponible

            // 1. Vérifier si le créneau est dans le passé
            // Cela s'applique spécifiquement pour le jour courant.
            if (date('Y-m-d', $current_slot_start_timestamp) == date('Y-m-d', $current_datetime_timestamp) && 
                $current_slot_start_timestamp < $current_datetime_timestamp) {
                $is_available = false;
            }

            // 2. Vérifier les chevauchements avec les rendez-vous existants
            if ($is_available) { // Seulement si pas déjà marqué comme non disponible
                foreach ($booked_intervals as $booked_interval) {
                    // Les conditions de chevauchement :
                    // (Slot commence avant la fin du RDV ET Slot finit après le début du RDV)
                    if (($current_slot_start_timestamp < $booked_interval['end']) && 
                        ($slot_end_timestamp > $booked_interval['start'])) {
                        $is_available = false; // Il y a un chevauchement
                        break; // Pas besoin de vérifier d'autres rendez-vous pour ce créneau
                    }
                }
            }

            // 3. (Optionnel) Vérifier les congés/indisponibilités du médecin
            // Si vous avez une table `doctor_absences` ou similaire.
            /*
            if ($is_available) {
                $stmt_absences = $pdo->prepare("SELECT start_datetime, end_datetime FROM doctor_absences
                                                WHERE doctor_id = :doctor_id AND DATE(start_datetime) = :date");
                $stmt_absences->execute([':doctor_id' => $doctor_id, ':date' => $date_str]);
                $absences = $stmt_absences->fetchAll(PDO::FETCH_ASSOC);

                foreach ($absences as $absence) {
                    $absence_start = strtotime($absence['start_datetime']);
                    $absence_end = strtotime($absence['end_datetime']);
                    if (($current_slot_start_timestamp < $absence_end) && ($slot_end_timestamp > $absence_start)) {
                        $is_available = false;
                        break;
                    }
                }
            }
            */

            // Si le créneau est disponible après toutes les vérifications
            if ($is_available) {
                $available_slots[] = [
                    'start' => date('H:i', $current_slot_start_timestamp), // Heure formatée (ex: "09:00")
                    'full_start' => date('Y-m-d H:i:s', $current_slot_start_timestamp) // Date et heure complètes
                ];
            }

            // Passer au prochain créneau potentiel
            // Si vous voulez des créneaux qui commencent à des intervalles fixes (ex: toutes les 15 min),
            // même si le service dure 30 min, utilisez $increment_interval_minutes.
            // Si vous voulez que les services s'enchaînent immédiatement, gardez ($service_duration * 60).
            $current_slot_start_timestamp += ($increment_interval_minutes * 60); // Ex: pour avancer de 15 minutes à chaque fois
            // Si vous voulez juste des blocs de durée de service, utilisez:
            // $current_slot_start_timestamp = $slot_end_timestamp;
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