<?php
// Fichier : MEDECINE/login.php
// Gère le processus de connexion des utilisateurs (patients, cliniques, médecins).

// 1. Démarrer la session PHP au tout début du script
// Toujours vérifier si la session n'est pas déjà démarrée pour éviter les erreurs.
// if (session_status() == PHP_SESSION_NONE) {
//     session_start();
// }
require_once '../includes/auth_check.php'; // Pour les constantes et display_flash_message()

// 2. Inclure les fichiers nécessaires
// a. Fichier de connexion à la base de données
require_once '../includes/Database.php';

// b. Fichier de configuration/constantes pour les URL de redirection et messages flash
// Si votre auth_check.php contient les définitions de constantes comme LOGIN_PAGE, BASE_URL,
// et la fonction display_flash_message(), vous devriez l'inclure ici.
// Ou bien, un fichier de configuration séparé. Pour cet exemple, on peut l'inclure directement.

// Initialiser les variables pour le formulaire (pour pré-remplir l'email en cas d'erreur)
$email = '';

// Récupérer et afficher les messages flash s'il y en a (par exemple, après une déconnexion)
$flash_message_content = '';
$flash_message_type = '';

if (isset($_SESSION['flash_message'])) {
    $flash_message_content = htmlspecialchars($_SESSION['flash_message']['message']);
    $flash_message_type = htmlspecialchars($_SESSION['flash_message']['type']);
    unset($_SESSION['flash_message']); // Supprimer le message après l'avoir récupéré
}

// Si le formulaire est soumis (méthode POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupérer et nettoyer les données du formulaire
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password']; // Le mot de passe n'est pas nettoyé ici car password_verify le gère.

    // --- Validation basique côté serveur ---
    if (empty($email) || empty($password)) {
        $flash_message_content = "Veuillez remplir tous les champs.";
        $flash_message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $flash_message_content = "L'adresse email n'est pas valide.";
        $flash_message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            // Préparer la requête pour récupérer l'utilisateur par email
            // Utilisation des requêtes préparées pour prévenir les injections SQL.
            $stmt = $pdo->prepare("SELECT id, email, password_hash, role FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC); // Utiliser FETCH_ASSOC pour des clés de tableau associatives

            // Vérifier si un utilisateur a été trouvé et si le mot de passe est correct
            if ($user && password_verify($password, $user['password_hash'])) {
                // --- CONNEXION RÉUSSIE ---

                // TRÈS IMPORTANT : Régénérer l'ID de session pour prévenir les attaques de fixation de session
                session_regenerate_id(true); // 'true' supprime l'ancienne session

                // Stocker les informations de l'utilisateur dans la session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role']; // 'patient', 'clinic', 'doctor'
                $_SESSION['LAST_ACTIVITY'] = time(); // Initialiser l'heure de la dernière activité pour la sécurité de session

                // Message flash de succès de connexion
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Connexion réussie ! Bienvenue.'];

                // Redirection après connexion réussie en fonction du rôle
                switch ($user['role']) {
                    case 'patient':
                        header('Location: ' . BASE_URL . 'app/patient/dashboard_patient.php');
                        break;
                    case 'clinic':
                        header('Location: ' . BASE_URL . 'app/clinic/dashboard_clinic.php');
                        break;
                    case 'doctor':
                        header('Location: ' . BASE_URL . 'app/medecin/dashboard_doctor.php'); // Note: 'medecin' vs 'doctor'
                        break;
                    default:
                        // Gérer les rôles non reconnus (par exemple, rediriger vers une page d'erreur ou l'index)
                        $_SESSION['flash_message'] = ['type' => 'warning', 'message' => 'Votre rôle n\'est pas reconnu.'];
                        header('Location: ' . BASE_URL . 'index.php');
                }
                exit(); // Arrêter l'exécution du script après la redirection

            } else {
                // Identifiants incorrects
                $flash_message_content = "Email ou mot de passe incorrect.";
                $flash_message_type = 'error';
                // Pour la sécurité, ne donnez pas d'informations spécifiques (email ou mot de passe)
                // sur ce qui est incorrect.
            }

        } catch (PDOException $e) {
            // Gérer les erreurs de base de données
            $flash_message_content = "Une erreur est survenue lors de la connexion. Veuillez réessayer.";
            $flash_message_type = 'error';
            // En production, il est impératif de LOGUER l'erreur complète ($e->getMessage())
            // et de ne pas l'afficher directement à l'utilisateur pour des raisons de sécurité.
            error_log("Login PDO Error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Système de Rendez-vous</title>
    <style>
        /* Votre CSS existant */
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .login-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 400px; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="email"],
        input[type="password"] {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover { background-color: #218838; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; } /* Style pour les messages info */
        .message.warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; } /* Style pour les messages warning */
        .register-link { text-align: center; margin-top: 20px; }
        .register-link a { color: #007bff; text-decoration: none; }
        .register-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Connexion</h2>

        <?php
        // Afficher les messages flash (si disponibles)
        if (!empty($flash_message_content)) {
            echo '<div class="message ' . $flash_message_type . '">' . $flash_message_content . '</div>';
        }
        // OU si vous utilisez la fonction display_flash_message() de auth_check.php:
        // display_flash_message();
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Se connecter</button>
        </form>
        <div class="register-link">
            Pas encore de compte ? <a href="register.php">Créez-en un ici</a>
        </div>
    </div>
</body>
</html>