<?php
// Fichier : MEDECINE/app/medecin/doctor_messages.php

// Assurez-vous que le fuseau horaire est défini pour éviter les avertissements de date
date_default_timezone_set('Africa/Ouagadougou');

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once '../../includes/medecin/Messaging.php'; // Inclure la classe Messaging

require_login('doctor'); // S'assurer que seul un médecin est connecté

$user_id = get_user_id(); // L'ID utilisateur du médecin connecté
$doctor_db_id = null; // L'ID du médecin dans la table 'doctors'
$doctor_name_display = "Médecin"; // Pour l'affichage dans le titre

$db = Database::getInstance();
$pdo = $db->getConnection();
$messaging = new Messaging();

$message_status = ''; // Pour afficher les messages de succès/erreur

// --- Récupérer l'ID du médecin et son nom depuis la table 'doctors' ---
try {
    $stmt_doctor_db_id = $pdo->prepare("SELECT id, first_name, last_name FROM doctors WHERE user_id = :user_id");
    $stmt_doctor_db_id->execute([':user_id' => $user_id]);
    $doctor_info = $stmt_doctor_db_id->fetch(PDO::FETCH_ASSOC);

    if ($doctor_info) {
        $doctor_db_id = $doctor_info['id'];
        $doctor_name_display = htmlspecialchars($doctor_info['first_name'] . ' ' . $doctor_info['last_name']);
    } else {
        throw new Exception("Profil médecin introuvable.");
    }
} catch (Exception $e) {
    error_log("Erreur lors de la récupération de l'ID du médecin: " . $e->getMessage());
    $message_status = '<div class="message error">Erreur: Profil médecin introuvable ou problème de base de données.</div>';
    // Empêcher le reste de la page de charger si l'ID du médecin n'est pas trouvé
    // ou afficher une page d'erreur plus générale si cela est critique.
    // Pour cet exemple, on continue mais avec un doctor_db_id nul.
}

// --- Gérer l'envoi d'un nouveau message ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $doctor_db_id) {
    $recipient_patient_id = filter_input(INPUT_POST, 'recipient_patient_id', FILTER_VALIDATE_INT);
    $subject = htmlspecialchars(trim($_POST['subject']));
    $content = htmlspecialchars(trim($_POST['content']));

    if ($recipient_patient_id && !empty($subject) && !empty($content)) {
        // Récupérer l'user_id du patient à partir de son patient_id
        $stmt_patient_user_id = $pdo->prepare("SELECT user_id FROM patients WHERE id = :patient_id");
        $stmt_patient_user_id->execute([':patient_id' => $recipient_patient_id]);
        $patient_user_id = $stmt_patient_user_id->fetchColumn();

        if ($patient_user_id) {
            // Utiliser la méthode sendMessage de la classe Messaging
            $success = $messaging->sendMessage($user_id, $patient_user_id, $subject, $content);

            if ($success) {
                $message_status = '<div class="message success">Message envoyé avec succès !</div>';
                // Effacer les champs du formulaire après l'envoi pour éviter la re-soumission
                $_POST['subject'] = '';
                $_POST['content'] = '';
            } else {
                $message_status = '<div class="message error">Erreur lors de l\'envoi du message.</div>';
            }
        } else {
            $message_status = '<div class="message error">Destinataire patient invalide ou non trouvé.</div>';
        }
    } else {
        $message_status = '<div class="message error">Veuillez remplir tous les champs.</div>';
    }
}

