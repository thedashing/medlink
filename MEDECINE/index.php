<?php
session_start();
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_role']) {
        case 'patient':
            header('Location: dashboard_patient.php');
            exit();
        case 'clinic':
            header('Location: dashboard_clinic.php');
            exit();
        case 'doctor':
            header('Location: dashboard_doctor.php');
            exit();
        default:
            header('Location: login.php');
            exit();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Plateforme de gestion de rendez-vous médicaux en ligne. Prenez rendez-vous avec des médecins, gérez votre clinique ou votre profil de médecin.">
    <title>MediPlan - Plateforme de rendez-vous médicaux</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Roboto:wght@300;400;500&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #2a7fba;
            --primary-dark: #1a5f8b;
            --secondary-color: #4CAF50;
            --accent-color: #FF6B6B;
            --light-gray: #f5f7fa;
            --medium-gray: #e1e5eb;
            --dark-gray: #333;
            --white: #ffffff;
            --shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            color: #444;
            background-color: var(--light-gray);
            background-image: linear-gradient(135deg, #f5f7fa 0%, #e4f0fb 100%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 1rem;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
        }

        h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-size: 2.2rem;
        }

        .subtitle {
            font-size: 1.2rem;
            color: var(--dark-gray);
            margin-bottom: 2rem;
        }

        .role-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .role-option {
            background: var(--white);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            text-align: center;
            border-top: 4px solid var(--primary-color);
            position: relative;
            overflow: hidden;
        }

        .role-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .role-option:nth-child(2) {
            border-top-color: var(--secondary-color);
        }

        .role-option:nth-child(3) {
            border-top-color: var(--accent-color);
        }

        .role-option h3 {
            font-family: 'Montserrat', sans-serif;
            color: var(--dark-gray);
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .role-option p {
            color: #666;
            margin-bottom: 1.5rem;
            min-height: 60px;
        }

        .role-option a {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background-color: var(--primary-color);
            color: var(--white);
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .role-option:nth-child(2) a {
            background-color: var(--secondary-color);
        }

        .role-option:nth-child(3) a {
            background-color: var(--accent-color);
        }

        .role-option a:hover {
            background-color: transparent;
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .role-option:nth-child(2) a:hover {
            color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .role-option:nth-child(3) a:hover {
            color: var(--accent-color);
            border-color: var(--accent-color);
        }

        .login-section {
            text-align: center;
            padding: 1.5rem;
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
        }

        .login-section a {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
            transition: var(--transition);
            border-bottom: 1px dashed var(--primary-color);
        }

        .login-section a:hover {
            color: var(--primary-dark);
            border-bottom-color: var(--primary-dark);
        }

        .icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .role-option:nth-child(2) .icon {
            color: var(--secondary-color);
        }

        .role-option:nth-child(3) .icon {
            color: var(--accent-color);
        }

        @media (max-width: 768px) {
            .role-options {
                grid-template-columns: 1fr;
            }
            
            h1 {
                font-size: 1.8rem;
            }
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .role-option {
            animation: fadeIn 0.6s ease forwards;
        }

        .role-option:nth-child(2) {
            animation-delay: 0.2s;
        }

        .role-option:nth-child(3) {
            animation-delay: 0.4s;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Bienvenue sur MedLink</h1>
            <p class="subtitle">Votre plateforme de gestion de rendez-vous médicaux en ligne</p>
        </header>

        <section class="role-options">
            <div class="role-option">
                <div class="icon"><i class="fas fa-user-injured"></i></div>
                <h3>Je suis un Patient</h3>
                <p>Trouvez et réservez des rendez-vous facilement avec des professionnels de santé près de chez vous.</p>
                <a href="securite/register_patient.php">S'inscrire comme Patient</a>
            </div>
            <div class="role-option">
                <div class="icon"><i class="fas fa-clinic-medical"></i></div>
                <h3>Je suis une Clinique</h3>
                <p>Gérez vos plannings, médecins et réservations en toute simplicité.</p>
                <a href="securite/register_clinic.php">S'inscrire comme Clinique</a>
            </div>
            <div class="role-option">
                <div class="icon"><i class="fas fa-user-md"></i></div>
                <h3>Je suis un Médecin</h3>
                <p>Gérez votre profil, vos disponibilités et vos consultations dans différentes cliniques.</p>
                <a href="securite/register_doctor.php">S'inscrire comme Médecin</a>
            </div>
        </section>

        <div class="login-section">
            <p>Vous avez déjà un compte ? <a href="securite/login.php">Connectez-vous ici</a></p>
        </div>
    </div>
</body>
</html>