<?php
require_once '../../includes/patient/search_function.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche de Cliniques</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/patient/search.css">
</head>
<body>
    <div class="container">
        <div class="header-main">
            <h1>Recherche de Cliniques</h1>
            <nav>
                <a href="../patient/dashboard_patient.php">Tableau de Bord</a>
                <a href="../patient/mes_rendez_vous.php">Mes rendez-vous</a>
                <a href="../patient/mon_dossier.php">Mon dossier médical</a>
                <a href="../securite/logout.php">Se déconnecter</a>
            </nav>
        </div>

        <div class="user-info">
            <?php if (is_logged_in()): ?>
                <p>Connecté en tant que: **<?php echo $user_email; ?>** (<?php echo htmlspecialchars(ucfirst($user_role)); ?>)</p>
            <?php else: ?>
                <p>Non connecté. <a href="../../login.php">Se connecter</a> ou <a href="../../register.php">S'inscrire</a> pour prendre rendez-vous.</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($message)): ?>
            <div class="system-message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="GET" class="search-form">
            <div class="form-group">
                <label for="specialty">Spécialité disponible:</label>
                <select id="specialty" name="specialty">
                    <option value="">Toutes les spécialités</option>
                    <?php foreach ($specialties as $specialty): ?>
                        <option value="<?php echo htmlspecialchars($specialty['id']); ?>"
                            <?php echo ($prev_specialty == $specialty['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($specialty['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="location">Localisation (Ville/Adresse de la clinique):</label>
                <input type="text" id="location" name="location" placeholder="Ex: Ouagadougou, 123 Rue Principale" value="<?php echo htmlspecialchars($prev_location); ?>">
            </div>
            <div class="form-group">
                <label for="language">Langue parlée par un médecin de la clinique:</label>
                <select id="language" name="language">
                    <option value="">Toutes les langues</option>
                    <?php foreach ($languages as $lang): ?>
                        <option value="<?php echo htmlspecialchars($lang['language']); ?>"
                            <?php echo ($prev_language == $lang['language']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($lang['language']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-button-container">
                <button type="submit" name="search">Rechercher des cliniques</button>
            </div>
        </form>

        <h2>Résultats de la Recherche</h2>
        <?php if (empty($clinics) && isset($_GET['search'])): ?>
            <div class="no-results">
                <p>Aucune clinique trouvée correspondant à vos critères de recherche.</p>
                <p>Essayez d'ajuster vos filtres ou de rechercher plus largement.</p>
            </div>
        <?php elseif (empty($clinics) && !isset($_GET['search'])): ?>
            <div class="no-results">
                <p>Utilisez le formulaire ci-dessus pour rechercher des cliniques.</p>
                <p>Vous pouvez filtrer par spécialité, localisation ou langue des médecins.</p>
            </div>
        <?php else: ?>
            <div class="results-list">
                <?php foreach ($clinics as $clinic): ?>
                    <div class="clinic-card">
                        <h3><?php echo htmlspecialchars($clinic['clinic_name']); ?></h3>
                        <p><strong>Adresse:</strong> <span class="details"><?php echo htmlspecialchars($clinic['clinic_address']); ?>, <?php echo htmlspecialchars($clinic['clinic_city']); ?></span></p>
                        <p><strong>Téléphone:</strong> <span class="details"><?php echo htmlspecialchars($clinic['clinic_phone'] ?: 'Non spécifié'); ?></span></p>
                        <p><strong>Email:</strong> <span class="details"><?php echo htmlspecialchars($clinic['clinic_email'] ?: 'Non spécifié'); ?></span></p>
                        <p><strong>Description:</strong> <span class="details"><?php echo nl2br(htmlspecialchars($clinic['clinic_description'] ?: 'Pas de description disponible.')); ?></span></p>
                        <p><strong>Spécialités disponibles:</strong> <span class="specialties"><?php echo htmlspecialchars($clinic['clinic_available_specialties'] ?: 'Aucune spécialité spécifiée'); ?></span></p>
                        <p><strong>Langues parlées par les médecins:</strong> <span class="languages"><?php echo htmlspecialchars($clinic['clinic_available_languages'] ?: 'Aucune langue spécifiée'); ?></span></p>
                        <div class="clinic-actions">
                            <a href="view_clinic_doctors.php?clinic_id=<?php echo $clinic['clinic_id']; ?>" class="button">Voir les médecins de cette clinique</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>