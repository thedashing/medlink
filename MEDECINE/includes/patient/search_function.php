<?php
// Fichier : MEDECINE/search_clinics.php

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once 'Messaging_functions.php'; // Inclure la classe Messaging pour le compteur de messages

require_login('patient');
// Initialisation des variables pour éviter les notices PHP si non définies
$user_email = isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : 'Invité';
$user_id = isset($_SESSION['user_id']) ? htmlspecialchars($_SESSION['user_id']) : null;
$user_role = isset($_SESSION['user_role']) ? htmlspecialchars($_SESSION['user_role']) : 'guest';

$clinics = []; // Variable pour stocker les résultats de la recherche de cliniques
$specialties = []; // Pour la liste déroulante des spécialités
$languages = []; // Pour la liste déroulante des langues des médecins
$message = '';  
$message_type = '';
$unread_messages_count = 0; // Initialisation du compteur


// Récupérer les spécialités et les langues pour les filtres
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $stmt = $pdo->query("SELECT id, name FROM specialties ORDER BY name");
    $specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT DISTINCT language FROM doctors WHERE language IS NOT NULL AND language != '' ORDER BY language");
    $languages = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Erreur lors du chargement des listes de filtres : " . $e->getMessage());
    $message = "Erreur interne lors du chargement des options de recherche.";
    $message_type = 'error';
}
 $messaging = new Messaging();
    $unread_messages_count = $messaging->getUnreadMessagesCount($user_id);

// Traitement de la recherche quand le formulaire est soumis
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search'])) {
    $search_specialty = htmlspecialchars(trim($_GET['specialty'] ?? ''));
    $search_location = htmlspecialchars(trim($_GET['location'] ?? '')); // Ville ou adresse de la clinique
    $search_language = htmlspecialchars(trim($_GET['language'] ?? '')); // Langue parlée par au moins un médecin de la clinique

    // Construction de la requête SQL pour chercher les cliniques
    $sql = "SELECT
                c.id AS clinic_id,
                c.name AS clinic_name,
                c.address AS clinic_address,
                c.phone AS clinic_phone,
                c.email AS clinic_email,
                c.description AS clinic_description,
                c.city AS clinic_city,
                GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS clinic_available_specialties,
                GROUP_CONCAT(DISTINCT d.language ORDER BY d.language SEPARATOR ', ') AS clinic_available_languages
            FROM
                clinics c
            JOIN
                clinic_doctors cd ON c.id = cd.clinic_id
            JOIN
                doctors d ON cd.doctor_id = d.id
            LEFT JOIN
                doctor_clinic_specialties dcs ON d.id = dcs.doctor_id AND c.id = dcs.clinic_id -- Nouvelle jointure ici
            LEFT JOIN
                specialties s ON dcs.specialty_id = s.id -- Liaison via la nouvelle table
            WHERE 1=1"; // Clause WHERE de base pour faciliter l'ajout des conditions

    $params = [];

    // Conditions de filtrage
    // Filtration par langue (appliquée aux médecins, avant regroupement)
    if (!empty($search_language)) {
        $sql .= " AND d.language LIKE :language";
        $params[':language'] = '%' . $search_language . '%';
    }

    // Filtration par localisation (appliquée directement aux cliniques)
    if (!empty($search_location)) {
        $sql .= " AND (c.city LIKE :location OR c.address LIKE :location)";
        $params[':location'] = '%' . $search_location . '%';
    }

    // Filtration par spécialité (appliquée en vérifiant l'existence via une sous-requête)
    // Cela garantit que toutes les spécialités d'une clinique sont affichées même si un filtre est actif.
    if (!empty($search_specialty)) {
        $sql .= " AND EXISTS (
            SELECT 1
            FROM doctor_clinic_specialties dcs_sub -- Utilisation de la nouvelle table
            WHERE dcs_sub.clinic_id = c.id
              AND dcs_sub.specialty_id = :specialty_id
        )";
        $params[':specialty_id'] = $search_specialty;
    }

    // GROUP BY toutes les colonnes SELECT non agrégées
    $sql .= " GROUP BY c.id, c.name, c.address, c.phone, c.email, c.description, c.city";
    $sql .= " ORDER BY c.name";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $clinics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Erreur lors de la recherche des cliniques dans MEDECINE/search_clinics.php : " . $e->getMessage());
        $message = "Une erreur est survenue lors de la recherche des cliniques. Veuillez réessayer.";
        $message_type = 'error';
    }
}

// Garder les valeurs des filtres dans le formulaire après soumission
$prev_specialty = $_GET['specialty'] ?? '';
$prev_location = $_GET['location'] ?? '';
$prev_language = $_GET['language'] ?? '';

?>