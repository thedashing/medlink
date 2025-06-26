<?php
// Fichier: MEDECINE/patient/patient_header.php


// Inclure la classe Messaging
// Assurez-vous que ce chemin est correct par rapport à l'emplacement d'où patient_header.php est inclus.
// Par exemple, si patient_header.php est dans MEDECINE/patient/ et Messaging.php dans classes/,
// alors le chemin relatif serait '../../classes/Messaging.php'
require_once '../../includes/Database.php';
require_once '../../includes/patient/messaging_functions.php'; // Chemin ajusté pour la classe Messaging
require_login('patient');

// Initialisation des variables pour éviter les erreurs si l'utilisateur n'est pas connecté
$user_id = $_SESSION['user_id'] ?? null; // Utilise l'opérateur null coalescing pour une meilleure gestion
$user_profile_picture = 'default.jpg';
$user_first_name = 'Invité'; // Valeur par défaut pour les non-connectés
$unread_messages_count = 0;

// Si l'utilisateur est connecté, récupérez ses informations
if ($user_id !== null) {
    $messaging = new Messaging(); // Instancier la classe Messaging
    $unread_messages_count = $messaging->getUnreadMessagesCount($user_id);

    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();

        $stmt = $pdo->prepare("SELECT profile_picture, first_name, last_name FROM patients WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $user_id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($patient) {
            // Utiliser la photo de profil du patient ou une par défaut
            $user_profile_picture = !empty($patient['profile_picture']) ? htmlspecialchars($patient['profile_picture']) : 'default.jpg';
            // Construire le nom affiché (Première lettre du prénom en majuscule + Nom de famille)
            $user_first_name = '';
            if (!empty($patient['first_name'])) {
                $user_first_name .= mb_strtoupper(mb_substr($patient['first_name'], 0, 1, 'UTF-8')) . '.';
            }
            if (!empty($patient['last_name'])) {
                $user_first_name .= ' ' . htmlspecialchars($patient['last_name']);
            }
            $user_first_name = trim($user_first_name); // Supprimer les espaces inutiles
            if (empty($user_first_name)) { // Au cas où prénom et nom sont vides
                $user_first_name = 'Utilisateur';
            }
        } else {
            $user_profile_picture = 'default.jpg';
            $user_first_name = 'Utilisateur';
        }

    } catch (PDOException $e) {
        error_log("Erreur de base de données dans patient_header: " . $e->getMessage());
        $user_profile_picture = 'default.jpg';
        $user_first_name = 'Erreur';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MedLink - Patient</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
   <?php
// [Votre code PHP existant reste inchangé jusqu'à la balise <style>]
?>

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

    /* Reset and Base Styles */
    * {   
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Roboto', sans-serif; 
    }

    body {
        color: var(--dark-color);
        line-height: 1.6;
        background-color: var(--light-color); 
    }

    .container {
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    /* Header styles */
    .patient-header {
        background-color: var(--secondary-color);
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        position: sticky;
        top: 0;
        z-index: 100;
    }

    .patient-header .container {
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 70px;
        position: relative;
    }

    /* Logo styles */
    .logo { 
        display: flex; 
        align-items: center; 
        gap: 10px; 
        text-decoration: none;
        z-index: 101; /* Au-dessus du menu mobile */
    }

    .logo h1 {
        font-size: 1.8em; 
        color: #ffffff; 
        margin: 0; 
        font-weight: 700; 
        letter-spacing: -0.5px; 
    }

    .logo h1 span {
        color: var(--primary-color); 
        font-weight: 900; 
    }

    /* Navigation styles */
    .patient-nav {
        display: flex;
        align-items: center;
    }

    .patient-nav ul {
        display: flex;
        list-style: none;
    }

    .patient-nav ul li {
        margin: 0 10px;
    }

    .patient-nav ul li a {
        text-decoration: none;
        color: #e0e0e0;
        font-weight: 500;
        font-size: 15px;
        display: flex;
        align-items: center;
        padding: 10px;
        border-radius: 5px;
        transition: all 0.3s ease;
        position: relative; 
    }

    .patient-nav ul li a i {
        margin-right: 8px;
    }

    /* Active link style */
    .patient-nav ul li a.active {
        color: var(--primary-color); 
        background-color: var(--primary-light); 
    }

    .patient-nav ul li a:hover {
        color: var(--primary-color); 
    }
    
    /* Message badge */
    .message-badge {
        position: absolute;
        top: 0px; 
        right: 0px; 
        background-color: var(--danger-color); 
        color: white;
        border-radius: 50%;
        width: 20px; 
        height: 20px; 
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7em; 
        font-weight: bold;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        transform: translate(50%, -50%); 
    }

    /* Patient account section */
    .patient-account {
        display: flex;
        align-items: center;
        cursor: pointer;
        padding: 5px 10px;
        border-radius: 20px;
        transition: all 0.3s ease; 
        color: var(--light-color); 
        z-index: 101; /* Au-dessus du menu mobile */
    }

    .patient-account:hover {
        background-color: var(--primary-light); 
    }

    .profile-link {
        display: flex;
        align-items: center;
        text-decoration: none;
        color: inherit;
    }

    .patient-account img {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        margin-right: 8px;
        object-fit: cover; 
        border: 1px solid rgba(255,255,255,0.3);
    }

    .patient-account span {
        font-weight: 500;
        margin-right: 5px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100px;
    }

    .patient-account i {
        font-size: 12px;
        color: var(--light-color); 
    }

    /* Hamburger menu button */
    .hamburger {
        display: none;
        cursor: pointer;
        background: none;
        border: none;
        padding: 10px;
        z-index: 101;
    }

    .hamburger span {
        display: block;
        width: 25px;
        height: 3px;
        background-color: white;
        margin: 5px 0;
        transition: all 0.3s ease;
    }

    /* Responsive styles */
    @media (max-width: 992px) {
        .patient-nav ul li {
            margin: 0 8px;
        }
        .patient-nav ul li a {
            padding: 8px;
            font-size: 14px;
        }
        .logo h1 {
            font-size: 1.6em;
        }
    }

    @media (max-width: 768px) {
        /* Hide regular navigation */
        .patient-nav {
            position: fixed;
            top: 0;
            left: -100%;
            width: 80%;
            max-width: 300px;
            height: 100vh;
            background-color: var(--secondary-color);
            flex-direction: column;
            justify-content: flex-start;
            padding-top: 80px;
            transition: all 0.3s ease;
            z-index: 100;
        }

        .patient-nav.active {
            left: 0;
            box-shadow: 5px 0 15px rgba(0,0,0,0.2);
        }

        .patient-nav ul {
            flex-direction: column;
            width: 100%;
            padding: 0 20px;
        }

        .patient-nav ul li {
            margin: 5px 0;
            width: 100%;
        }

        .patient-nav ul li a {
            padding: 12px 15px;
            justify-content: flex-start;
            border-radius: 5px;
        }

        /* Show hamburger button */
        .hamburger {
            display: block;
        }

        /* Adjust account section */
        .patient-account {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background-color: rgba(42, 157, 143, 0.9);
            padding: 10px 15px;
            border-radius: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        /* Overlay when menu is open */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 99;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Hamburger animation */
        .hamburger.active span:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
        }
        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }
        .hamburger.active span:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
        }
    }

    @media (max-width: 480px) {
        .patient-header .container {
            padding: 0 15px;
        }
        
        .logo h1 {
            font-size: 1.4em;
        }
        
        .patient-nav {
            width: 85%;
        }
        
        .patient-account span {
            max-width: 70px;
        }
    }
