<?php
// Fichier : MEDECINE/app/patient/mon_dossier.php

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';

// S'assurer que l'utilisateur est connecté et est un patient
require_login('patient');

$user_id = htmlspecialchars($_SESSION['user_id']); // L'ID de l'utilisateur (patient) connecté
$patient_db_id = null; // L'ID du patient dans la table 'patients'
$medical_records = [];
$message = '';
$message_type = '';

// Retrieve user's email for the header, ensuring it's set
$user_email = isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : 'Patient';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // D'abord, récupérer l'ID du patient à partir de l'ID utilisateur
    $stmt_patient_id = $pdo->prepare("SELECT id FROM patients WHERE user_id = :user_id");
    $stmt_patient_id->execute([':user_id' => $user_id]);
    $patient_info = $stmt_patient_id->fetch(PDO::FETCH_ASSOC);

    if ($patient_info) {
        $patient_db_id = $patient_info['id'];
    } else {
        throw new Exception("Aucun profil patient trouvé pour votre compte. Veuillez contacter l'administration.");
    }

    // Récupérer les dossiers médicaux pour ce patient
    $stmt_records = $pdo->prepare("SELECT
                                        mr.id AS record_id,
                                        mr.diagnosis,
                                        mr.treatment,
                                        mr.notes,
                                        mr.record_date,
                                        a.appointment_datetime,
                                        d.first_name AS doctor_first_name,
                                        d.last_name AS doctor_last_name,
                                        cl.name AS clinic_name,
                                        cl.address AS clinic_address,
                                        s.name AS service_name,
                                        s.price AS service_price
                                    FROM
                                        medical_records mr
                                    JOIN
                                        appointments a ON mr.appointment_id = a.id
                                    JOIN
                                        doctors d ON a.doctor_id = d.id
                                    JOIN
                                        clinics cl ON a.clinic_id = cl.id
                                    JOIN
                                        services s ON a.service_id = s.id
                                    WHERE
                                        mr.patient_id = :patient_db_id
                                    ORDER BY
                                        mr.record_date DESC"); // Trier par date du dossier, le plus récent en premier
    $stmt_records->execute([':patient_db_id' => $patient_db_id]);
    $medical_records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    error_log("Medical records page error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Dossier Médical - Patient</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="../../public/css/patient/mon_dossier.css">

</head>
<body>
    <div class="container">
        <div class="header-main">
            <h1>Mon Dossier Médical</h1>
            <a href="dashboard_patient.php">Retour au Tableau de Bord</a>
        </div>

        <div class="user-info">
            <p>Connecté en tant que: **<?php echo $user_email; ?>**</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="system-message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title">Historique de mes Dossiers Médicaux</h2>

        <?php if (empty($medical_records)): ?>
            <div class="no-records">
                <p>Aucun dossier médical trouvé pour le moment.</p>
                <p>Les dossiers médicaux sont ajoutés par votre médecin après une consultation terminée. N'hésitez pas à prendre un rendez-vous pour commencer votre suivi !</p>
            </div>
        <?php else: ?>
            <div class="records-list">
                <?php foreach ($medical_records as $record): ?>
                    <div class="record-card">
                        <h3>Dossier du <?php echo date('d/m/Y', strtotime($record['record_date'])); ?></h3>
                        <p><strong>Date de la consultation:</strong> <span class="detail-value"><?php echo date('d/m/Y à H:i', strtotime($record['appointment_datetime'])); ?></span></p>
                        <p><strong>Médecin:</strong> <span class="detail-value">Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?></span></p>
                        <p><strong>Clinique:</strong> <span class="detail-value"><?php echo htmlspecialchars($record['clinic_name']); ?>, <?php echo htmlspecialchars($record['clinic_address']); ?></span></p>
                        <p><strong>Service:</strong> <span class="detail-value"><?php echo htmlspecialchars($record['service_name']); ?> (<?php echo htmlspecialchars(number_format($record['service_price'], 0, ',', ' ')); ?> FCFA)</span></p>
                        <div class="diagnosis-notes">
                            <p><strong>Diagnostic:</strong> <br><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
                            <p><strong>Traitement:</strong> <br><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></p>
                            <p><strong>Notes du médecin:</strong> <br><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>