// --- Récupérer la liste des patients du médecin pour le sélecteur de destinataire ---
// On ne liste ici que les patients avec qui le médecin a eu (ou a) un rendez-vous
$patients = [];
if ($doctor_db_id) { // Assurez-vous que l'ID du docteur est valide avant de récupérer les patients
    try {
        $stmt_patients = $pdo->prepare("
            SELECT DISTINCT p.id, p.first_name, p.last_name
            FROM patients p
            JOIN appointments a ON p.id = a.patient_id
            WHERE a.doctor_id = :doctor_db_id
            ORDER BY p.last_name, p.first_name
        ");
        $stmt_patients->execute([':doctor_db_id' => $doctor_db_id]);
        $patients = $stmt_patients->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erreur lors de la récupération des patients: " . $e->getMessage());
        $message_status .= '<div class="message error">Erreur lors du chargement des patients.</div>';
    }
}


// --- Récupérer les messages reçus par le médecin ---
$received_messages = [];
try {
    $stmt_received_messages = $pdo->prepare("
        SELECT m.*, u.email AS sender_email,
               CASE u.role
                   WHEN 'patient' THEN CONCAT(pa.first_name, ' ', pa.last_name)
                   WHEN 'clinic' THEN cl.name
                   WHEN 'doctor' THEN CONCAT(d.first_name, ' ', d.last_name)
                   ELSE 'Système' -- Pour les messages envoyés par le système (sender_id = 0)
               END AS sender_name
        FROM messages m
        JOIN users u ON m.sender_id = u.id OR m.sender_id = 0 /* Permet de joindre pour les messages système */
        LEFT JOIN patients pa ON u.id = pa.user_id AND u.role = 'patient'
        LEFT JOIN doctors d ON u.id = d.user_id AND u.role = 'doctor'
        LEFT JOIN clinics cl ON u.id = cl.user_id AND u.role = 'clinic'
        WHERE m.recipient_id = :user_id
        ORDER BY m.created_at DESC
    ");
    $stmt_received_messages->execute([':user_id' => $user_id]);
    $received_messages = $stmt_received_messages->fetchAll(PDO::FETCH_ASSOC);

    // Marquer tous les messages reçus par ce médecin comme lus une fois la page consultée
    $messaging->markAllAsRead($user_id);

} catch (Exception $e) {
    error_log("Erreur lors de la récupération des messages reçus: " . $e->getMessage());
    $message_status .= '<div class="message error">Erreur lors du chargement des messages reçus.</div>';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messagerie - Dr. <?php echo $doctor_name_display; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/medecin/medecin_message.css">

</head>
<body>
    <div class="container">
        <div class="header-main">
            <h1>Messagerie Dr. <?php echo $doctor_name_display; ?></h1>
            <a href="dashboard_doctor.php">Retour au Tableau de Bord</a>
        </div>

        <div class="user-info">
            <p>Gérez vos communications avec les patients.</p>
        </div>

        <?php echo $message_status; // Afficher le statut de l'envoi ?>

        <div class="form-section">
            <h2>Envoyer un nouveau message à un patient</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="recipient_patient_id">Destinataire (Patient) :</label>
                    <select id="recipient_patient_id" name="recipient_patient_id" required <?php echo empty($patients) ? 'disabled' : ''; ?>>
                        <option value="">Sélectionnez un patient</option>
                        <?php if (!empty($patients)): ?>
                            <?php foreach ($patients as $patient): ?>
                                <option value="<?php echo htmlspecialchars($patient['id']); ?>">
                                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>Aucun patient trouvé pour le moment (Basé sur vos rendez-vous)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="subject">Sujet :</label>
                    <input type="text" id="subject" name="subject" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" required placeholder="Ex: Concernant votre traitement...">
                </div>
                <div class="form-group">
                    <label for="content">Contenu du message :</label>
                    <textarea id="content" name="content" required placeholder="Tapez votre message ici..."><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" name="send_message" <?php echo empty($patients) ? 'disabled' : ''; ?>>Envoyer le message</button>
                </div>
            </form>
        </div>

        <h2>Messages Reçus</h2>
        <div class="message-list">
            <?php if (!empty($received_messages)): ?>
                <?php foreach ($received_messages as $message): ?>
                    <div class="message-item <?php echo $message['is_read'] == 0 ? 'unread' : ''; ?>">
                        <div class="message-header">
                            <span>De: <strong><?php echo htmlspecialchars($message['sender_name'] ?: $message['sender_email']); ?></strong></span>
                            <span>Reçu le: <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?></span>
                        </div>
                        <div class="message-subject">Sujet: <?php echo htmlspecialchars($message['subject']); ?></div>
                        <div class="message-content"><?php echo htmlspecialchars($message['content']); ?></div>
                        <?php if ($message['appointment_id']): ?>
                            <div class="message-appointment-link">
                                <small>Lié au rendez-vous ID: <?php echo htmlspecialchars($message['appointment_id']); ?></small>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="message">
                    <p>Aucun message reçu pour le moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>