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
                    $start_datetime = trim($_POST['start_datetime']);
                    $end_datetime = trim($_POST['end_datetime']);
                    $reason = htmlspecialchars(trim($_POST['reason'] ?? ''));

                    // Validation simple (vous devriez ajouter plus de validation côté serveur)
                    if (!empty($start_datetime) && !empty($end_datetime)) {
                        $dt_start = new DateTime($start_datetime);
                        $dt_end = new DateTime($end_datetime);

                        if ($dt_start >= $dt_end) {
                            $message = "La date et l'heure de début doivent être antérieures à la date et l'heure de fin.";
                            $message_type = 'error';
                        } else {
                            $stmt_insert = $pdo->prepare("INSERT INTO doctor_unavailable_periods (doctor_id, start_datetime, end_datetime, reason)
                                                          VALUES (:doctor_id, :start_datetime, :end_datetime, :reason)");
                            if ($stmt_insert->execute([
                                ':doctor_id' => $doctor_id,
                                ':start_datetime' => $start_datetime,
                                ':end_datetime' => $end_datetime,
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
            header('Location: manage_schedules.php');
            exit();
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