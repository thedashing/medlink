<?php
require_once '../../includes/patient/dash_patient_function.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Patient - <?php echo $user_email; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/patient/dash_patient.css">
</head>
<body>
    <div class="container">
        <div class="header-patient">
            <h1>Bienvenue, <?php echo $user_email; ?> (Patient) !</h1>
            <nav>  
                <a href="search.php">Rechercher un Médecin</a>
                <a href="mes_rendez_vous.php">Mes rendez-vous</a>
                <a href="mon_dossier.php">Mon dossier médical</a>
                <a href="messagerie.php">
                    Messagerie
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="message-badge"><?php echo $unread_messages_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="../securite/logout.php">Se déconnecter</a>
            </nav>
        </div>

        <?php if (!empty($message)): ?>
            <div class="system-message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <h2 class="section-title">Mes Prochains Rendez-vous</h2>
        <?php if (empty($upcoming_appointments)): ?>
            <div class="no-appointments">
                <p>Vous n'avez aucun rendez-vous à venir. <a href="../../search.php">Rechercher un médecin pour prendre rendez-vous</a>.</p>
            </div>
        <?php else: ?>
            <?php foreach ($upcoming_appointments as $appointment): ?>
                <div class="appointment-card upcoming">
                    <div class="appointment-details">
                        <div class="appointment-info">
                            <h3>Rendez-vous avec Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h3>
                            <p><strong>Service:</strong> <?php echo htmlspecialchars($appointment['service_name']); ?> (<?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?> FCFA)</p>
                            <p><strong>Quand:</strong> <?php echo date('d/m/Y à H:i', strtotime($appointment['appointment_datetime'])); ?> - <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                            <p><strong>Où:</strong> <?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                        </div>
                        <div class="appointment-status status-<?php echo strtolower($appointment['status']); ?>">
                            Statut: <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                        </div>
                    </div>
                    <div class="appointment-actions">
                        <?php if ($appointment['status'] !== 'cancelled' && $appointment['status'] !== 'completed'): // Ne pas afficher si annulé ou terminé ?>
                            <a href="reschedule_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="action-button reschedule">Reporter</a>
                            <a href="../../includes/patient/cancel_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="action-button" onclick="return confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?');">Annuler</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <h2 class="section-title">Historique de mes Rendez-vous</h2>
        <?php if (empty($past_appointments)): ?>
            <div class="no-appointments">
                <p>Aucun rendez-vous passé ou annulé.</p>
            </div>
        <?php else: ?>
            <?php foreach ($past_appointments as $appointment): ?>
                <div class="appointment-card past <?php echo ($appointment['status'] === 'cancelled') ? 'cancelled' : ''; ?>">
                    <div class="appointment-details">
                        <div class="appointment-info">
                            <h3>Rendez-vous avec Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h3>
                            <p><strong>Service:</strong> <?php echo htmlspecialchars($appointment['service_name']); ?> (<?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?> FCFA)</p>
                            <p><strong>Quand:</strong> <?php echo date('d/m/Y à H:i', strtotime($appointment['appointment_datetime'])); ?> - <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                            <p><strong>Où:</strong> <?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                        </div>
                        <div class="appointment-status status-<?php echo strtolower($appointment['status']); ?>">
                            Statut: <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?>
                        </div>
                    </div>
                    <div class="appointment-actions">
                         <?php if ($appointment['status'] === 'completed'): ?>
                            <span class="action-button" style="background-color: #6c757d; cursor: default;">Terminé</span>
                        <?php elseif ($appointment['status'] === 'cancelled'): ?>
                            <span class="action-button" style="background-color: var(--danger-color); cursor: default;">Annulé</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>