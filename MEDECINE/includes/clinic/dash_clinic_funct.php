<?php
// Fichier : MEDECINE/dashboard_clinic.php

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php'; // Assurez-vous que c'est bien inclus

require_login('clinic');

$user_email = htmlspecialchars($_SESSION['user_email']);
$user_id = htmlspecialchars($_SESSION['user_id']);

$clinic_name = "Votre Clinique"; // Valeur par défaut
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare("SELECT name FROM clinics WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $clinic_info = $stmt->fetch();
    if ($clinic_info) {
        $clinic_name = htmlspecialchars($clinic_info['name']);
    }
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération du nom de la clinique: " . $e->getMessage());
}

?>