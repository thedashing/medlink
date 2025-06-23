<?php
// Fichier : MEDECINE/clinics/invite_doctor.php


require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';

require_login('clinic');

$user_id = htmlspecialchars($_SESSION['user_id']); // ID utilisateur du propriétaire de la clinique

$clinic_id = null;
$clinic_name = "Votre Clinique";
$message = '';
$message_type = '';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Récupérer l'ID de la clinique pour l'utilisateur connecté
    $stmt_clinic = $pdo->prepare("SELECT id, name FROM clinics WHERE user_id = :user_id");
    $stmt_clinic->execute([':user_id' => $user_id]);
    $clinic_data = $stmt_clinic->fetch(PDO::FETCH_ASSOC);

    if ($clinic_data) {
        $clinic_id = $clinic_data['id'];
        $clinic_name = htmlspecialchars($clinic_data['name']);

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (isset($_POST['doctor_email']) && !empty($_POST['doctor_email'])) {
                $doctor_email = htmlspecialchars(trim($_POST['doctor_email']));

                // 1. Trouver l'user_id du médecin basé sur l'email et le rôle 'doctor'
                $stmt_doctor_user = $pdo->prepare("SELECT id FROM users WHERE email = :email AND role = 'doctor'");
                $stmt_doctor_user->execute([':email' => $doctor_email]);
                $doctor_user = $stmt_doctor_user->fetch(PDO::FETCH_ASSOC);

                if ($doctor_user) {
                    $doctor_user_id_from_users = $doctor_user['id']; // C'est l'ID de la table 'users'
                    
                    // Récupérer l'ID réel du médecin depuis la table 'doctors'
                    $stmt_doctor = $pdo->prepare("SELECT id FROM doctors WHERE user_id = :user_id");
                    $stmt_doctor->execute([':user_id' => $doctor_user_id_from_users]);
                    $doctor_data = $stmt_doctor->fetch(PDO::FETCH_ASSOC);

                    if ($doctor_data) {
                        $actual_doctor_id = $doctor_data['id']; // C'est l'ID de la table 'doctors'

                        // 2. Vérifier si une invitation existe déjà ou si le médecin est déjà lié
                        $stmt_check = $pdo->prepare("
                            SELECT invitation_status FROM clinic_doctor_invitations
                            WHERE clinic_id = :clinic_id AND doctor_id = :doctor_id
                        ");
                        $stmt_check->execute([
                            ':clinic_id' => $clinic_id,
                            ':doctor_id' => $actual_doctor_id
                        ]);
                        $existing_invitation = $stmt_check->fetch(PDO::FETCH_ASSOC);

                        if ($existing_invitation) {
                            if ($existing_invitation['invitation_status'] == 'accepted') {
                                $message = "Ce médecin est déjà associé à votre clinique.";
                                $message_type = 'error';
                            } else {
                                $message = "Une invitation a déjà été envoyée à ce médecin et est en attente ou a été refusée.";
                                $message_type = 'error';
                            }
                        } else {
                            // 3. Insérer l'invitation
                            $stmt_insert = $pdo->prepare("
                                INSERT INTO clinic_doctor_invitations (clinic_id, doctor_id, invitation_status)
                                VALUES (:clinic_id, :doctor_id, 'pending')
                            ");
                            if ($stmt_insert->execute([
                                ':clinic_id' => $clinic_id,
                                ':doctor_id' => $actual_doctor_id
                            ])) {
                                $message = "Invitation envoyée avec succès à " . $doctor_email . " !";
                                $message_type = 'success';
                            } else {
                                $message = "Erreur lors de l'envoi de l'invitation. Veuillez réessayer.";
                                $message_type = 'error';
                            }
                        }
                    } else {
                        $message = "Profil de médecin introuvable pour cet e-mail. Assurez-vous que le médecin a complété son profil.";
                        $message_type = 'error';
                    }
                } else {
                    $message = "Aucun médecin trouvé avec cette adresse e-mail.";
                    $message_type = 'error';
                }
            } else {
                $message = "Veuillez entrer l'adresse e-mail d'un médecin.";
                $message_type = 'error';
            }
        }

        // Récupérer les invitations actuelles pour l'affichage
        $stmt_invitations = $pdo->prepare("
            SELECT cdi.id, d.first_name, d.last_name, u.email, cdi.invitation_status, cdi.invited_at
            FROM clinic_doctor_invitations cdi
            JOIN doctors d ON cdi.doctor_id = d.id
            JOIN users u ON d.user_id = u.id
            WHERE cdi.clinic_id = :clinic_id
            ORDER BY cdi.invited_at DESC
        ");
        $stmt_invitations->execute([':clinic_id' => $clinic_id]);
        $current_invitations = $stmt_invitations->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $message = "Aucune clinique trouvée pour cet utilisateur. Veuillez contacter l'administrateur.";
        $message_type = 'error';
    }

} catch (PDOException $e) {
    error_log("Erreur dans invite_doctor.php: " . $e->getMessage());
    $message = "Une erreur inattendue est survenue. Veuillez réessayer.";
    $message_type = 'error';
}

?>