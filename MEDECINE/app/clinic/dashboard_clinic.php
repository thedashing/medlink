<?php
include_once "../../includes/clinic/dash_clinic_funct.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Clinique</title>
        <link rel="stylesheet" href="../../public/css/clinic/dash_clinic.css">

</head>
<body>
    <div class="header">
        <h1>Bienvenue, <?php echo $clinic_name; ?> (Clinique) !</h1>
        <a href="../../securite/logout.php">Se déconnecter</a>
    </div>
    <div class="content">
        <p>Ceci est votre tableau de bord clinique. Ici, vous pourrez :</p>
        <ul>
            <li>Gérer le planning médical.</li>
            <li>Suivre les réservations.</li>
            <li>Consulter les statistiques (occupation, spécialités demandées).</li>
            <li>Paramétrer les services, créneaux, tarifs.</li>
        </ul>
        <p>Votre ID utilisateur est : <?php echo $user_id; ?></p>

        <div class="nav-buttons">
            <a href="manage_clinic_appointments.php">Gérer les Rendez-vous</a>
            <a href="planification_medecin/planification_medecin.php">gerer les medecin</a>
            <a href="medecinSpecialite_prixhtml.php">Specialite et service</a>
            <a href="invite_doctor.php">inviter Medecin</a>
            </div>
    </div>
</body>
</html>