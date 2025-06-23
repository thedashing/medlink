<?php
include_once "../../includes/clinic/clinic_invite_funct.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inviter un Médecin - <?php echo $clinic_name; ?></title>
    <link rel="stylesheet" href="../../public/css/clinic/clinic_invite.css">

</head>
<body>
    <div class="container">
        <?php if ($clinic_id): ?>
            <h1>Inviter un Médecin pour <?php echo $clinic_name; ?></h1>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <hr>

            <h2>Envoyer une nouvelle invitation</h2>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="doctor_email">Email du médecin à inviter :</label>
                    <input type="email" id="doctor_email" name="doctor_email" required placeholder="medecin@example.com">
                </div>
                <button type="submit">Envoyer l'invitation</button>
            </form>

            <hr>

            <h2>Invitations envoyées</h2>
            <?php if (empty($current_invitations)): ?>
                <div class="no-entries">
                    <p>Aucune invitation n'a été envoyée par votre clinique pour le moment.</p>
                </div>
            <?php else: ?>
                <div class="invitation-list">
                    <?php foreach ($current_invitations as $invitation): ?>
                        <div class="invitation-item">
                            <div>
                                <strong><?php echo htmlspecialchars($invitation['first_name'] . ' ' . $invitation['last_name']); ?></strong> (<?php echo htmlspecialchars($invitation['email']); ?>)<br>
                                <span>Envoyée le : <?php echo date('d/m/Y H:i', strtotime($invitation['invited_at'])); ?></span>
                            </div>
                            <span class="status-<?php echo htmlspecialchars($invitation['invitation_status']); ?>">
                                <?php echo ucfirst(htmlspecialchars($invitation['invitation_status'])); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="message error">
                <p>Impossible de charger les informations de la clinique. Assurez-vous d'être correctement connecté.</p>
            </div>
        <?php endif; ?>

        <a href="dashboard_clinic.php" class="nav-link-back">Retour au Tableau de Bord Clinique</a>
    </div>
</body>
</html>