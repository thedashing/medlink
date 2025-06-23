<?php
require_once '../../includes/patient/rendezvous_function.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/patient/rendezvous.css">
</head>
<body>
    <div class="container">
        <div class="header-main">
            <h1>Mes Rendez-vous (<?php echo $user_email; ?>)</h1>
            <nav>
                <a href="dashboard_patient.php">Tableau de Bord</a>
                <a href="search.php">Prendre un Rendez-vous</a>
                <a href="../securite/logout.php">Se déconnecter</a>
            </nav>
        </div>

        <?php if (!empty($message)): ?>
            <div class="system-message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title">Rendez-vous à venir</h2>
        <?php if (empty($upcoming_appointments)): ?>
            <div class="no-appointments">
                <p>Vous n'avez aucun rendez-vous à venir. <a href="../../search.php">Rechercher un médecin pour prendre rendez-vous</a>.</p>
            </div>
        <?php else: ?>
            <?php foreach ($upcoming_appointments as $appointment): ?>
                <?php
                    // Calculate time difference for cancellation policy (24 hours)
                    $appointment_timestamp = strtotime($appointment['appointment_datetime']);
                    $current_timestamp = time();
                    $time_diff_hours = ($appointment_timestamp - $current_timestamp) / 3600; // Difference in hours

                    $can_cancel = ($time_diff_hours > 24);
                ?>
                <div class="appointment-card upcoming">
                    <div class="appointment-details">
                        <div class="appointment-info">
                            <h3>Rendez-vous avec Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h3>
                            <p><strong>Service :</strong> <?php echo htmlspecialchars($appointment['service_name']); ?> (<?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?> FCFA)</p>
                            <p><strong>Quand :</strong> <?php echo date('d/m/Y à H:i', strtotime($appointment['appointment_datetime'])); ?> - <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                            <p><strong>Où :</strong> <?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                        </div>
                        <div class="appointment-status-display status-<?php echo strtolower($appointment['status']); ?>">
                            Statut: <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                        </div>
                    </div>
                    <div class="appointment-actions">
                        <a href="reschedule_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" 
                           class="action-button reschedule <?php echo !$can_cancel ? 'disabled' : ''; ?>"
                           <?php echo !$can_cancel ? 'title="Modification non autorisée moins de 24h avant le rendez-vous." onclick="return false;"' : ''; ?>>
                            Reporter
                        </a>
                        <a href="../../includes/patient/cancel_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" 
                           class="action-button <?php echo !$can_cancel ? 'disabled' : ''; ?>" 
                           onclick="return <?php echo $can_cancel ? "confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')" : "false"; ?>;"
                           <?php echo !$can_cancel ? 'title="Annulation non autorisée moins de 24h avant le rendez-vous." onclick="return false;"' : ''; ?>>
                            Annuler
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        ---

        <h2 class="section-title">Rendez-vous Annulés</h2>
        <?php if (empty($cancelled_appointments)): ?>
            <div class="no-appointments">
                <p>Vous n'avez aucun rendez-vous annulé.</p>
            </div>
        <?php else: ?>
            <?php foreach ($cancelled_appointments as $appointment): ?>
                <div class="appointment-card cancelled">
                    <div class="appointment-details">
                        <div class="appointment-info">
                            <h3>Rendez-vous avec Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h3>
                            <p><strong>Service :</strong> <?php echo htmlspecialchars($appointment['service_name']); ?> (<?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?> FCFA)</p>
                            <p><strong>Quand :</strong> <?php echo date('d/m/Y à H:i', strtotime($appointment['appointment_datetime'])); ?> - <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                            <p><strong>Où :</strong> <?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                        </div>
                        <div class="appointment-status-display status-cancelled">
                            Statut: Annulé
                        </div>
                    </div>
                    <div class="appointment-actions">
                        </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        ---
       

        <h2 class="section-title">Historique des Rendez-vous</h2>
        <?php if (empty($past_completed_appointments)): ?>
            <div class="no-appointments">
                <p>Aucun rendez-vous passé n'a été trouvé dans votre historique.</p>
            </div>
        <?php else: ?>
            <?php foreach ($past_completed_appointments as $appointment): ?>
                
                <div class="appointment-card past">
                    <div class="appointment-details">
                        <div class="appointment-info">
                            <h3>Rendez-vous avec Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h3>
                            <p><strong>Service :</strong> <?php echo htmlspecialchars($appointment['service_name']); ?> (<?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?> FCFA)</p>
                            <p><strong>Quand :</strong> <?php echo date('d/m/Y à H:i', strtotime($appointment['appointment_datetime'])); ?> - <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                            <p><strong>Où :</strong> <?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                        </div>
                        <div class="appointment-status-display status-<?php echo strtolower($appointment['status']); ?>">
                            Statut: <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                        </div>
                    </div>
                    <div class="appointment-actions">
                        </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>