</style>

</head>
<body>
    <header class="patient-header">
        <div class="container">
            <div class="logo">   
                <h1>Med<span>Link</span></h1>
            </div>

            <!-- Hamburger Menu Button -->
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <!-- Navigation Menu -->
            <nav class="patient-nav" id="patient-nav">
                <ul id="patient-nav-list">
                    <li><a href="../patient/search.php">
                        <i class="fas fa-search"></i> Recherche Médecin</a>
                    </li>
                    <li><a href="../patient/mes_rendez_vous.php">
                        <i class="fas fa-calendar-alt"></i> Mes rendez-vous</a>
                    </li>
                    <li><a href="../patient/mon_dossier.php">
                        <i class="fas fa-file-medical"></i> Mon dossier médical</a>
                    </li> 
                    <li>
                        <a href="../patient/messagerie.php">
                            <i class="fas fa-envelope"></i> 
                            <?php if ($unread_messages_count > 0): ?>
                                <span class="message-badge"><?php echo $unread_messages_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li><a href="../../securite/login.php">
                        <i class="fas fa-sign-out-alt"></i> Se déconnecter</a>
                    </li>
                </ul>
            </nav>

            <div class="patient-account">
                <?php if ($user_id !== null): ?>
                    <a href="../patient/patient_profile.php" class="profile-link"> 
                        <img src="../../uploads/profile_images/<?php echo $user_profile_picture; ?>" alt="Photo profil" class="profile-img">
                        <span><?php echo $user_first_name; ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </a>
                <?php else: ?>
                    <p>Non connecté. <a href="../../securite/login.php">Se connecter</a> ou <a href="../../register.php">S'inscrire</a> pour prendre rendez-vous.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Overlay for mobile menu -->
        <div class="overlay" id="overlay"></div>
    </header>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.getElementById('hamburger');
            const patientNav = document.getElementById('patient-nav');
            const overlay = document.getElementById('overlay');
            
            // Toggle mobile menu
            hamburger.addEventListener('click', function() {
                this.classList.toggle('active');
                patientNav.classList.toggle('active');
                overlay.classList.toggle('active');
                
                // Prevent scrolling when menu is open
                if (patientNav.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            });
            
            // Close menu when clicking on overlay
            overlay.addEventListener('click', function() {
                hamburger.classList.remove('active');
                patientNav.classList.remove('active');
                this.classList.remove('active');
                document.body.style.overflow = '';
            });
            
            // Set active link (your existing code)
            const navList = document.getElementById('patient-nav-list');
            if (navList) {
                const navLinks = navList.querySelectorAll('a');
                const currentPath = window.location.pathname;
                
                function setActiveLinkOnLoad() {
                    let foundActive = false;
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        const linkPath = new URL(link.href).pathname;
                        
                        if (currentPath.includes(linkPath) && !foundActive) {
                            link.classList.add('active');
                            foundActive = true;
                        }
                    });
                }
                
                setActiveLinkOnLoad();
                
                navLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        // Close mobile menu when a link is clicked
                        if (window.innerWidth <= 768) {
                            hamburger.classList.remove('active');
                            patientNav.classList.remove('active');
                            overlay.classList.remove('active');
                            document.body.style.overflow = '';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>