<?php
// Fichier : MEDECINE/register_doctor.php

session_start();
require_once '../includes/Database.php';

$message = '';
$message_type = '';

// Variables pour conserver les valeurs du formulaire en cas d'erreur
$email = $_POST['email'] ?? '';
$first_name = $_POST['first_name'] ?? '';
$last_name = $_POST['last_name'] ?? '';
$phone = $_POST['phone'] ?? '';
$bio = $_POST['bio'] ?? '';
$language = $_POST['language'] ?? '';
$selected_specialties = isset($_POST['specialties']) ? (array)$_POST['specialties'] : [];


// Pour les spécialités et cliniques (récupération de la DB)
$specialties = [];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Récupérer les spécialités existantes
    $stmt = $pdo->query("SELECT id, name FROM specialties ORDER BY name");
    $specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    // Gérer l'erreur si la base de données n'est pas accessible
    $message = "Impossible de charger les listes de spécialités ou cliniques: " . $e->getMessage();
    $message_type = 'error';
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupérer et nettoyer les données du formulaire
    $email = htmlspecialchars(trim($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $phone = htmlspecialchars(trim($_POST['phone']));
    $bio = htmlspecialchars(trim($_POST['bio']));
    $language = htmlspecialchars(trim($_POST['language']));
    $selected_specialties = isset($_POST['specialties']) ? (array)$_POST['specialties'] : []; // Array d'IDs

    $profile_picture_url = null; // Initialise à null, sera mis à jour si un fichier est uploadé

    // Validation basique des champs textuels
    if (empty($email) || empty($password) || empty($confirm_password) || empty($first_name) || empty($last_name)) {
        $message = "Tous les champs obligatoires (email, mot de passe, nom, prénom) doivent être remplis.";
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
        // GESTION DE L'UPLOAD DE LA PHOTO DE PROFIL
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['profile_picture']['name'];
            $file_tmp = $_FILES['profile_picture']['tmp_name'];
            $file_size = $_FILES['profile_picture']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $max_file_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file_ext, $allowed_extensions)) {
                $message = "Extension de fichier non autorisée. Seuls JPG, JPEG, PNG, GIF sont permis.";
                $message_type = 'error';
            } elseif ($file_size > $max_file_size) {
                $message = "La taille du fichier est trop grande. Maximum 5MB.";
                $message_type = 'error';
            } else {
                // Générer un nom de fichier unique pour éviter les conflits
                $new_file_name = uniqid('doctor_') . '.' . $file_ext;
                // Assurez-vous que ce chemin est correct par rapport à la racine de votre projet web
                // Si votre structure est `projet/MEDECINE/register_doctor.php`, alors `../uploads/doctors_pics/`
                // pointera vers `projet/uploads/doctors_pics/`
                $upload_dir = '../uploads/doctors_pics/';
                $destination_path = $upload_dir . $new_file_name;

                // Assurez-vous que le répertoire d'upload existe et est accessible en écriture
                if (!is_dir($upload_dir)) {
                    // Tente de créer le répertoire si inexistant, avec permissions 0755
                    if (!mkdir($upload_dir, 0755, true)) {
                        $message = "Impossible de créer le répertoire d'upload. Vérifiez les permissions du serveur.";
                        $message_type = 'error';
                    }
                }

                if ($message_type !== 'error') { // Seulement si la création du répertoire n'a pas échoué
                    if (move_uploaded_file($file_tmp, $destination_path)) {
                        // Stocker le chemin relatif depuis la racine du site web
                        // Par exemple, si le site est sur localhost/, et les uploads dans uploads/,
                        // le chemin sera "uploads/doctors_pics/nom_fichier.jpg"
                        $profile_picture_url = str_replace('../', '', $destination_path); // Enlève '../' pour un chemin web-accessible
                    } else {
                        $message = "Erreur lors du téléchargement de l'image. Code d'erreur: " . $_FILES['profile_picture']['error'];
                        $message_type = 'error';
                    }
                }
            }
        }
        // Fin de la gestion de l'upload

        // Si une erreur d'upload a eu lieu, ou si la validation initiale a échoué,
        // le message_type sera 'error'. On ne continue pas le processus d'enregistrement DB.
        if ($message_type === 'error') {
            // Le message a déjà été défini par la logique d'upload ou de validation
        } else {
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
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'doctor';

                    $pdo->beginTransaction(); // Début de la transaction

                    // 1. Insérer dans la table users
                    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (:email, :password_hash, :role)");
                    $stmt->execute([
                        ':email' => $email,
                        ':password_hash' => $password_hash,
                        ':role' => $role
                    ]);
                    $user_id = $pdo->lastInsertId();

                    // 2. Insérer dans la table doctors (ajout de profile_picture_url)
                    $stmt = $pdo->prepare("INSERT INTO doctors (user_id, first_name, last_name, phone, email, bio, language, profile_picture_url)
                                         VALUES (:user_id, :first_name, :last_name, :phone, :email, :bio, :language, :profile_picture_url)");
                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':first_name' => $first_name,
                        ':last_name' => $last_name,
                        ':phone' => $phone,
                        ':email' => $email,
                        ':bio' => $bio,
                        ':language' => $language,
                        ':profile_picture_url' => $profile_picture_url // Insérer le chemin d'accès ou NULL
                    ]);
                    $doctor_id = $pdo->lastInsertId();

                  
                       

                    if ($message_type !== 'error') { // Si aucune erreur n'a été définie durant le processus
                        $pdo->commit(); // Valider la transaction
                        $message = "Votre compte médecin a été créé avec succès ! Vous pouvez maintenant vous connecter.";
                        $message_type = 'success';
                        // Rediriger après un succès pour éviter la soumission multiple du formulaire
                        header('Location: login.php?registration=success');
                        exit();
                    }

                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack(); // Annuler la transaction en cas d'erreur
                }
                error_log("Erreur lors de l'enregistrement du médecin : " . $e->getMessage()); // Pour le débogage sur le serveur
                $message = "Une erreur est survenue lors de l'enregistrement : " . $e->getMessage();
                $message_type = 'error';
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
    <title>Inscription Médecin | Plateforme Médicale</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #2b7de9;
            --primary-dark: #1a56b7;
            --secondary: #e9f2ff;
            --accent: #00c896;
            --text: #333333;
            --light-text: #6c757d;
            --border: #e0e0e0;
            --white: #ffffff;
            --danger: #e63946;
            --success: #4caf50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: var(--text);
            line-height: 1.6;
        }

        .register-container {
            max-width: 1000px;
            margin: 40px auto;
            background: var(--white);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            display: flex;
            min-height: 80vh;
        }

        .register-hero {
            flex: 1;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .register-hero::before {
            content: "";
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .register-hero::after {
            content: "";
            position: absolute;
            bottom: -80px;
            left: -30px;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .register-hero h2 {
            font-size: 2.2rem;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .register-hero p {
            margin-bottom: 30px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .hero-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--accent);
            position: relative;
            z-index: 1;
        }

        .register-form {
            flex: 1.2;
            padding: 50px;
            background: var(--white);
        }

        .form-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .form-header h2 {
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .form-header p {
            color: var(--light-text);
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text);
            font-size: 0.9rem;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(43, 125, 233, 0.2);
            outline: none;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group select[multiple] {
            height: auto;
            min-height: 120px;
            padding: 10px;
        }

        .form-group select[multiple] option {
            padding: 8px 12px;
            margin: 2px 0;
            border-radius: 4px;
        }

        .form-group select[multiple] option:hover {
            background-color: var(--secondary);
        }

        .file-upload {
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .file-upload input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            border: 1px dashed var(--border);
            border-radius: 8px;
            background: var(--secondary);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            border-color: var(--primary);
            background: rgba(43, 125, 233, 0.05);
        }

        .file-upload-label i {
            margin-right: 10px;
            color: var(--primary);
        }

        button[type="submit"] {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        button[type="submit"]:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(43, 125, 233, 0.3);
        }

        .login-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
            color: var(--light-text);
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }

        .message i {
            margin-right: 10px;
        }

        .message.error {
            background-color: #fdecea;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .message.success {
            background-color: #edf7ed;
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .password-strength {
            margin-top: 5px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s ease;
        }

        .strength-weak {
            background: #ff5252;
        }

        .strength-medium {
            background: #ffb74d;
        }

        .strength-strong {
            background: #4caf50;
        }

        @media (max-width: 768px) {
            .register-container {
                flex-direction: column;
                margin: 20px;
            }

            .register-hero {
                padding: 30px 20px;
            }

            .register-form {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-hero">
            <div class="hero-icon">
                <i class="fas fa-user-md"></i>
            </div>
            <h2>Rejoignez notre réseau médical</h2>
            <p>Connectez-vous avec des patients, gérez votre agenda et développez votre pratique médicale avec notre plateforme sécurisée.</p>
            <div class="benefits">
                <p><i class="fas fa-check-circle"></i> Profil professionnel vérifié</p>
                <p><i class="fas fa-check-circle"></i> Gestion simplifiée des rendez-vous</p>
                <p><i class="fas fa-check-circle"></i> Accès à un large réseau de patients</p>
            </div>
        </div>

        <div class="register-form">
            <div class="form-header">
                <h2>Créer votre compte médecin</h2>
                <p>Remplissez les informations ci-dessous pour vous inscrire</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas <?php echo $message_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="first_name">Prénom*</label>
                        <input type="text" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($first_name); ?>" placeholder="Votre prénom">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="last_name">Nom*</label>
                        <input type="text" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($last_name); ?>" placeholder="Votre nom">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email professionnel*</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>" placeholder="exemple@votreclinique.com">
                </div>

                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="phone">Téléphone</label>
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="+33 6 12 34 56 78">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="language">Langues parlées</label>
                        <input type="text" id="language" name="language" value="<?php echo htmlspecialchars($language); ?>" placeholder="Français, Anglais...">
                    </div>
                </div>

                <div class="form-group">
                    <label for="bio">Biographie / Spécialisation</label>
                    <textarea id="bio" name="bio" rows="5" placeholder="Décrivez votre parcours, vos spécialisations et votre approche médicale..."><?php echo htmlspecialchars($bio); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Photo de profil</label>
                    <div class="file-upload">
                        <label class="file-upload-label" for="profile_picture">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Choisir une image (JPG, PNG - max 5MB)</span>
                        </label>
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg, image/png, image/gif">
                    </div>
                </div>

                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="specialties">Spécialités*</label>
                        <select id="specialties" name="specialties[]" multiple required>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?php echo htmlspecialchars($specialty['id']); ?>"
                                    <?php echo (in_array($specialty['id'], $selected_specialties)) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($specialty['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="display: block; margin-top: 5px; color: var(--light-text);">Maintenez Ctrl (Windows) ou Cmd (Mac) pour sélectionner plusieurs</small>
                    </div>
           

                <div class="form-row" style="display: flex; gap: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label for="password">Mot de passe*</label>
                        <input type="password" id="password" name="password" required placeholder="Minimum 6 caractères">
                        <div class="password-strength">
                            <div class="strength-bar" id="strengthBar"></div>
                        </div>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label for="confirm_password">Confirmer le mot de passe*</label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Retapez votre mot de passe">
                    </div>
                </div>

                <button type="submit">Finaliser l'inscription</button>
            </form>

            <div class="login-link">
                Vous avez déjà un compte ? <a href="login.php">Connectez-vous ici</a>
            </div>
        </div>
    </div>

    <script>
        // Animation pour la force du mot de passe
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('strengthBar');

        passwordInput.addEventListener('input', function() {
            const strength = calculatePasswordStrength(this.value);
            updateStrengthBar(strength);
        });

        function calculatePasswordStrength(password) {
            let strength = 0;
            
            // Longueur minimale
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            
            // Contient des lettres majuscules et minuscules
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 1;
            
            // Contient des chiffres
            if (/\d/.test(password)) strength += 1;
            
            // Contient des caractères spéciaux
            if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
            
            return Math.min(strength, 3); // Max 3 niveaux de force
        }

        function updateStrengthBar(strength) {
            strengthBar.style.width = '0%';
            strengthBar.className = 'strength-bar';
            
            setTimeout(() => {
                const width = strength * 33.33;
                strengthBar.style.width = width + '%';
                
                if (strength === 0) {
                    strengthBar.className = 'strength-bar';
                } else if (strength === 1) {
                    strengthBar.className = 'strength-bar strength-weak';
                } else if (strength === 2) {
                    strengthBar.className = 'strength-bar strength-medium';
                } else {
                    strengthBar.className = 'strength-bar strength-strong';
                }
            }, 10);
        }
    </script>
</body>
</html>