<?php
// Fichier : MEDECINE/mes_rendez_vous.php

// Inclure le fichier de vérification d'authentification
require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php'; // Inclure la connexion à la base de données

// Appeler la fonction pour s'assurer que l'utilisateur est connecté ET qu'il est un patient
require_login('patient');

// Les informations de session sont maintenant garanties d'être présentes et correctes pour un patient
$user_email = htmlspecialchars($_SESSION['user_email']);
$user_id = htmlspecialchars($_SESSION['user_id']); // L'ID de l'utilisateur connecté (de la table 'users')

$patient_db_id = null; // Variable pour stocker l'ID du patient de la table 'patients'

$upcoming_appointments = [];
$confirmed_appointments = []; 
$cancelled_appointments = []; 
$past_completed_appointments = []; 
$message = '';
$message_type = '';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // D'abord, récupérer l'ID réel du patient de la table 'patients' en utilisant l'user_id de la session
    $stmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($patient_data) {
        $patient_db_id = $patient_data['id'];
    } else {
        // Gérer le cas où l'ID du patient n'est pas trouvé dans la table 'patients'
        $message = "Erreur : ID patient introuvable. Veuillez contacter l'administration.";
        $message_type = 'error';
        error_log("Patient ID not found in 'patients' table for user_id: " . $user_id);
        // Empêcher la suite de l'exécution si l'ID patient est manquant
        exit(); // Stop script execution as patient ID is crucial
    }

    // Récupérer les rendez-vous à venir (statut 'pending', 'confirmed', et date dans le futur)
    // J'ai mis 'pending' et 'confirmed' ici, si 'pending' est un statut initial avant confirmation
    // sinon, si 'pending' signifie "en attente de validation", il faudrait un statut "confirmed"
    // S'ils ont le même sens, gardez 'pending' ou changez tous vos statuts en 'confirmed' pour la cohérence.
    $stmt_upcoming = $pdo->prepare("SELECT
                                        a.id AS appointment_id,
                                        a.appointment_datetime,
                                        a.end_datetime,
                                        a.status,
                                        d.first_name AS doctor_first_name,
                                        d.last_name AS doctor_last_name,
                                        d.profile_picture_url AS profile_picture_url,
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
                                        a.patient_id = :patient_id
                                        AND a.appointment_datetime >= NOW()
                                        AND a.status IN ('pending', 'confirmed') 
                                    ORDER BY
                                        a.appointment_datetime ASC");
    $stmt_upcoming->execute([':patient_id' => $patient_db_id]);
    $upcoming_appointments = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);


    // Récupérer les rendez-vous annulés
    $stmt_cancelled = $pdo->prepare("SELECT
                                        a.id AS appointment_id,
                                        a.appointment_datetime,
                                        a.end_datetime,
                                        a.status,
                                        d.first_name AS doctor_first_name,
                                        d.last_name AS doctor_last_name,
                                        d.profile_picture_url AS profile_picture_url,

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
                                        a.patient_id = :patient_id
                                        AND a.status = 'cancelled'
                                    ORDER BY
                                        a.appointment_datetime DESC");
    $stmt_cancelled->execute([':patient_id' => $patient_db_id]);
    $cancelled_appointments = $stmt_cancelled->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer l'historique des rendez-vous passés et complétés (non annulés et date dans le passé)
    $stmt_past_completed = $pdo->prepare("SELECT
                                        a.id AS appointment_id,
                                        a.appointment_datetime,
                                        a.end_datetime,
                                        a.status,
                                        d.first_name AS doctor_first_name,
                                        d.last_name AS doctor_last_name,
                                        d.profile_picture_url AS profile_picture_url,
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
                                        a.patient_id = :patient_id
                                        AND a.appointment_datetime < NOW()
                                        AND a.status NOT IN ('cancelled', 'pending') 
                                    ORDER BY
                                        a.appointment_datetime DESC");
    $stmt_past_completed->execute([':patient_id' => $patient_db_id]);
    $past_completed_appointments = $stmt_past_completed->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    $message = "Erreur de base de données : " . $e->getMessage();
    $message_type = 'error';
    error_log("DB Error on mes_rendez_vous.php: " . $e->getMessage());
}

?>