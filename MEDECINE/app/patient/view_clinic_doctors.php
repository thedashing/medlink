<?php
require_once '../../includes/patient/voir_doctor_clinic_fonction.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $clinic_info ? htmlspecialchars($clinic_info['name']) : 'Détails de la Clinique'; ?></title>
    <link rel="stylesheet" href="../../public/css/patient/voir_doctor_clinic.css">

</head>
<body>
    <div class="container">
        <div class="nav-links">
            <?php // Assurez-vous que get_user_email() et get_user_role() sont définies dans auth_check.php ou inclus ailleurs
            if (function_exists('is_logged_in') && is_logged_in()): ?>
                <p>Connecté en tant que: <?php echo htmlspecialchars(get_user_email()); ?> (<?php echo htmlspecialchars(get_user_role()); ?>)</p>
                <a href="dashboard_<?php echo htmlspecialchars(get_user_role()); ?>.php">Mon Tableau de Bord</a> |
                <a href="../../securite/logout.php">Se déconnecter</a>
            <?php else: ?>
                <p>Non connecté.</p>
                <a href="../../securite/login.php">Se connecter</a> |
                <a href="../../securite/register.php">S'inscrire</a>
            <?php endif; ?>
            <p><a href="search_clinics.php">Retour à la recherche de cliniques</a></p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($clinic_info): ?>
            <h1>Détails de la Clinique : <?php echo htmlspecialchars($clinic_info['name']); ?></h1>
            <div class="clinic-details">
                <p><strong>Adresse:</strong> <?php echo htmlspecialchars($clinic_info['address']); ?>, <?php echo htmlspecialchars($clinic_info['city']); ?>, <?php echo htmlspecialchars($clinic_info['country']); ?></p>
                <p><strong>Téléphone:</strong> <?php echo htmlspecialchars($clinic_info['phone'] ?: 'N/A'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($clinic_info['email'] ?: 'N/A'); ?></p>
                <p><strong>Site Web:</strong> <?php echo $clinic_info['website'] ? '<a href="' . htmlspecialchars($clinic_info['website']) . '" target="_blank">' . htmlspecialchars($clinic_info['website']) . '</a>' : 'N/A'; ?></p>
                <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($clinic_info['description'] ?: 'Pas de description disponible.')); ?></p>
            </div>

            <hr>

            <h2>Médecins de cette clinique</h2>
            <?php if (empty($doctors_in_clinic)): ?>
                <div class="no-doctors">
                    <p>Aucun médecin associé trouvé pour cette clinique.</p>
                </div>
            <?php else: ?>
                <div class="doctors-list">
                    <?php foreach ($doctors_in_clinic as $doctor): ?>
                        <div class="doctor-card">
                            <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                            <p><strong>Spécialités:</strong> <span class="specialties"><?php echo htmlspecialchars($doctor['doctor_specialties'] ?: 'Non spécifié'); ?></span></p>
                            <p><strong>Langues parlées:</strong> <?php echo htmlspecialchars($doctor['language'] ?: 'Non spécifié'); ?></p>
                            <p><?php echo nl2br(htmlspecialchars($doctor['bio'] ?: 'Pas de biographie disponible.')); ?></p>
                            <a href="book_appointment.php?doctor_id=<?php echo $doctor['doctor_id']; ?>&clinic_id=<?php echo $clinic_id; ?>" class="button">Prendre rendez-vous avec ce médecin</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="no-results">
                <p>Impossible d'afficher les détails de la clinique. Veuillez retourner à la page de recherche.</p>
                <p><a href="search_clinics.php" class="button">Retour à la recherche</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>