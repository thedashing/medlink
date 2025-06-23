<?php
// Fichier : MEDECINE/includes/auth_check.php
// Ce fichier contient les fonctions de vérification d'authentification et de gestion des rôles.

// --- Configuration des paramètres de session pour une sécurité accrue ---
// IMPORTANT : Ces réglages DOIVENT être faits AVANT session_start().

// Empêche l'accès au cookie de session via JavaScript (protection contre certaines attaques XSS).
ini_set('session.cookie_httponly', 1);

// Activez ceci UNIQUEMENT si votre site est servi en HTTPS.
// C'est une recommandation FORTE : utilisez HTTPS pour un système d'authentification.
// Décommentez la ligne ci-dessous si votre site est en production et utilise HTTPS.
// ini_set('session.cookie_secure', 1);


// Assurez-vous que la session est démarrée APRÈS la configuration des ini_set.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Définition des constantes de chemins ---
// Puisque vous n'avez pas de fichier de configuration séparé, définissons-les ici.
// Ces chemins sont relatifs à la racine de votre application web.
// Adaptez 'BASE_URL' à l'URL de base de votre application.
if (!defined('BASE_URL')) {
    // IMPORTANT : ADAPTEZ CE CHEMIN À LA RACINE DE VOTRE APPLICATION SUR LE SERVEUR WEB.
    // D'après votre avertissement, votre projet semble être dans 'C:\xampp\htdocs\ok1\projetx\'.
    // Si 'MEDECINE' est un sous-dossier de 'projetx', et 'projetx' est dans 'ok1',
    // et 'ok1' est directement servi par Apache/XAMPP, alors le chemin serait :
    define('BASE_URL', '/ok1/projetx/MEDECINE/'); // Ajustez ceci selon votre structure XAMPP/Apache

    // Chemins complets vers les pages importantes
    define('LOGIN_PAGE', BASE_URL . '../MEDECINE/securite/login.php');
    // La page de tableau de bord par défaut après une redirection non autorisée.
    define('UNAUTHORIZED_REDIRECT_PAGE', BASE_URL . 'index.php'); // Redirige vers l'index par défaut ou une page d'erreur générale
}


/**
 * Vérifie si l'utilisateur est connecté et possède le rôle requis.
 * Redirige l'utilisateur vers la page de connexion ou une page non autorisée si les conditions ne sont pas remplies.
 * Utilise des messages flash en session pour informer l'utilisateur.
 *
 * @param string|null $required_role Le rôle spécifique requis ('patient', 'clinic', 'doctor').
 * Si null, seul l'état de connexion est vérifié.
 */
function require_login($required_role = null) {
    // Si l'utilisateur n'est pas connecté du tout
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['flash_message'] = [
            'type' => 'info',
            'message' => 'Veuillez vous connecter pour accéder à cette page.'
        ];
        header('Location: ' . LOGIN_PAGE);
        exit();
    }

    // Si un rôle spécifique est requis et que l'utilisateur ne possède pas ce rôle
    if ($required_role !== null && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $required_role)) {
        $_SESSION['flash_message'] = [
            'type' => 'error',
            'message' => 'Vous n\'avez pas les permissions nécessaires pour accéder à cette page.'
        ];
        header('Location: ' . UNAUTHORIZED_REDIRECT_PAGE);
        exit();
    }

    // Optionnel mais recommandé : Régénérer l'ID de session à intervalle régulier.
    // Cela aide à prévenir la fixation de session et d'autres attaques.
    $inactive_timeout = 300; // 5 minutes en secondes

    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $inactive_timeout)) {
        session_regenerate_id(true); // 'true' supprime l'ancienne session
        $_SESSION['LAST_ACTIVITY'] = time(); // Met à jour le temps de dernière activité
    }
    // Met à jour le temps de dernière activité à chaque requête où require_login est appelé
    $_SESSION['LAST_ACTIVITY'] = time();
}

/**
 * Vérifie si un utilisateur est actuellement connecté.
 *
 * @return bool Vrai si l'utilisateur est connecté (présence de user_id en session), faux sinon.
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Récupère l'ID de l'utilisateur actuellement connecté.
 *
 * @return int|null L'ID de l'utilisateur ou null si personne n'est connecté.
 */
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Récupère le rôle de l'utilisateur actuellement connecté.
 *
 * @return string|null Le rôle de l'utilisateur ('patient', 'clinic', 'doctor') ou null si non défini.
 */
function get_user_role() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Récupère l'email de l'utilisateur actuellement connecté.
 *
 * @return string|null L'email de l'utilisateur ou null si non défini.
 */
function get_user_email() {
    return $_SESSION['user_email'] ?? null;
}

/**
 * Affiche et supprime les messages flash stockés en session.
 * Cette fonction est destinée à être appelée dans la partie HTML de vos pages.
 *
 * @return void
 */
function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message_type = htmlspecialchars($_SESSION['flash_message']['type']);
        $message_text = htmlspecialchars($_SESSION['flash_message']['message']);

        echo '<div class="message ' . $message_type . '" role="alert">';
        echo $message_text;
        echo '</div>';

        unset($_SESSION['flash_message']);
    }
}
?>