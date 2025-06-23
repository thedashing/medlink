<?php
// Fichier : MEDECINE/doctor/manage_invitations.php


require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';

require_login('doctor');

$user_id = htmlspecialchars($_SESSION['user_id']); // ID utilisateur du médecin
$doctor_id = null; // ID réel du médecin depuis la table doctors
$message = '';
$message_type = '';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Récupérer l'ID du médecin pour l'utilisateur connecté
    $stmt_doctor = $pdo->prepare("SELECT id FROM doctors WHERE user_id = :user_id");
    $stmt_doctor->execute([':user_id' => $user_id]);
    $doctor_data = $stmt_doctor->fetch(PDO::FETCH_ASSOC);

    if ($doctor_data) {
        $doctor_id = $doctor_data['id'];

        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (isset($_POST['action']) && isset($_POST['invitation_id'])) {
                $invitation_id = intval($_POST['invitation_id']);
                $action = htmlspecialchars($_POST['action']);

                // S'assurer que l'invitation appartient à ce médecin et est en attente
                $stmt_check_invitation = $pdo->prepare("
                    SELECT clinic_id, invitation_status FROM clinic_doctor_invitations
                    WHERE id = :invitation_id AND doctor_id = :doctor_id AND invitation_status = 'pending'
                ");
                $stmt_check_invitation->execute([
                    ':invitation_id' => $invitation_id,
                    ':doctor_id' => $doctor_id
                ]);
                $invitation_to_process = $stmt_check_invitation->fetch(PDO::FETCH_ASSOC);

                if ($invitation_to_process) {
                    $clinic_id = $invitation_to_process['clinic_id'];
                    $pdo->beginTransaction(); // Commencer la transaction pour l'atomicité

                    try {
                        if ($action == 'accept') {
                            // Mettre à jour le statut de l'invitation
                            $stmt_update_invitation = $pdo->prepare("
                                UPDATE clinic_doctor_invitations SET invitation_status = 'accepted', accepted_at = NOW()
                                WHERE id = :invitation_id AND doctor_id = :doctor_id
                            ");
                            $stmt_update_invitation->execute([
                                ':invitation_id' => $invitation_id,
                                ':doctor_id' => $doctor_id
                            ]);

                            // Ajouter l'entrée dans la table clinic_doctors
                            $stmt_add_to_clinic_doctors = $pdo->prepare("
                                INSERT INTO clinic_doctors (clinic_id, doctor_id)
                                VALUES (:clinic_id, :doctor_id)
                            ");
                            $stmt_add_to_clinic_doctors->execute([
                                ':clinic_id' => $clinic_id,
                                ':doctor_id' => $doctor_id
                            ]);

                            $pdo->commit();
                            $message = "Invitation acceptée avec succès ! Vous êtes maintenant associé à cette clinique.";
                            $message_type = 'success';

                        } elseif ($action == 'decline') {
                            // Mettre à jour le statut de l'invitation
                            $stmt_update_invitation = $pdo->prepare("
                                UPDATE clinic_doctor_invitations SET invitation_status = 'declined', declined_at = NOW()
                                WHERE id = :invitation_id AND doctor_id = :doctor_id
                            ");
                            $stmt_update_invitation->execute([
                                ':invitation_id' => $invitation_id,
                                ':doctor_id' => $doctor_id
                            ]);
                            $pdo->commit();
                            $message = "Invitation refusée.";
                            $message_type = 'success';
                        }
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        error_log("Erreur de transaction dans manage_invitations.php: " . $e->getMessage());
                        $message = "Une erreur est survenue lors de l'action. Veuillez réessayer.";
                        $message_type = 'error';
                    }

                } else {
                    $message = "Invitation invalide ou déjà traitée.";
                    $message_type = 'error';
                }
            }
        }

        // Récupérer les invitations pour le médecin
        $stmt_invitations = $pdo->prepare("
            SELECT cdi.id, c.name AS clinic_name, cdi.invitation_status, cdi.invited_at
            FROM clinic_doctor_invitations cdi
            JOIN clinics c ON cdi.clinic_id = c.id
            WHERE cdi.doctor_id = :doctor_id
            ORDER BY cdi.invited_at DESC
        ");
        $stmt_invitations->execute([':doctor_id' => $doctor_id]);
        $doctor_invitations = $stmt_invitations->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $message = "Profil de médecin introuvable pour votre ID utilisateur. Veuillez compléter votre profil.";
        $message_type = 'error';
    }

} catch (PDOException $e) {
    error_log("Erreur dans manage_invitations.php: " . $e->getMessage());
    $message = "Une erreur inattendue est survenue. Veuillez réessayer.";
    $message_type = 'error';
}

?>