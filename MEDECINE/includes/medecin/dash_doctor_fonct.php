<?php
// Fichier : MEDECINE/dashboard_doctor.php

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once 'Messaging.php'; // Inclure la classe Messaging

require_login('doctor');

$user_email = htmlspecialchars($_SESSION['user_email']);
$user_id = htmlspecialchars($_SESSION['user_id']); // C'est l'ID de l'utilisateur dans la table 'users'

$doctor_name = "Dr. " . $user_email; // Valeur par défaut
$doctor_db_id = null; // ID du docteur dans la table 'doctors'
$unread_messages_count = 0; // Initialisation du compteur

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Récupérer l'ID du docteur (ID dans la table 'doctors') à partir de l'ID utilisateur
    $stmt_doctor_db_id = $pdo->prepare("SELECT id, first_name, last_name FROM doctors WHERE user_id = :user_id");
    $stmt_doctor_db_id->execute([':user_id' => $user_id]);
    $doctor_info = $stmt_doctor_db_id->fetch(PDO::FETCH_ASSOC);

    if ($doctor_info) {
        $doctor_db_id = $doctor_info['id'];
        $doctor_name = "Dr. " . htmlspecialchars($doctor_info['first_name']) . " " . htmlspecialchars($doctor_info['last_name']);
    } else {
        throw new Exception("Profil médecin introuvable pour cet utilisateur.");
    }

    // --- MODIFICATION MAJEURE ICI : Utilisation de la classe Messaging pour les messages non lus ---
    $messaging = new Messaging();
    // Le recipient_id de la table messages est l'user_id (celui de la table users)
    $unread_messages_count = $messaging->getUnreadMessagesCount($user_id);
    // --- FIN DE LA MODIFICATION ---

} catch (PDOException $e) {
    error_log("Erreur de base de données sur dashboard_doctor.php: " . $e->getMessage());
    // Vous pouvez définir un message d'erreur à afficher à l'utilisateur si nécessaire
} catch (Exception $e) {
    error_log("Erreur générale sur dashboard_doctor.php: " . $e->getMessage());
}

?>