<?php
// Fichier : MEDECINE/doctor/manage_schedules.php


require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';

require_login('doctor'); // S'assurer que seul un médecin peut accéder

$user_id = htmlspecialchars($_SESSION['user_id']); // ID de l'utilisateur connecté (qui est le compte du médecin)
$doctor_id = null;
$doctor_name = "Médecin"; // Default name
$clinic_schedules = []; // Horaires du médecin par clinique
$unavailable_periods = []; // Périodes d'indisponibilité du médecin
$message = '';
$message_type = '';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Récupérer l'ID du médecin associé à l'utilisateur connecté
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM doctors WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $doctor_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($doctor_data) {
        $doctor_id = $doctor_data['id'];
        $doctor_name = htmlspecialchars($doctor_data['first_name'] . ' ' . $doctor_data['last_name']);

        // --- Traitement de l'ajout/suppression d'indisponibilité ---
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (isset($_POST['action'])) {
                if ($_POST['action'] == 'add_unavailable_period') {
                    $start_datetime_raw = trim($_POST['start_datetime']);
                    $end_datetime_raw = trim($_POST['end_datetime']);
                    $reason = htmlspecialchars(trim($_POST['reason'] ?? ''));

                    // Validation simple
                    if (!empty($start_datetime_raw) && !empty($end_datetime_raw)) {
                        
                        // --- CORRECTION CLÉ : Utiliser createFromFormat pour gérer le format de datetime-local ---
                        $dt_start = DateTime::createFromFormat('Y-m-d\TH:i', $start_datetime_raw);
                        $dt_end = DateTime::createFromFormat('Y-m-d\TH:i', $end_datetime_raw);

                        // Vérifier si les objets DateTime ont été créés avec succès
                        if ($dt_start === false || $dt_end === false) {
                            $message = "Format de date ou d'heure invalide. Assurez-vous que les champs sont remplis correctement.";
                            $message_type = 'error';
                        } elseif ($dt_start >= $dt_end) {
                            $message = "La date et l'heure de début doivent être antérieures à la date et l'heure de fin.";
                            $message_type = 'error';
                        } else {
                            // Si les formats sont corrects, convertissez-les au format SQL (YYYY-MM-DD HH:MM:SS)
                            $start_datetime_sql = $dt_start->format('Y-m-d H:i:s');
                            $end_datetime_sql = $dt_end->format('Y-m-d H:i:s');

                            $stmt_insert = $pdo->prepare("INSERT INTO doctor_unavailable_periods (doctor_id, start_datetime, end_datetime, reason)
                                                         VALUES (:doctor_id, :start_datetime, :end_datetime, :reason)");
                            if ($stmt_insert->execute([
                                ':doctor_id' => $doctor_id,
                                ':start_datetime' => $start_datetime_sql, // Utilisation du format SQL
                                ':end_datetime' => $end_datetime_sql,     // Utilisation du format SQL
                                ':reason' => $reason
                            ])) {
                                $message = "Période d'indisponibilité ajoutée avec succès !";
                                $message_type = 'success';
                            } else {
                                $message = "Erreur lors de l'ajout de la période d'indisponibilité.";
                                $message_type = 'error';
                            }
                        }
                    } else {
                        $message = "Veuillez remplir toutes les dates et heures pour l'indisponibilité.";
                        $message_type = 'error';
                    }
                } elseif ($_POST['action'] == 'delete_unavailable_period' && isset($_POST['period_id'])) {
                    $period_id = intval($_POST['period_id']);
                    // S'assurer que la période appartient bien à ce médecin
                    $stmt_delete = $pdo->prepare("DELETE FROM doctor_unavailable_periods WHERE id = :period_id AND doctor_id = :doctor_id");
                    if ($stmt_delete->execute([':period_id' => $period_id, ':doctor_id' => $doctor_id])) {
                        $message = "Période d'indisponibilité supprimée avec succès !";
                        $message_type = 'success';
                    } else {
                        $message = "Erreur lors de la suppression de la période ou période non trouvée.";
                        $message_type = 'error';
                    }
                }
            }
            // After POST, redirect to prevent form resubmission on refresh
            header('Location: manage_schedules.php?message=' . urlencode($message) . '&type=' . urlencode($message_type));
            exit();
        }
        
        // Handle message from GET redirect
        if (isset($_GET['message']) && isset($_GET['type'])) {
            $message = htmlspecialchars($_GET['message']);
            $message_type = htmlspecialchars($_GET['type']);
        }


        // --- Récupérer tous les horaires du médecin par clinique ---
        $sql_schedules = "SELECT
                            dcs.id AS schedule_id,
                            dcs.day_of_week,
                            dcs.start_time,
                            dcs.end_time,
                            c.name AS clinic_name,
                            c.address AS clinic_address,
                            c.city AS clinic_city
                         FROM
                            doctor_schedules dcs
                         JOIN
                            clinics c ON dcs.clinic_id = c.id
                         WHERE
                            dcs.doctor_id = :doctor_id
                         ORDER BY
                            FIELD(dcs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                            c.name, dcs.start_time";

        $stmt_schedules = $pdo->prepare($sql_schedules);
        $stmt_schedules->execute([':doctor_id' => $doctor_id]);
        $clinic_schedules = $stmt_schedules->fetchAll(PDO::FETCH_ASSOC);

        // --- Récupérer toutes les périodes d'indisponibilité du médecin ---
        // Fetch only future or ongoing unavailable periods first, then past ones.
        $sql_unavailable = "SELECT id, start_datetime, end_datetime, reason FROM doctor_unavailable_periods
                            WHERE doctor_id = :doctor_id
                            ORDER BY 
                                CASE 
                                    WHEN end_datetime >= NOW() THEN 1 
                                    ELSE 2 
                                END, 
                                start_datetime ASC"; // Futures/actuelles en premier, puis passées
        $stmt_unavailable = $pdo->prepare($sql_unavailable);
        $stmt_unavailable->execute([':doctor_id' => $doctor_id]);
        $unavailable_periods = $stmt_unavailable->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $message = "Votre profil de médecin n'est pas trouvé. Veuillez contacter l'administrateur.";
        $message_type = 'error';
    }

} catch (PDOException $e) {
    error_log("Erreur de base de données dans manage_schedules.php (médecin) : " . $e->getMessage());
    $message = "Une erreur est survenue lors du chargement ou de la gestion de vos plannings. Veuillez réessayer.";
    $message_type = 'error';
} catch (Exception $e) {
    error_log("Erreur inattendue dans manage_schedules.php (médecin) : " . $e->getMessage());
    $message = "Une erreur inattendue est survenue: " . $e->getMessage();
    $message_type = 'error';
}

