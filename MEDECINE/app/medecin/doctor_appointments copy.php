<?php
// Fichier : MEDECINE/doctor_appointments.php



date_default_timezone_set('Africa/Ouagadougou');
require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';

// Seuls les médecins peuvent accéder à cette page
require_login('doctor');

$user_id = get_user_id(); // L'ID de l'utilisateur connecté (qui est un médecin)
$doctor_id = null; // L'ID du docteur correspondant à cet utilisateur
$appointments = [];
$message = '';
$message_type = '';

// Récupérer l'ID du docteur lié à cet utilisateur
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $stmt_doctor_id = $pdo->prepare("SELECT id FROM doctors WHERE user_id = :user_id");
    $stmt_doctor_id->execute([':user_id' => $user_id]);
    $doctor_info = $stmt_doctor_id->fetch(PDO::FETCH_ASSOC);

    if ($doctor_info) {
        $doctor_id = $doctor_info['id'];
    } else {
        throw new Exception("Aucun profil de docteur trouvé pour votre compte.");
    }

    // Traitement des actions (confirmer, annuler, terminer, payer) - Déplacé avant la récupération des RDV pour un rechargement propre
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? '';
        $appointment_id_to_act = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);

        if ($appointment_id_to_act) {
            $pdo->beginTransaction();
            try {
                // Initialise $stmt_update pour éviter l'erreur si non défini pour certains cas
                $stmt_update = null;
                $stmt_insert_record = null; // Pour le cas 'complete_and_record'

                switch ($action) {
                    case 'confirm':
                        $new_status = 'confirmed';
                        $success_msg = 'Rendez-vous confirmé avec succès.';
                        $stmt_update = $pdo->prepare("UPDATE appointments SET status = :new_status WHERE id = :appointment_id AND doctor_id = :doctor_id");
                        $stmt_update->execute([
                            ':new_status' => $new_status,
                            ':appointment_id' => $appointment_id_to_act,
                            ':doctor_id' => $doctor_id
                        ]);
                        break;
                    case 'cancel':
                        $new_status = 'cancelled';
                        $success_msg = 'Rendez-vous annulé avec succès.';
                        $stmt_update = $pdo->prepare("UPDATE appointments SET status = :new_status WHERE id = :appointment_id AND doctor_id = :doctor_id");
                        $stmt_update->execute([
                            ':new_status' => $new_status,
                            ':appointment_id' => $appointment_id_to_act,
                            ':doctor_id' => $doctor_id
                        ]);
                        break;
                    case 'complete_and_record':
                        $new_status = 'completed';
                        $success_msg = 'Rendez-vous marqué comme terminé et dossier médical enregistré.';

                        // Récupérer les infos du patient pour le dossier médical
                        $stmt_get_patient_id = $pdo->prepare("SELECT patient_id FROM appointments WHERE id = :appointment_id");
                        $stmt_get_patient_id->execute([':appointment_id' => $appointment_id_to_act]);
                        $patient_info = $stmt_get_patient_id->fetch(PDO::FETCH_ASSOC);
                        $patient_id_for_record = $patient_info['patient_id'] ?? null;

                        if (!$patient_id_for_record) {
                            throw new Exception("Impossible de trouver le patient pour ce rendez-vous.");
                        }

                        // Récupérer les données du formulaire de la modale
                        $diagnosis = htmlspecialchars(trim($_POST['diagnosis'] ?? ''));
                        $treatment = htmlspecialchars(trim($_POST['treatment'] ?? ''));
                        $notes = htmlspecialchars(trim($_POST['notes'] ?? ''));

                        // Insérer le dossier médical
                        $stmt_insert_record = $pdo->prepare("INSERT INTO medical_records (appointment_id, patient_id, doctor_id, diagnosis, treatment, notes) VALUES (:appointment_id, :patient_id, :doctor_id, :diagnosis, :treatment, :notes)");
                        $stmt_insert_record->execute([
                            ':appointment_id' => $appointment_id_to_act,
                            ':patient_id' => $patient_id_for_record,
                            ':doctor_id' => $doctor_id,
                            ':diagnosis' => $diagnosis,
                            ':treatment' => $treatment,
                            ':notes' => $notes
                        ]);

                        // Mettre à jour le statut du rendez-vous
                        $stmt_update = $pdo->prepare("UPDATE appointments SET status = :new_status WHERE id = :appointment_id AND doctor_id = :doctor_id");
                        $stmt_update->execute([
                            ':new_status' => $new_status,
                            ':appointment_id' => $appointment_id_to_act,
                            ':doctor_id' => $doctor_id
                        ]);
                        break;
                    case 'mark_as_paid': // NOUVEAU CAS POUR LE PAIEMENT
                        $success_msg = 'Paiement enregistré avec succès.';
                        $stmt_update = $pdo->prepare("UPDATE appointments SET payment_status = 'paid' WHERE id = :appointment_id AND doctor_id = :doctor_id");
                        $stmt_update->execute([
                            ':appointment_id' => $appointment_id_to_act,
                            ':doctor_id' => $doctor_id
                        ]);
                        break;
                    default:
                        $message = "Action invalide.";
                        $message_type = 'error';
                        break;
                }

                // Vérifiez si $stmt_update a été exécuté et a affecté des lignes
                // Ou si c'est l'action 'complete_and_record' et l'insertion a eu lieu
                if (($stmt_update && $stmt_update->rowCount() > 0) || ($action === 'complete_and_record' && $stmt_insert_record && $stmt_insert_record->rowCount() > 0)) {
                    $pdo->commit();
                    $message = $success_msg;
                    $message_type = 'success';
                    header('Location: doctor_appointments.php?message=' . urlencode($message) . '&type=' . $message_type);
                    exit();
                } else {
                    $pdo->rollBack();
                    $message = "Impossible de mettre à jour le rendez-vous. Peut-être qu'il est déjà dans cet état ou n'existe pas.";
                    $message_type = 'error';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = "Erreur lors de l'action: " . $e->getMessage();
                $message_type = 'error';
                error_log("Transaction error: " . $e->getMessage());
            }
        }
    }


    // Récupérer les rendez-vous
    $stmt_appointments = $pdo->prepare("SELECT
                                            a.id AS appointment_id,
                                            a.appointment_datetime,
                                            a.end_datetime,
                                            a.status,
                                            a.payment_status,
                                            p.id AS patient_db_id,
                                            p.first_name AS patient_first_name,
                                            p.last_name AS patient_last_name,
                                            u.email AS patient_email,
                                            cl.name AS clinic_name,
                                            cl.address AS clinic_address,
                                            s.name AS service_name,
                                            s.price AS service_price
                                        FROM
                                            appointments a
                                        JOIN
                                            patients p ON a.patient_id = p.id
                                        JOIN
                                            users u ON p.user_id = u.id
                                        JOIN
                                            clinics cl ON a.clinic_id = cl.id
                                        JOIN
                                            services s ON a.service_id = s.id
                                        WHERE
                                            a.doctor_id = :doctor_id
                                        ORDER BY
                                            a.appointment_datetime ASC");


    $stmt_appointments->execute([':doctor_id' => $doctor_id]);
    $appointments = $stmt_appointments->fetchAll(PDO::FETCH_ASSOC);


} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    error_log("Doctor appointments error: " . $e->getMessage());
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
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous - Dr. <?php echo htmlspecialchars($_SESSION['user_email']); ?></title>
   
