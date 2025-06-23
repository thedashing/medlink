<?php
include_once "../../includes/medecin/manage_invitation_fonct.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les Invitations de Cliniques</title>
    <link rel="stylesheet" href="../../public/css/medecin/invitation_doctor.css">

</head>
<body>
    <div class="container">
        <h1>Gérer mes Invitations de Cliniques</h1>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($doctor_id): ?>
            <?php if (empty($doctor_invitations)): ?>
                <div class="no-entries">
                    <p>Vous n'avez pas d'invitations de cliniques pour le moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($doctor_invitations as $invitation): ?>
                    <div class="invitation-item">
                        <div>
                            <strong>Clinique : <?php echo htmlspecialchars($invitation['clinic_name']); ?></strong><br>
                            <span>Envoyée le : <?php echo date('d/m/Y H:i', strtotime($invitation['invited_at'])); ?></span>
                        </div>
                        <div class="actions">
                            <?php if ($invitation['invitation_status'] == 'pending'): ?>
                                <form style="display:inline;" action="" method="POST">
                                    <input type="hidden" name="invitation_id" value="<?php echo $invitation['id']; ?>">
                                    <button type="submit" name="action" value="accept" class="accept">Accepter</button>
                                </form>
                                <form style="display:inline;" action="" method="POST">
                                    <input type="hidden" name="invitation_id" value="<?php echo $invitation['id']; ?>">
                                    <button type="submit" name="action" value="decline" class="decline">Refuser</button>
                                </form>
                            <?php else: ?>
                                <span class="status-<?php echo htmlspecialchars($invitation['invitation_status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($invitation['invitation_status'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php else: ?>
            <div class="message error">
                <p>Votre profil de médecin n'est pas complet. Veuillez contacter l'administrateur.</p>
            </div>
        <?php endif; ?>

        <a href="dashboard_doctor.php" class="nav-link-back">Retour au Tableau de Bord Médecin</a>
    </div>
</body>
</html>