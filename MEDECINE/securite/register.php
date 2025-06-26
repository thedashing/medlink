<?php
// Fichier : MEDECINE/register.php

// Inclure le fichier de connexion à la base de données
require_once '../includes/Database.php';

// Initialiser les variables pour les messages d'erreur/succès
$message = '';
$message_type = ''; // 'success' ou 'error'

// Si le formulaire est soumis (méthode POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupérer les données du formulaire
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $date_of_birth = htmlspecialchars(trim($_POST['date_of_birth']));
    $gender = htmlspecialchars(trim($_POST['gender']));

    // Variable pour le chemin de l'image
    $profile_image_path = null;

    // --- Validation basique côté serveur ---
    if (empty($email) || empty($password) || empty($confirm_password) || empty($first_name) || empty($last_name)) {
        $message = "Tous les champs obligatoires (email, mot de passe, nom, prénom) doivent être remplis.";
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "L'adresse email n'est pas valide.";
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = "Les mots de passe ne correspondent pas.";
        $message_type = 'error';
    } elseif (strlen($password) < 6) { // Ex: mot de passe d'au moins 6 caractères
        $message = "Le mot de passe doit contenir au moins 6 caractères.";
        $message_type = 'error';
    } else {
        // Gérer le téléchargement de fichiers
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5 Mo

            if (in_array($_FILES['profile_image']['type'], $allowed_types) && $_FILES['profile_image']['size'] <= $max_size) {
                $upload_dir = '../uploads/profile_images/'; // Créez ce répertoire s'il n'existe pas
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true); // Crée le répertoire récursivement avec toutes les permissions
                }

                $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
                $new_file_name = uniqid('profile_', true) . '.' . $file_extension;
                $target_file = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_file)) {
                    $profile_image_path = $new_file_name; // Stocke uniquement le nom du fichier dans la base de données
                } else {
                    $message = "Erreur lors du téléchargement de l'image.";
                    $message_type = 'error';
                }
            } else {
                $message = "Type de fichier non autorisé ou taille de fichier trop grande (max 5MB, JPG, PNG, GIF autorisés).";
                $message_type = 'error';
            }
        }

        // Continuer seulement s'il n'y a pas eu d'erreurs de téléchargement de fichier, ou si aucun fichier n'a été téléchargé
        if ($message_type !== 'error' || !isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] == 4) { // Erreur 4 signifie qu'aucun fichier n'a été téléchargé
            try {
                $db = Database::getInstance();
                $pdo = $db->getConnection();

                // Vérifier si l'email existe déjà
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute([':email' => $email]);
                if ($stmt->fetch()) {
                    $message = "Cet email est déjà utilisé. Veuillez en choisir un autre.";
                    $message_type = 'error';
                } else {
                    // Hacher le mot de passe
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'patient'; // Le rôle est fixé pour cet exemple d'enregistrement de patient

                    $pdo->beginTransaction(); // Début de la transaction pour assurer l'atomicité

                    // 1. Insérer dans la table users
                    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)");
                    $stmt->execute([
                        ':email' => $email,
                        ':password_hash' => $password_hash,
                        ':role' => $role
                    ]);
                    $user_id = $pdo->lastInsertId(); // Récupérer l'ID du nouvel utilisateur

                    // 2. Insérer dans la table patients
                    $stmt = $pdo->prepare("INSERT INTO patients (user_id, first_name, last_name, phone, date_of_birth, gender,profile_picture)
                                         VALUES (:user_id, :first_name, :last_name, :phone, :date_of_birth, :gender, :profile_image)");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':first_name' => $first_name,
                        ':last_name' => $last_name,
                        ':phone' => $phone,
                        ':date_of_birth' => $date_of_birth,
                        ':gender' => $gender,
                        ':profile_image' => $profile_image_path // Ajoutez le chemin de l'image de profil ici
                    ]);

                    $pdo->commit(); // Valider la transaction

                    $message = "Votre compte patient a été créé avec succès ! Vous pouvez maintenant vous connecter.";
                    $message_type = 'success';
                    // Rediriger ou effacer le formulaire
                    // header('Location: login.php'); // Rediriger vers la page de connexion
                    // exit();
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack(); // Annuler la transaction en cas d'erreur
                }
                $message = "Une erreur est survenue lors de l'enregistrement : " . $e->getMessage();
                $message_type = 'error';
                // En production, loguer l'erreur et afficher un message générique.
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S'enregistrer - Patient</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .register-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 400px; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="date"],
        select {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover { background-color: #0056b3; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .login-link { text-align: center; margin-top: 20px; }
        .login-link a { color: #007bff; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>S'enregistrer comme Patient</h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="first_name">Prénom:</label>
                <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="last_name">Nom:</label>
                <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="phone">Téléphone:</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="date_of_birth">Date de naissance:</label>
                <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($date_of_birth ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="gender">Sexe:</label>
                <select id="gender" name="gender">
                    <option value="">Sélectionner</option>
                    <option value="male" <?php echo (isset($gender) && $gender == 'male') ? 'selected' : ''; ?>>Homme</option>
                    <option value="female" <?php echo (isset($gender) && $gender == 'female') ? 'selected' : ''; ?>>Femme</option>
                    <option value="other" <?php echo (isset($gender) && $gender == 'other') ? 'selected' : ''; ?>>Autre</option>
                </select>
            </div>
            <div class="form-group">
                <label for="profile_image">Image de profil:</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmer le mot de passe:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit">S'enregistrer</button>
        </form>
        <div class="login-link">
            Déjà un compte ? <a href="login.php">Connectez-vous ici</a>
        </div>
    </div>
</body>
</html>