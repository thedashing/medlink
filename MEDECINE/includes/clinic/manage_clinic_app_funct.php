<?php
// Fichier : MEDECINE/manage_clinic_appointments.php


require_once '../../includes/Database.php';
require_once '../../includes/auth_check.php';

// Seules les cliniques peuvent accéder à cette page
require_login('clinic');

$user_id = get_user_id(); // L'ID de l'utilisateur connecté (qui est une clinique)
$clinic_id = null; // L'ID de la clinique correspondant à cet utilisateur
$appointments = [];
$message = '';
$message_type = '';

// Récupérer l'ID de la clinique liée à cet utilisateur
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $stmt_clinic_id = $pdo->prepare("SELECT id FROM clinics WHERE user_id = :user_id");
    $stmt_clinic_id->execute([':user_id' => $user_id]);
    $clinic_info = $stmt_clinic_id->fetch(PDO::FETCH_ASSOC);

    if ($clinic_info) {
        $clinic_id = $clinic_info['id'];
    } else {
        throw new Exception("Aucun profil de clinique trouvé pour votre compte.");
    }

    // Récupérer tous les rendez-vous pour cette clinique (pour tous les médecins associés)
  $stmt_appointments = $pdo->prepare("SELECT
                                        a.id AS appointment_id,
                                        a.appointment_datetime,
                                        a.end_datetime,
                                        a.status,
                                        a.payment_status,
                                        p.first_name AS patient_first_name,
                                        p.last_name AS patient_last_name,
                                        u.email AS patient_email, -- <--- MODIFICATION ICI : 'u.email' au lieu de 'p.email'
                                        d.first_name AS doctor_first_name,
                                        d.last_name AS doctor_last_name,
                                        s.name AS service_name,
                                        s.price AS service_price
                                    FROM
                                        appointments a
                                    JOIN
                                        patients p ON a.patient_id = p.id
                                    JOIN
                                        users u ON p.user_id = u.id -- <--- NOUVELLE JOINTURE ICI
                                    JOIN
                                        doctors d ON a.doctor_id = d.id
                                    JOIN
                                        services s ON a.service_id = s.id
                                    WHERE
                                        a.clinic_id = :clinic_id
                                    ORDER BY
                                        a.appointment_datetime ASC");

    $stmt_appointments->execute([':clinic_id' => $clinic_id]);
    $appointments = $stmt_appointments->fetchAll(PDO::FETCH_ASSOC);

    // Traitement des actions (confirmer, annuler, terminer)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? '';
        $appointment_id_to_act = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);

        if ($appointment_id_to_act) {
            $new_status = '';
            $success_msg = '';

            switch ($action) {
                case 'confirm':
                    $new_status = 'confirmed';
                    $success_msg = 'Rendez-vous confirmé avec succès.';
                    break;
                case 'cancel':
                    $new_status = 'cancelled';
                    $success_msg = 'Rendez-vous annulé avec succès.';
                    break;
                case 'complete':
                    $new_status = 'completed';
                    $success_msg = 'Rendez-vous marqué comme terminé.';
                    break;
                default:
                    $message = "Action invalide.";
                    $message_type = 'error';
                    break;
            }

            if ($new_status) {
                $pdo->beginTransaction();
                $stmt_update = $pdo->prepare("UPDATE appointments SET status = :new_status WHERE id = :appointment_id AND clinic_id = :clinic_id");
                $stmt_update->execute([
                    ':new_status' => $new_status,
                    ':appointment_id' => $appointment_id_to_act,
                    ':clinic_id' => $clinic_id // Sécurité: S'assurer que la clinique ne modifie que les RDV de sa clinique
                ]);

                if ($stmt_update->rowCount() > 0) {
                    $pdo->commit();
                    $message = $success_msg;
                    $message_type = 'success';
                    // Recharger la page pour afficher les changements
                    header('Location: manage_clinic_appointments.php?message=' . urlencode($message) . '&type=' . $message_type);
                    exit();
                } else {
                    $pdo->rollBack();
                    $message = "Impossible de mettre à jour le rendez-vous. Peut-être qu'il est déjà dans cet état ou n'existe pas.";
                    $message_type = 'error';
                }
            }
        }
    }

} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    error_log("Clinic appointments error: " . $e->getMessage());
}

// Récupérer les messages des paramètres GET
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars(urldecode($_GET['message']));
    $message_type = htmlspecialchars($_GET['type']);
}

// Organiser les rendez-vous par date pour un affichage plus clair
$appointments_by_date = [];
foreach ($appointments as $appt) {
    $date = date('Y-m-d', strtotime($appt['appointment_datetime']));
    if (!isset($appointments_by_date[$date])) {
        $appointments_by_date[$date] = [];
    }
    $appointments_by_date[$date][] = $appt;
}
ksort($appointments_by_date); // Trier par date

?>