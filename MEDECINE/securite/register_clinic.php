<?php
// Fichier : MEDECINE/register_clinic.php

session_start();
require_once '../includes/Database.php';

$message = '';
$message_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $clinic_name = htmlspecialchars(trim($_POST['clinic_name']));
    $address = htmlspecialchars(trim($_POST['address']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $website = htmlspecialchars(trim($_POST['website']));
    $city = htmlspecialchars(trim($_POST['city']));
    $country = htmlspecialchars(trim($_POST['country']));
    $description = htmlspecialchars(trim($_POST['description']));

    // Validation basique
    if (empty($email) || empty($password) || empty($confirm_password) || empty($clinic_name) || empty($address)) {
        $message = "Tous les champs obligatoires (email, mot de passe, nom de la clinique, adresse) doivent être remplis.";
        $message_type = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "L'adresse email n'est pas valide.";
        $message_type = 'error';
    } elseif ($password !== $confirm_password) {
        $message = "Les mots de passe ne correspondent pas.";
        $message_type = 'error';
    } elseif (strlen($password) < 6) {
        $message = "Le mot de passe doit contenir au moins 6 caractères.";
        $message_type = 'error';
    } else {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $message = "Cet email est déjà utilisé. Veuillez en choisir un autre.";
                $message_type = 'error';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'clinic';

                $pdo->beginTransaction();

                // 1. Insérer dans la table users
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)");
                $stmt->execute([
                    ':email' => $email,
                    ':password_hash' => $password_hash,
                    ':role' => $role
                ]);
                $user_id = $pdo->lastInsertId();

                // 2. Insérer dans la table clinics
                $stmt = $pdo->prepare("INSERT INTO clinics (user_id, name, address, phone, email, website, city, country, description)
                                    VALUES (:user_id, :name, :address, :phone, :email, :website, :city, :country, :description)");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':name' => $clinic_name,
                    ':address' => $address,
                    ':phone' => $phone,
                    ':email' => $email, // Utilisation de l'email de l'utilisateur pour la clinique
                    ':website' => $website,
                    ':city' => $city,
                    ':country' => $country,
                    ':description' => $description
                ]);

                $pdo->commit();

                $message = "Votre compte clinique a été créé avec succès ! Vous pouvez maintenant vous connecter.";
                $message_type = 'success';
                header('Location: login.php'); 
                exit();
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Une erreur est survenue lors de l'enregistrement : " . $e->getMessage();
            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S'enregistrer - Clinique</title>
    <style>
        /* Réutiliser le style de register_patient.php, ou le mettre dans un fichier CSS externe */
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .register-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 450px; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"],
        input[type="email"],
        input[type="password"],
        textarea {
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
        <h2>S'enregistrer comme Clinique</h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
            <div class="form-group">
                <label for="clinic_name">Nom de la Clinique:</label>
                <input type="text" id="clinic_name" name="clinic_name" required value="<?php echo htmlspecialchars($clinic_name ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="email">Email de la Clinique:</label>
                <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="address">Adresse Complète:</label>
                <textarea id="address" name="address" rows="3" required><?php echo htmlspecialchars($address ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="city">Ville:</label>
                <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="country">Pays:</label>
                <input type="text" id="country" name="country" value="<?php echo htmlspecialchars($country ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="phone">Téléphone de la Clinique:</label>
                <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="website">Site Web (optionnel):</label>
                <input type="text" id="website" name="website" value="<?php echo htmlspecialchars($website ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="description">Description (optionnel):</label>
                <textarea id="description" name="description" rows="5"><?php echo htmlspecialchars($description ?? ''); ?></textarea>
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