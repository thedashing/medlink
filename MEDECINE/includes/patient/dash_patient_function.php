<?php
// Fichier : MEDECINE/dashboard_patient.php

// Inclure le fichier de vérification d'authentification
require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php'; // Inclure la connexion à la base de données
require_once 'Messaging_functions.php'; // Inclure la classe Messaging pour le compteur de messages

// Appeler la fonction pour s'assurer que l'utilisateur est connecté ET qu'il est un patient
require_login('patient');

// Les informations de session sont maintenant garanties d'être présentes et correctes pour un patient
$user_email = htmlspecialchars($_SESSION['user_email']);
$user_id = htmlspecialchars($_SESSION['user_id']); // L'ID du patient connecté

$upcoming_appointments = [];
$past_appointments = [];
$message = '';
$message_type = '';
$unread_messages_count = 0; // Initialisation du compteur
  
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Récupérer les informations du patient (si des infos spécifiques au patient étaient stockées dans la table patients)
    // Par exemple, pour afficher le prénom/nom si on les avait collectés lors de l'inscription du patient.
    // Pour l'instant, on utilise l'email.

    // Récupérer les rendez-vous à venir
    $stmt_upcoming = $pdo->prepare("SELECT
                                        a.id AS appointment_id,
                                        a.appointment_datetime,
                                        a.end_datetime,
                                        a.status,
                                        d.first_name AS doctor_first_name,
                                        d.last_name AS doctor_last_name,
                                        cl.name AS clinic_name,
                                        cl.address AS clinic_address,
                                        s.name AS service_name,
                                        s.price AS service_price
                                    FROM
                                        appointments a
                                    JOIN
                                        doctors d ON a.doctor_id = d.id
                                    JOIN
                                        clinics cl ON a.clinic_id = cl.id
                                    JOIN
                                        services s ON a.service_id = s.id
                                    WHERE
                                        a.patient_id = (SELECT id FROM patients WHERE user_id = :user_id LIMIT 1) 
                                        AND a.appointment_datetime >= NOW()
                                        AND a.status != 'cancelled'
                                    ORDER BY
                                        a.appointment_datetime ASC");
    $stmt_upcoming->execute([':user_id' => $user_id]);
    $upcoming_appointments = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les rendez-vous passés (ou annulés)
    $stmt_past = $pdo->prepare("SELECT
                                        a.id AS appointment_id,
                                        a.appointment_datetime,
                                        a.end_datetime,
                                        a.status,
                                        d.first_name AS doctor_first_name,
                                        d.last_name AS doctor_last_name,
                                        cl.name AS clinic_name,
                                        cl.address AS clinic_address,
                                        s.name AS service_name,
                                        s.price AS service_price
                                    FROM
                                        appointments a
                                    JOIN
                                        doctors d ON a.doctor_id = d.id
                                    JOIN
                                        clinics cl ON a.clinic_id = cl.id
                                    JOIN
                                        services s ON a.service_id = s.id
                                    WHERE
                                        a.patient_id = (SELECT id FROM patients WHERE user_id = :user_id LIMIT 1) 
                                        AND (a.appointment_datetime < NOW() OR a.status = 'cancelled')
                                    ORDER BY
                                        a.appointment_datetime DESC");
    $stmt_past->execute([':user_id' => $user_id]);
    $past_appointments = $stmt_past->fetchAll(PDO::FETCH_ASSOC);

    // Initialisation de la classe Messaging et récupération du nombre de messages non lus
    $messaging = new Messaging();
    $unread_messages_count = $messaging->getUnreadMessagesCount($user_id);

} catch (PDOException $e) {
    $message = "Erreur de base de données : " . $e->getMessage();
    $message_type = 'error';
    error_log("DB Error on dashboard_patient.php: " . $e->getMessage());
}

?>