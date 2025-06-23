<?php
// Fichier : MEDECINE/logout.php
// Gère la déconnexion de l'utilisateur en détruisant sa session.

// 1. Démarrer la session
// Il est crucial de démarrer la session pour pouvoir y accéder et la manipuler.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Préparer un message flash de succès
// C'est une bonne pratique pour informer l'utilisateur qu'il a été déconnecté avec succès.
// Ce message sera affiché sur la page de connexion après la redirection.
$_SESSION['flash_message'] = [
    'type' => 'info', // Ou 'success', selon le style que vous préférez pour vos messages
    'message' => 'Vous avez été déconnecté avec succès.'
];

// 3. Détruire toutes les variables de session
// Cela supprime toutes les données stockées dans la session pour l'utilisateur actuel.
$_SESSION = array();

// 4. Détruire le cookie de session côté client
// Si PHP gère les sessions via des cookies (ce qui est le cas par défaut et recommandé),
// il est important de supprimer ce cookie pour invalider la session côté navigateur.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), // Nom du cookie de session (ex: PHPSESSID)
        '',             // Valeur vide pour effacer le cookie
        time() - 42000, // Une date passée pour faire expirer le cookie immédiatement
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 5. Détruire la session sur le serveur
// C'est l'étape finale qui supprime le fichier de session du serveur.
session_destroy();

// 6. Rediriger l'utilisateur vers la page de connexion
// Utilisez la constante LOGIN_PAGE si vous l'avez définie dans votre fichier de configuration
// pour une meilleure cohérence. Sinon, 'login.php' est correct si le fichier est au même niveau.
// N'oubliez pas d'inclure votre fichier de configuration si LOGIN_PAGE y est défini.
// Si vous n'avez pas de config.php centralisé avec cette constante :
header('Location: login.php');
// Si vous avez une constante comme dans notre auth_check.php, vous devriez inclure le fichier config
// ou redéfinir la constante si elle n'est pas déjà définie, par exemple :
/*
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
    define('LOGIN_PAGE', BASE_URL . 'login.php');
}
header('Location: ' . LOGIN_PAGE);
*/

exit(); // Toujours appeler exit() après une redirection pour s'assurer que le script s'arrête.
?>