<?php
// Fichier : MEDECINE/planification_medecin/manage_doctor_schedule.php

require_once '../../../includes/auth_check.php';
require_once '../../../includes/Database.php';

require_login('clinic');

// Vérifier les paramètres requis
if (!isset($_GET['doctor_id']) || !is_numeric($_GET['doctor_id']) ||
    !isset($_GET['clinic_id']) || !is_numeric($_GET['clinic_id'])) {
    header("Location: index.php"); // Rediriger si les IDs sont manquants
    exit();
}

$doctor_id = intval($_GET['doctor_id']);
$clinic_id = intval($_GET['clinic_id']);
$user_id_clinic_owner = htmlspecialchars($_SESSION['user_id']); // L'ID de l'utilisateur qui est le propriétaire de la clinique

$doctor_info = null;
$clinic_info = null;
$doctor_schedules = [];
$message = '';
$message_type = '';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Vérifier que la clinique appartient bien à l'utilisateur connecté
    $stmt_owner_check = $pdo->prepare("SELECT id, name FROM clinics WHERE id = :clinic_id AND user_id = :user_id");
    $stmt_owner_check->execute([':clinic_id' => $clinic_id, ':user_id' => $user_id_clinic_owner]);
    $clinic_info = $stmt_owner_check->fetch(PDO::FETCH_ASSOC);

    if (!$clinic_info) {
        $message = "Accès non autorisé ou clinique introuvable.";
        $message_type = 'error';
        // Rediriger ou afficher une erreur grave
        // header("Location: index.php?message=unauthorized_access");
        // exit();
    } else {
        // Récupérer les infos du médecin (vérifier qu'il est bien associé à CETTE clinique)
        $stmt_doctor_info = $pdo->prepare("SELECT d.id, d.first_name, d.last_name
                                            FROM doctors d
                                            JOIN clinic_doctors cd ON d.id = cd.doctor_id
                                            WHERE d.id = :doctor_id AND cd.clinic_id = :clinic_id");
        $stmt_doctor_info->execute([':doctor_id' => $doctor_id, ':clinic_id' => $clinic_id]);
        $doctor_info = $stmt_doctor_info->fetch(PDO::FETCH_ASSOC);

        if (!$doctor_info) {
            $message = "Médecin non trouvé ou non associé à cette clinique.";
            $message_type = 'error';
            // Rediriger ou afficher une erreur
            // header("Location: index.php?message=doctor_not_found");
            // exit();
        } else {
            // --- Traitement de l'ajout/suppression de créneau ---
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                if (isset($_POST['action'])) {
                    if ($_POST['action'] == 'add_schedule') {
                        $day_of_week = htmlspecialchars(trim($_POST['day_of_week']));
                        $start_time = htmlspecialchars(trim($_POST['start_time']));
                        $end_time = htmlspecialchars(trim($_POST['end_time']));

                        // Validation simple
                        if (!empty($day_of_week) && !empty($start_time) && !empty($end_time)) {
                            // Vérifier si le créneau existe déjà pour éviter les doublons
                            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM doctor_clinic_schedules
                                                         WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id
                                                         AND day_of_week = :day_of_week AND start_time = :start_time AND end_time = :end_time");
                            $stmt_check->execute([
                                ':doctor_id' => $doctor_id,
                                ':clinic_id' => $clinic_id,
                                ':day_of_week' => $day_of_week,
                                ':start_time' => $start_time,
                                ':end_time' => $end_time
                            ]);
                            if ($stmt_check->fetchColumn() > 0) {
                                $message = "Ce créneau horaire existe déjà.";
                                $message_type = 'error';
                            } else {
                                $stmt_insert = $pdo->prepare("INSERT INTO doctor_schedules (doctor_id, clinic_id, day_of_week, start_time, end_time)
                                                            VALUES (:doctor_id, :clinic_id, :day_of_week, :start_time, :end_time)");
                                if ($stmt_insert->execute([
                                    ':doctor_id' => $doctor_id,
                                    ':clinic_id' => $clinic_id,
                                    ':day_of_week' => $day_of_week,
                                    ':start_time' => $start_time,
                                    ':end_time' => $end_time
                                ])) {
                                    $message = "Créneau ajouté avec succès !";
                                    $message_type = 'success';
                                } else {
                                    $message = "Erreur lors de l'ajout du créneau.";
                                    $message_type = 'error';
                                }
                                echo "<script>console.log('" . json_encode($day_of_week) . "');</script>";

                            }
                        } else {
                            $message = "Veuillez remplir tous les champs du créneau.";
                            $message_type = 'error';
                        }
                    } elseif ($_POST['action'] == 'delete_schedule' && isset($_POST['schedule_id'])) {
                        $schedule_id = intval($_POST['schedule_id']);
                        // S'assurer que le créneau appartient bien à ce médecin et cette clinique
                        $stmt_delete = $pdo->prepare("DELETE FROM clinic_schedules WHERE id = :schedule_id AND doctor_id = :doctor_id AND clinic_id = :clinic_id");
                        if ($stmt_delete->execute([':schedule_id' => $schedule_id, ':doctor_id' => $doctor_id, ':clinic_id' => $clinic_id])) {
                            $message = "Créneau supprimé avec succès !";
                            $message_type = 'success';
                        } else {
                            $message = "Erreur lors de la suppression du créneau ou créneau non trouvé.";
                            $message_type = 'error';
                        }
                    }
                }
            }

            // Récupérer tous les créneaux horaires pour ce médecin et cette clinique
            $stmt_schedules = $pdo->prepare("SELECT id, day_of_week, start_time, end_time FROM doctor_schedules
                                              WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id
                                              ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time");
            $stmt_schedules->execute([':doctor_id' => $doctor_id, ':clinic_id' => $clinic_id]);
            $doctor_schedules = $stmt_schedules->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (PDOException $e) {
    error_log("Erreur de base de données dans manage_doctor_schedule.php : " . $e->getMessage());
    $message = "Une erreur est survenue lors de l'opération sur la base de données. Veuillez réessayer.";
    $message_type = 'error';
}

// Définir les jours de la semaine pour la liste déroulante
$days_of_week = [
    'Monday' => 'Lundi',
    'Tuesday' => 'Mardi',
    'Wednesday' => 'Mercredi',
    'Thursday' => 'Jeudi',
    'Friday' => 'Vendredi',
    'Saturday' => 'Samedi',
    'Sunday' => 'Dimanche',

];
?>