<?php
// Fichier : MEDECINE/search.php

session_start();
require_once 'includes/Database.php';
require_once 'includes/auth_check.php';

// S'assurer que seul un patient peut accéder à cette page (ou un utilisateur non connecté pour une recherche publique)
// Pour l'instant, on va permettre aux non-connectés de chercher aussi, mais la prise de RDV nécessitera la connexion.
// Si vous voulez que la recherche soit uniquement pour les patients connectés:
// require_login('patient'); // Décommenter si la recherche n'est que pour les patients connectés

$doctors = []; // Variable pour stocker les résultats de la recherche
$specialties = []; // Pour la liste déroulante des spécialités

// Récupérer les spécialités pour le filtre
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $stmt = $pdo->query("SELECT id, name FROM specialties ORDER BY name");
    $specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // En production, loguer l'erreur
    // echo "Erreur lors du chargement des spécialités : " . $e->getMessage();
}

// Traitement de la recherche quand le formulaire est soumis
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search'])) {
    $search_specialty = htmlspecialchars(trim($_GET['specialty'] ?? ''));
    $search_location = htmlspecialchars(trim($_GET['location'] ?? '')); // Peut être la ville ou l'adresse de la clinique
    $search_language = htmlspecialchars(trim($_GET['language'] ?? ''));

    // Construction de la requête SQL dynamique
    $sql = "SELECT
                d.id AS doctor_id,
                d.first_name,
                d.last_name,
                d.bio,
                d.language,
                cl.name AS clinic_name,
                cl.address AS clinic_address,
                GROUP_CONCAT(s.name SEPARATOR ', ') AS doctor_specialties
            FROM
                doctors d
            JOIN
                clinic_doctors cd ON d.id = cd.doctor_id
            JOIN
                clinics cl ON cd.clinic_id = cl.id
            LEFT JOIN
                doctor_specialties ds ON d.id = ds.doctor_id
            LEFT JOIN
                specialties s ON ds.specialty_id = s.id
            WHERE 1=1"; // Clause WHERE de base pour faciliter l'ajout des conditions

    $params = [];

    if (!empty($search_specialty)) {
        // Recherche par spécialité (ID de la spécialité)
        $sql .= " AND s.id = :specialty_id";
        $params[':specialty_id'] = $search_specialty;
    }

    if (!empty($search_location)) {
        // Recherche par localisation (ville ou partie de l'adresse de la clinique)
        $sql .= " AND (cl.city LIKE :location OR cl.address LIKE :location)";
        $params[':location'] = '%' . $search_location . '%';
    }

    if (!empty($search_language)) {
        // Recherche par langue parlée (utiliser LIKE pour correspondance partielle)
        $sql .= " AND d.language LIKE :language";
        $params[':language'] = '%' . $search_language . '%';
    }

    $sql .= " GROUP BY d.id, d.first_name, d.last_name, d.bio, d.language, cl.name, cl.address";
    $sql .= " ORDER BY d.last_name, d.first_name";


    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // En production, loguer l'erreur au lieu de l'afficher
        $message = "Erreur lors de la recherche : " . $e->getMessage();
        $message_type = 'error';
    }
}

// Garder les valeurs des filtres dans le formulaire après soumission
$prev_specialty = $_GET['specialty'] ?? '';
$prev_location = $_GET['location'] ?? '';
$prev_language = $_GET['language'] ?? '';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recherche de Médecins</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; }
        .container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 900px; margin: 20px auto; }
        h1, h2 { color: #333; text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"], select {
            width: calc(100% - 22px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
        }
        button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover { background-color: #0056b3; }
        .search-form { display: grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 15px; margin-bottom: 30px; }
        .doctor-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #fdfdfd;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .doctor-card h3 { color: #007bff; margin-top: 0; margin-bottom: 10px; }
        .doctor-card p { margin: 5px 0; color: #666; }
        .doctor-card .clinic-info { font-size: 0.9em; color: #888; }
        .doctor-card .specialties { font-style: italic; font-size: 0.9em; color: #555; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .no-results { text-align: center; padding: 30px; color: #777; }
        .nav-links { text-align: center; margin-bottom: 20px; }
        .nav-links a { margin: 0 10px; text-decoration: none; color: #007bff; }
        .nav-links a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-links">
            <?php if (is_logged_in()): ?>
                <p>Connecté en tant que: <?php echo htmlspecialchars(get_user_email()); ?> (<?php echo htmlspecialchars(get_user_role()); ?>)</p>
                <a href="dashboard_<?php echo get_user_role(); ?>.php">Mon Tableau de Bord</a> |
                <a href="app/patient/logout.php">Se déconnecter</a>
            <?php else: ?>
                <p>Non connecté.</p>
                <a href="login.php">Se connecter</a> |
                <a href="index.php">S'inscrire</a>
            <?php endif; ?>
        </div>

        <h1>Rechercher un Médecin</h1>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="GET" class="search-form">
            <div class="form-group">
                <label for="specialty">Spécialité:</label>
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
                <label for="location">Localisation (Ville/Adresse):</label>
                <input type="text" id="location" name="location" placeholder="Ex: Paris, Boulevard Voltaire" value="<?php echo htmlspecialchars($prev_location); ?>">
            </div>
            <div class="form-group">
                <label for="language">Langue parlée:</label>
                <input type="text" id="language" name="language" placeholder="Ex: Français, Anglais" value="<?php echo htmlspecialchars($prev_language); ?>">
            </div>
            <button type="submit" name="search">Rechercher</button>
        </form>

        <h2>Résultats de la Recherche</h2>
        <?php if (empty($doctors)): ?>
            <div class="no-results">
                <p>Aucun médecin trouvé correspondant à vos critères.</p>
                <?php if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['search'])): ?>
                    <p>Essayez d'ajuster vos filtres de recherche.</p>
                <?php else: ?>
                    <p>Utilisez le formulaire ci-dessus pour lancer votre recherche.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="results-list">
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-card">
                        <h3>Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>
                        <p><strong>Spécialités:</strong> <span class="specialties"><?php echo htmlspecialchars($doctor['doctor_specialties'] ?: 'Non spécifié'); ?></span></p>
                        <p><strong>Clinique:</strong> <span class="clinic-info"><?php echo htmlspecialchars($doctor['clinic_name']); ?> - <?php echo htmlspecialchars($doctor['clinic_address']); ?></span></p>
                        <p><strong>Langues parlées:</strong> <?php echo htmlspecialchars($doctor['language'] ?: 'Non spécifié'); ?></p>
                        <p><?php echo nl2br(htmlspecialchars($doctor['bio'] ?: 'Pas de biographie disponible.')); ?></p>
                        <a href="book_appointment.php?doctor_id=<?php echo $doctor['doctor_id']; ?>" class="button">Prendre rendez-vous</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>