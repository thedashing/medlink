<?php
require_once '../../includes/auth_check.php';

require_once 'includes/Database.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $doctor_id = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
    
    if (!$doctor_id) {
        throw new Exception("ID médecin manquant");
    }

    // Récupérer les infos du médecin
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM doctors WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    // Récupérer les plannings
    $stmt = $pdo->prepare("SELECT * FROM doctor_schedules WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'doctorName' => $doctor['first_name'] . ' ' . $doctor['last_name'],
        'schedules' => $schedules
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}