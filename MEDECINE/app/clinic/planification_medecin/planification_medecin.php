<?php
include_once "../../../includes/clinic/medecin_planification_funct.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les Médecins et Plannings</title>
    <link rel="stylesheet" href="../../../public/css/clinic/medecin_planification.css">

</head>
<body>
    <div class="container">
        <h1>Gérer les Médecins de votre Clinique</h1>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($clinic_id === null): ?>
            <div class="no-doctors">
                <p>Vous n'êtes pas associé à une clinique. Veuillez contacter l'administrateur.</p>
            </div>
        <?php elseif (empty($clinic_doctors)): ?>
            <div class="no-doctors">
                <p>Aucun médecin n'est encore associé à votre clinique.</p>
                </div>
        <?php else: ?>
            <h2>Liste des Médecins</h2>
            <div class="doctors-list">
                <?php foreach ($clinic_doctors as $doctor): ?>
                    <div class="doctor-list-item">
                        <div>
                            <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                            <p>Spécialités: <?php echo htmlspecialchars($doctor['doctor_specialties'] ?: 'Non spécifié'); ?></p>
                        </div>
                        <a href="manage_doctor_schedule.php?doctor_id=<?php echo $doctor['doctor_id']; ?>&clinic_id=<?php echo $clinic_id; ?>" class="action-button">Gérer les horaires</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="../dashboard_clinic.php" class="nav-link-back">Retour au Tableau de Bord</a>
    </div>
</body>
</html>