// Définir les jours de la semaine pour l'affichage
$days_of_week = [
    'Monday' => 'Lundi',
    'Tuesday' => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday' => 'Jeudi',
    'Friday' => 'Vendredi',
    'Saturday' => 'Samedi',
    'Sunday' => 'Dimanche'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer mes Plannings - <?php echo $doctor_name ?? 'Médecin'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/medecin/manage_schedules.css">
</head>
<body>
    <div class="container">
        <div class="header-main">
            <h1>Gérer mes Plannings</h1>
            <a href="dashboard_doctor.php">Retour au Tableau de Bord</a>
        </div>

        <?php if ($doctor_id): ?>
            <div class="user-info">
                <p>Connecté en tant que: **Dr. <?php echo $doctor_name; ?>**</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <hr class="section-separator">

            <h2>Vos Horaires de Consultation</h2>
            <?php if (empty($clinic_schedules)): ?>
                <div class="no-entries">
                    <p>Aucun horaire de consultation n'est actuellement défini pour vous dans les cliniques.</p>
                    <p>Veuillez contacter les cliniques associées pour qu'elles vous assignent des heures de consultation, afin que les patients puissent prendre rendez-vous.</p>
                </div>
            <?php else: ?>
                <?php
                // Regrouper les horaires par clinique pour un affichage plus clair
                $grouped_schedules = [];
                foreach ($clinic_schedules as $schedule) {
                    $grouped_schedules[$schedule['clinic_name']][] = $schedule;
                }
                ?>
                <div class="schedules-by-clinic">
                    <?php foreach ($grouped_schedules as $clinic_name_group => $schedules_for_clinic): ?>
                        <div class="clinic-schedule-group">
                            <h3><?php echo htmlspecialchars($clinic_name_group); ?><br><small>(<?php echo htmlspecialchars($schedules_for_clinic[0]['clinic_address'] . ', ' . $schedules_for_clinic[0]['clinic_city']); ?>)</small></h3>
                            <?php foreach ($schedules_for_clinic as $schedule): ?>
                                <div class="schedule-item">
                                    <span>
                                        <strong><?php echo htmlspecialchars($days_of_week[$schedule['day_of_week']]); ?></strong> :
                                        <?php echo htmlspecialchars(substr($schedule['start_time'], 0, 5)); ?> -
                                        <?php echo htmlspecialchars(substr($schedule['end_time'], 0, 5)); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <hr class="section-separator">

            <h2>Déclarer une Période d'Indisponibilité</h2>
            <p>Utilisez cette section pour bloquer des dates ou des plages horaires pendant lesquelles vous ne serez pas disponible. Cela empêchera les patients de prendre rendez-vous sur ces créneaux.</p>
            <div class="form-section">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add_unavailable_period">
                    <div class="form-group">
                        <label for="start_datetime">Début de l'indisponibilité:</label>
                        <input type="datetime-local" id="start_datetime" name="start_datetime" required min="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_datetime">Fin de l'indisponibilité:</label>
                        <input type="datetime-local" id="end_datetime" name="end_datetime" required min="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="reason">Raison (facultatif):</label>
                        <input type="text" id="reason" name="reason" placeholder="Ex: Congés annuels, Conférence médicale, Maladie...">
                    </div>
                    <button type="submit">Ajouter l'indisponibilité</button>
                </form>
            </div>

            <hr class="section-separator">

            <h2>Vos Périodes d'Indisponibilité Actuelles et Futures</h2>
            <?php if (empty($unavailable_periods)): ?>
                <div class="no-entries">
                    <p>Vous n'avez aucune période d'indisponibilité déclarée.</p>
                    <p>Pour ajouter une indisponibilité, utilisez le formulaire ci-dessus.</p>
                </div>
            <?php else: ?>
                <div class="unavailable-list">
                    <?php foreach ($unavailable_periods as $period): ?>
                        <div class="unavailable-item">
                            <span>
                                Du <strong><?php echo (new DateTime($period['start_datetime']))->format('d/m/Y H:i'); ?></strong>
                                au <strong><?php echo (new DateTime($period['end_datetime']))->format('d/m/Y H:i'); ?></strong>
                                (Raison: <?php echo htmlspecialchars($period['reason'] ?: 'Non spécifié'); ?>)
                            </span>
                            <form action="" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette période d\'indisponibilité ? Cela pourrait rendre les créneaux concernés disponibles à nouveau.');">
                                <input type="hidden" name="action" value="delete_unavailable_period">
                                <input type="hidden" name="period_id" value="<?php echo $period['id']; ?>">
                                <button type="submit" class="delete-button">Supprimer</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="message error">
                <p>Votre profil de médecin n'a pas pu être chargé. Veuillez vérifier votre connexion ou contacter l'administrateur.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>