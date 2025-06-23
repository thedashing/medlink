<?php
include_once "../../includes/medecin/doctor_appointment_fonct.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous - Dr. <?php echo htmlspecialchars($_SESSION['user_email']); ?></title>
    <link rel="stylesheet" href="../../public/css/medecin/doctor_appointment.css">

</head>
<body>
    <div class="container">
        <a href="../../dashboard_doctor.php" class="back-to-dashboard">&larr; Retour au Tableau de Bord</a>
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