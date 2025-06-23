<?php
include_once "../../includes/medecin/dash_doctor_fonct.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Médecin</title>
    <link rel="stylesheet" href="../../public/css/medecin/dash_medecin.css">
</head>
<body>
    <div class="header">
        <h1>Bienvenue, <?php echo $doctor_name; ?> (Médecin) !</h1>
        <a href="../securite/logout.php">Se déconnecter</a>
    </div>
    <div class="content">
        <p>Ceci est votre tableau de bord médecin. Ici, vous pourrez :</p>
        <ul>
            <li>Gérer vos plannings multi-cliniques.</li>
            <li>Mettre à jour votre profil professionnel.</li>
            <li>Consulter vos rendez-vous.</li>
            <li>Gérer vos créneaux.</li>
        </ul>
        <p>Votre ID utilisateur est : <?php echo $user_id; ?></p>
        <p>Votre ID docteur est : <?php echo $doctor_db_id; ?></p>
        <div class="nav-buttons">
            <a href="manage_schedules.php">Gérer mes Plannings</a>
            <a href="doctor_appointments.php">Voir mes Rendez-vous</a>
            <a href="manage_invitations.php">Mes invitations</a>
            <a href="messagerie.php">
                Messagerie
                <?php if ($unread_messages_count > 0): ?>
                    <span class="unread-badge"><?php echo $unread_messages_count; ?></span>
                <?php endif; ?>
            </a>
            </div>
    </div>
</body>
</html>