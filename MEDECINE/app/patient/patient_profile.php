<?php
// Fichier: MEDECINE/patient/patient_profile.php

require_once '../../includes/auth_check.php'; // S'assure que l'utilisateur est connecté

// S'assure que la session est démarrée si auth_check.php ne le fait pas toujours
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../../includes/patient/Messaging_functions.php';// Inclure la classe Messaging pour le compteur de messages
require_once '../../includes/Database.php'; // Inclure la connexion à la base de données

$user_id = $_SESSION['user_id'] ?? null; // Récupère l'ID de l'utilisateur connecté, avec une sécurité pour null

$patient_data = null; // Initialisation de patient_data
$error_message = null; // Initialisation de error_message

if ($user_id === null) {
    // Si l'utilisateur n'est pas connecté, redirigez-le ou affichez un message d'erreur approprié.
    $error_message = "Vous devez être connecté pour voir votre profil.";
} else {
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();

        // Récupérer les informations du patient et de l'utilisateur
        $stmt = $pdo->prepare("
            SELECT 
                u.email, 
                p.first_name, 
                p.last_name, 
                p.phone, 
                p.date_of_birth, 
                p.gender, 
                p.address, 
                p.profile_picture
            FROM users u
            JOIN patients p ON u.id = p.user_id
            WHERE u.id = :user_id
        ");
        $stmt->execute([':user_id' => $user_id]);
        $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient_data) {
            $error_message = "Impossible de charger les données de votre profil. Veuillez compléter votre profil.";
        }

    } catch (PDOException $e) {
        $error_message = "Erreur de base de données : " . $e->getMessage();
        // En production, loguez l'erreur et affichez un message générique.
    }
}

