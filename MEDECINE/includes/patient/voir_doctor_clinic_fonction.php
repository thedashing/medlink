<?php
// Fichier : MEDECINE/view_clinic_doctors.php

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_login('patient');

// Rediriger si clinic_id n'est pas fourni dans l'URL
if (!isset($_GET['clinic_id']) || !is_numeric($_GET['clinic_id'])) {
    header("Location: search_clinics.php"); // Rediriger vers la page de recherche (nom de fichier corrigé si nécessaire)
    exit();
}

$clinic_id = intval($_GET['clinic_id']);
$clinic_info = null;
$doctors_in_clinic = [];
$message = '';
$message_type = '';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // 1. Récupérer les informations de la clinique
    $stmt_clinic = $pdo->prepare("SELECT id, name, address, phone, email, website, description, city, country FROM clinics WHERE id = :clinic_id");
    $stmt_clinic->execute([':clinic_id' => $clinic_id]);
    $clinic_info = $stmt_clinic->fetch(PDO::FETCH_ASSOC);

    if (!$clinic_info) {
        $message = "Clinique non trouvée.";
        $message_type = 'error';
        // Envisagez une redirection ici si la clinique n'existe pas
        header("Location: search_clinics.php?message=clinic_not_found"); // Rediriger en cas de clinique introuvable
        exit();
    }

    // 2. Récupérer les médecins associés à cette clinique avec leurs spécialités
    // On utilise la table doctor_clinic_specialties pour lier médecins, cliniques et spécialités.
    $sql_doctors = "SELECT
                        d.id AS doctor_id,
                        d.first_name,
                        d.last_name,
                        d.bio,
                        d.language,
                        GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS doctor_specialties
                    FROM
                        doctors d
                    JOIN
                        clinic_doctors cd ON d.id = cd.doctor_id
                    LEFT JOIN
                        doctor_clinic_specialties dcs ON d.id = dcs.doctor_id AND cd.clinic_id = dcs.clinic_id
                    LEFT JOIN
                        specialties s ON dcs.specialty_id = s.id
                    WHERE
                        cd.clinic_id = :clinic_id
                    GROUP BY
                        d.id, d.first_name, d.last_name, d.bio, d.language
                    ORDER BY
                        d.last_name, d.first_name";

    $stmt_doctors = $pdo->prepare($sql_doctors);
    $stmt_doctors->execute([':clinic_id' => $clinic_id]);
    $doctors_in_clinic = $stmt_doctors->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des détails de la clinique ou des médecins : " . $e->getMessage());
    $message = "Une erreur est survenue lors du chargement des informations. Veuillez réessayer.";
    $message_type = 'error';
}
?>