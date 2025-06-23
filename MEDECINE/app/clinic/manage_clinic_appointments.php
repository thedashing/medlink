<?php
include_once "../../includes/clinic/manage_clinic_app_funct.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les Rendez-vous de la Clinique - <?php echo htmlspecialchars($_SESSION['user_email']); ?></title>
     <link rel="stylesheet" href="../../public/css/clinic/manage_clinic_app.css">

</head>
<body>
    <div class="container">
        <a href="dashboard_clinic.php" class="back-to-dashboard">&larr; Retour au Tableau de Bord</a>
        <h1>Gestion des Rendez-vous de la Clinique</h1>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (empty($appointments)): ?>
            <div class="no-appointments">
                <p>Aucun rendez-vous pour votre clinique pour le moment.</p>
            </div>
        <?php else: ?>
            <?php foreach ($appointments_by_date as $date => $day_appointments): ?>
                <div class="appointment-day">
                    <h2>Rendez-vous du <?php echo date('d/m/Y', strtotime($date)); ?></h2>
                    <?php foreach ($day_appointments as $appointment): ?>
                        <div class="appointment-card <?php echo htmlspecialchars($appointment['status']); ?>">
                            <div class="appointment-info">
                                <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?> avec Patient: <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?></h3>
                                <p><strong>Email Patient:</strong> <?php echo htmlspecialchars($appointment['patient_email']); ?></p>
                                <p><strong>Service:</strong> <?php echo htmlspecialchars($appointment['service_name']); ?> (<?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?> €)</p>
                                <p><strong>Heure:</strong> <?php echo date('H:i', strtotime($appointment['appointment_datetime'])); ?> - <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
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
                                <?php if ($appointment['status'] === 'confirmed' || $appointment['status'] === 'pending'): ?>
                                    <form action="" method="POST" style="display:inline;" onsubmit="return confirm('Marquer comme terminé ?');">
                                        <input type="hidden" name="appointment_id" value="<?php echo htmlspecialchars($appointment['appointment_id']); ?>">
                                        <input type="hidden" name="action" value="complete">
                                        <button type="submit" class="btn-complete">Terminer</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>