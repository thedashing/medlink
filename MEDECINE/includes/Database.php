<?php

// Fichier : Database.php

// Paramètres de connexion à la base de données
define('DB_HOST', 'localhost'); // Ou l'adresse IP de votre serveur de base de données
define('DB_NAME', 'projet2'); // Nom de votre base de données
define('DB_USER', 'root1'); // Votre nom d'utilisateur MySQL
define('DB_PASS', 'HuntersX01!'); 
class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Rapporte les erreurs sous forme d'exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Récupère les résultats sous forme de tableau associatif
            PDO::ATTR_EMULATE_PREPARES   => false,                // Désactive l'émulation des requêtes préparées (pour de meilleures performances et sécurité)
        ];

        try {
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En production, vous voudriez loguer l'erreur au lieu de l'afficher
            // et afficher un message générique à l'utilisateur.
            die('Erreur de connexion à la base de données : ' . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}

// Exemple d'utilisation (pour test)
/*
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "Connexion à la base de données réussie !";
} catch (Exception $e) {
    echo "Erreur lors de la connexion : " . $e->getMessage();
}
*/

?>