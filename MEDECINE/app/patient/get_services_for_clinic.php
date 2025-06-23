<?php
// Fichier : MEDECINE/get_services_for_clinic.php


date_default_timezone_set('Africa/Ouagadougou'); // Assurez-vous de la cohérence
require_once '../../includes/auth_check.php';

require_once '../../includes/Database.php';

header('Content-Type: application/json');

$doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
$clinic_id = filter_input(INPUT_POST, 'clinic_id', FILTER_VALIDATE_INT);

if (!$doctor_id || !$clinic_id) {
    echo json_encode(['error' => 'Paramètres manquants ou invalides.']);
    exit();
}

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("SELECT id, name, price, description, duration_minutes
                            FROM services
                            WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id
                            ORDER BY name");
    $stmt->execute([
        ':doctor_id' => $doctor_id,
        ':clinic_id' => $clinic_id
    ]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($services);

} catch (PDOException $e) {
    error_log("Error in get_services_for_clinic.php: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur serveur lors de la récupération des services.']);
}
?>