// Définir le chemin de l'image de profil
// Si $patient_data est null (non connecté ou erreur), utilisez 'default.jpg'
$profile_picture_filename = $patient_data['profile_picture'] ?? 'default.jpg';
$profile_picture_src = '../../uploads/profile_images/' . htmlspecialchars($profile_picture_filename); 
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Profil Patient</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
    :root {
        --primary-color: #2a9d8f;
        --primary-light: rgba(42, 157, 143, 0.1);
        --secondary-color: #264653;
        --accent-color: #e9c46a;
        --danger-color: #e76f51;
        --warning-color: #f4a261;
        --success-color: #2a9d8f;
        --light-color: #f8f9fa;
        --dark-color: #343a40;
        --gray-color: #6c757d;
        --border-color: #e0e0e0;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f8fafc;
        margin: 0;
        padding: 0;
        line-height: 1.6;
        color: #333;
    }

    .profile-container {
        max-width: 1000px;
        margin: 30px auto;
        padding: 30px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }

    .profile-container h2 {
        color: var(--secondary-color);
        font-size: 28px;
        margin-bottom: 25px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-light);
    }

    .profile-info {
        display: flex;
        gap: 40px;
        align-items: flex-start;
        margin-bottom: 30px;
    }

    .profile-info img {
        width: 180px;
        height: 180px;
        border-radius: 50%;
        object-fit: cover;
        border: 5px solid var(--primary-light);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .profile-details {
        flex: 1;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .profile-details div {
        margin-bottom: 15px;
    }

    .profile-details label {
        display: block;
        font-weight: 600;
        color: var(--secondary-color);
        margin-bottom: 5px;
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .profile-details span {
        display: block;
        padding: 10px 15px;
        background-color: var(--primary-light);
        border-radius: 6px;
        font-size: 16px;
        color: var(--dark-color);
    }

    .action-buttons {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }

    .action-buttons a {
        display: inline-block;
        padding: 12px 25px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        transition: all 0.3s ease;
        text-align: center;
    }

    .action-buttons a:first-child {
        background-color: var(--primary-color);
        color: white;
        border: 2px solid var(--primary-color);
    }

    .action-buttons a:first-child:hover {
        background-color: #22867a;
        border-color: #22867a;
    }

    .action-buttons a:last-child {
        background-color: transparent;
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
    }

    .action-buttons a:last-child:hover {
        background-color: var(--primary-light);
    }

    .error-message {
        color: var(--danger-color);
        background-color: rgba(231, 111, 81, 0.1);
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        border-left: 4px solid var(--danger-color);
    }

    @media (max-width: 768px) {
        .profile-info {
            flex-direction: column;
            gap: 20px;
        }

        .profile-details {
            grid-template-columns: 1fr;
        }

        .action-buttons {
            flex-direction: column;
        }
    }
</style>
</head>
<body>
    <?php include '../../includes/patient/entete.php'; // Inclure l'en-tête de votre patient ?>

    <div class="profile-container">
        <h2>Mon Profil</h2>

        <?php if (isset($error_message) && $error_message): // Affiche l'erreur si définie ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php elseif ($patient_data): // Affiche le profil si les données sont disponibles ?>
            <div class="profile-info">
                <img src="<?php echo htmlspecialchars($profile_picture_src); ?>" alt="Image de profil">
                <div class="profile-details">
                    <div><label>Nom Complet :</label> <span><?php echo htmlspecialchars($patient_data['first_name'] . ' ' . $patient_data['last_name']); ?></span></div>
                    <div><label>Email :</label> <span><?php echo htmlspecialchars($patient_data['email']); ?></span></div>
                    <div><label>Téléphone :</label> <span><?php echo htmlspecialchars($patient_data['phone'] ?? 'Non défini'); ?></span></div>
                    <div><label>Date de naissance :</label> <span><?php echo htmlspecialchars($patient_data['date_of_birth'] ?? 'Non défini'); ?></span></div>
                    <div><label>Sexe :</label> <span><?php echo htmlspecialchars(ucfirst($patient_data['gender'] ?? 'Non défini')); ?></span></div>
                    <div><label>Adresse :</label> <span><?php echo htmlspecialchars($patient_data['address'] ?? 'Non définie'); ?></span></div>
                </div>
            </div>

            <div class="action-buttons">
                <a href="edit_profile.php"><i class="fas fa-edit"></i> Modifier le Profil</a>
                <a href="../patient/mes_rendez_vous.php"><i class="fas fa-calendar-check"></i> Voir mes rendez-vous</a>
            </div>
        <?php endif; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navList = document.getElementById('patient-nav-list');
            if (navList) {
                const navLinks = navList.querySelectorAll('a');
                const currentPath = window.location.pathname; 

                function setActiveLinkOnLoad() {
                    let foundActive = false;
                    navLinks.forEach(link => {
                        link.classList.remove('active'); 
                        const linkPath = new URL(link.href).pathname;
                        
                        // Logique ajustée pour tenir compte du répertoire de base si votre application est dans un sous-dossier comme /MEDECINE/
                        // Cela rend la détection du lien actif plus robuste.
                        const basePath = '/MEDECINE/'; // Ajustez cela si votre application est dans un dossier racine différent
                        if (currentPath.startsWith(basePath) && linkPath.startsWith(basePath)) {
                            const relativeLinkPath = linkPath.substring(basePath.length);
                            const relativeCurrentPath = currentPath.substring(basePath.length);

                            if (relativeCurrentPath.includes(relativeLinkPath) && !foundActive) {
                                link.classList.add('active');
                                foundActive = true;
                            }
                        } else if (currentPath.includes(linkPath) && !foundActive) { // Fallback pour les chemins de niveau racine
                            link.classList.add('active');
                            foundActive = true;
                        }
                    });
                }

                setActiveLinkOnLoad();

                navLinks.forEach(link => {
                    link.addEventListener('click', function(event) {
                        // Pour les rechargements complets de page, ce gestionnaire de clic est moins critique pour la classe 'active'
                        // car setActiveLinkOnLoad sera réexécuté sur la nouvelle page.
                        // Cependant, il fournit un feedback visuel immédiat.
                        // Si vous passez à un modèle SPA, alors event.preventDefault() serait nécessaire ici.
                        // Pour l'instant, laissons la navigation par défaut se produire.
                        // Nous supprimons et ajoutons la classe active juste pour un feedback immédiat avant le rechargement de la page.
                        navLinks.forEach(l => l.classList.remove('active'));
                        this.classList.add('active');
                    });
                });
            }
        });
    </script>
</body>
</html>