<?php
// Fichier : MEDECINE/planification_medecin/index.php


require_once '../../../includes/auth_check.php';
require_once '../../../includes/Database.php';

require_login('clinic'); // S'assurer que seul une clinique peut accéder

$user_id = htmlspecialchars($_SESSION['user_id']); // ID de l'utilisateur connecté (qui est le compte de la clinique)
$clinic_id = null;
$clinic_doctors = [];
$message = '';
$message_type = '';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Récupérer l'ID de la clinique associée à l'utilisateur connecté
    $stmt = $pdo->prepare("SELECT id FROM clinics WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $clinic_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($clinic_data) {
        $clinic_id = $clinic_data['id'];

        // Récupérer tous les médecins associés à cette clinique
        $sql_doctors = "SELECT
                            d.id AS doctor_id,
                            d.first_name,
                            d.last_name,
                            GROUP_CONCAT(s.name ORDER BY s.name SEPARATOR ', ') AS doctor_specialties
                        FROM
                            doctors d
                        JOIN
                            clinic_doctors cd ON d.id = cd.doctor_id
                        LEFT JOIN
                            doctor_specialties ds ON d.id = ds.doctor_id
                        LEFT JOIN
                            specialties s ON ds.specialty_id = s.id
                        WHERE
                            cd.clinic_id = :clinic_id
                        GROUP BY
                            d.id, d.first_name, d.last_name
                        ORDER BY
                            d.last_name, d.first_name";

        $stmt_doctors = $pdo->prepare($sql_doctors);
        $stmt_doctors->execute([':clinic_id' => $clinic_id]);
        $clinic_doctors = $stmt_doctors->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $message = "Aucune clinique trouvée pour cet utilisateur.";
        $message_type = 'error';
    }

} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des médecins de la clinique : " . $e->getMessage());
    $message = "Une erreur est survenue lors du chargement des médecins. Veuillez réessayer.";
    $message_type = 'error';
}

?>