</head>
<body>
    <div class="container">
        <a href="app/medecin/dashboard_doctor.php" class="back-to-dashboard">&larr; Retour au Tableau de Bord</a>
        <h1>Mes Rendez-vous</h1>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($appointments)): ?>
            <div class="no-appointments">
                <p>Vous n'avez aucun rendez-vous pour le moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($appointments_by_date as $date_str => $day_appointments): ?>
                <div class="appointment-day">
                    <h2>Rendez-vous du <?php echo date('d/m/Y', strtotime($date_str)); ?></h2>
                    <?php foreach ($day_appointments as $appointment): ?>
                        <div class="appointment-card <?php echo htmlspecialchars($appointment['status']); ?>">
                            <div class="appointment-info">
                                <h3>Patient: <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></h3>
                                <p><strong>Email Patient:</strong> <?php echo htmlspecialchars($appointment['patient_email']); ?></p>
                                <p><strong>Service:</strong> <?php echo htmlspecialchars($appointment['service_name']); ?> (<?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?> €)</p>
                                <p><strong>Heure:</strong> <?php echo date('H:i', strtotime($appointment['appointment_datetime'])); ?> - <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                                <p><strong>Clinique:</strong> <?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                                <p><strong>Statut:</strong> <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></p>
                                <p><strong>Paiement:</strong> <?php echo htmlspecialchars(ucfirst($appointment['payment_status'])); ?></p>
                            </div>
                            <div class="appointment-actions">
                                <?php if ($appointment['status'] === 'pending'): ?>
                                    <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Confirmer ce rendez-vous ?');">
                                        <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($appointment['appointment_id']); ?>">
                                        <input type="hidden" name="action" value="confirm">
                                        <button type="submit" class="btn-confirm">Confirmer</button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($appointment['status'] !== 'cancelled' && $appointment['status'] !== 'completed'): ?>
                                    <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Annuler ce rendez-vous ?');">
                                        <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($appointment['appointment_id']); ?>">
                                        <input type="hidden" name="action" value="cancel">
                                        <button type="submit" class="btn-cancel">Annuler</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($appointment['payment_status'] === 'unpaid' && ($appointment['status'] === 'confirmed' || $appointment['status'] === 'completed')): ?>
                                    <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Marquer ce rendez-vous comme payé ?');">
                                        <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($appointment['appointment_id']); ?>">
                                        <input type="hidden" name="action" value="mark_as_paid">
                                        <button type="submit" class="btn-pay">Marquer Payé</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($appointment['status'] === 'confirmed' || $appointment['status'] === 'pending'): ?>
                                    <button type="button" class="btn-complete open-modal-btn"
                                            data-appointment-id="<?php echo htmlspecialchars($appointment['appointment_id']); ?>"
                                            data-patient-name="<?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>"
                                            data-patient-id="<?php echo htmlspecialchars($appointment['patient_db_id']); ?>">
                                        Terminer et Dossier
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div id="medicalRecordModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Dossier Médical pour <span id="patientNameInModal"></span></h2>
            <form id="medicalRecordForm" action="" method="POST">
                <input type="hidden" name="appointment_id" id="modalAppointmentId">
                <input type="hidden" name="action" value="complete_and_record">

                <label for="diagnosis">Diagnostic:</label>
                <textarea id="diagnosis" name="diagnosis" rows="5" required></textarea>

                <label for="treatment">Traitement:</label>
                <textarea id="treatment" name="treatment" rows="5"></textarea>

                <label for="notes">Notes supplémentaires:</label>
                <textarea id="notes" name="notes" rows="5"></textarea>

                <button type="submit">Enregistrer le Dossier et Terminer</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('medicalRecordModal');
            const closeButton = document.querySelector('.close-button');
            const openModalButtons = document.querySelectorAll('.open-modal-btn');
            const modalAppointmentId = document.getElementById('modalAppointmentId');
            const patientNameInModal = document.getElementById('patientNameInModal');

            // Quand on clique sur le bouton "Terminer et Dossier"
            openModalButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const appointmentId = this.dataset.appointmentId;
                    const patientName = this.dataset.patientName;
                    const patientId = this.dataset.patientId; // Vous pouvez aussi passer le patient_id si nécessaire pour le formulaire

                    modalAppointmentId.value = appointmentId;
                    patientNameInModal.textContent = patientName;
                    modal.style.display = 'flex'; // Utiliser flex pour centrer
                });
            });

            // Quand on clique sur le <span> (x), fermer la modale
            closeButton.addEventListener('click', function() {
                modal.style.display = 'none';
                document.getElementById('medicalRecordForm').reset(); // Réinitialiser le formulaire
            });

            // Quand l'utilisateur clique n'importe où en dehors de la modale, la fermer
            window.addEventListener('click', function(event) {
                if (event.target == modal) {
                    modal.style.display = 'none';
                    document.getElementById('medicalRecordForm').reset(); // Réinitialiser le formulaire
                }
            });
        });
    </script>
</body>
</html>