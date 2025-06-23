<?php
// Fichier : MEDECINE/cancel_appointment.php

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';

$message = '';
$message_type = '';

// Récupérer les messages de la redirection (GET parameters)
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars(str_replace('_', ' ', $_GET['message'])); // Remplacer les underscores par des espaces
    $message_type = htmlspecialchars($_GET['type']);
}

// S'assurer que l'utilisateur est connecté et est un patient
require_login('patient');

$patient_id = get_user_id();
$appointment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$appointment_id) {
    header('Location: ../../app/patient/dashboard_patient.php?message=ID_rendez-vous_manquant&type=error');
    exit();
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $pdo->beginTransaction();

    // Vérifier si le rendez-vous appartient bien au patient et qu'il n'est pas déjà passé/annulé
    $stmt = $pdo->prepare("SELECT appointment_datetime, status FROM appointments WHERE id = :appointment_id AND patient_id = :patient_id FOR UPDATE");
    $stmt->execute([
        ':appointment_id' => $appointment_id,
        ':patient_id' => $patient_id
    ]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        $pdo->rollBack();
        header('Location: ../../app/patient/dashboard_patient.php?message=Rendez-vous_introuvable_ou_non_autorise&type=error');
        exit();
    }

    if ($appointment['status'] === 'cancelled') {
        $pdo->rollBack();
        header('Location: ../../app/patient/dashboard_patient.php?message=Ce_rendez-vous_est_deja_annule&type=error');
        exit();
    }

    // Empêcher l'annulation si le rendez-vous est dans un délai trop court (ex: moins de 24h)
    // Vous pouvez ajuster cette logique selon vos règles métier
    $appointment_time = strtotime($appointment['appointment_datetime']);
    $cancellation_deadline = strtotime('+24 hours'); // 24 heures avant le rendez-vous

    if ($appointment_time < $cancellation_deadline) {
        $pdo->rollBack();
        header('Location: dashboard_patient.php?message=Impossible_d_annuler_ce_rendez-vous_moins_de_24h_a_l_avance&type=error');
        exit();
    }

    // Mettre à jour le statut du rendez-vous
    $stmt_update = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = :appointment_id AND patient_id = :patient_id");
    $stmt_update->execute([
        ':appointment_id' => $appointment_id,
        ':patient_id' => $patient_id
    ]);

    $pdo->commit();
    header('Location: dashboard_patient.php?message=Rendez-vous_annule_avec_succes&type=success');
    exit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error cancelling appointment: " . $e->getMessage()); // Log l'erreur
    header('Location: ../../app/patient/dashboard_patient.php?message=Erreur_lors_de_l_annulation_du_rendez-vous&type=error');
    exit();
}
?>