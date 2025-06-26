<?php
require_once '../../includes/auth_check.php';

require_once '../../includes/patient/Messaging_functions.php';// Inclure la classe Messaging pour le compteur de messages

require_once '../../includes/Database.php'; // Inclure la connexion à la base de données



$user_id = $_SESSION['user_id'];
$message = '';
$message_type = ''; // 'success' ou 'error'

// Récupérer les données actuelles du patient
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $stmt = $pdo->prepare("
        SELECT
            u.email,
            p.first_name,
            p.last_name,
            p.phone,
            p.date_of_birth,
            p.gender,
            p.address,
            p.profile_picture
        FROM users u
        JOIN patients p ON u.id = p.user_id
        WHERE u.id = :user_id
    ");
    $stmt->execute([':user_id' => $user_id]);
    $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patient_data) {
        $message = "Impossible de charger les données de votre profil.";
        $message_type = 'error';
    }

} catch (PDOException $e) {
    $message = "Erreur de base de données lors du chargement : " . $e->getMessage();
    $message_type = 'error';
}

// Traiter la soumission du formulaire de modification
if ($_SERVER["REQUEST_METHOD"] == "POST" && $message_type !== 'error') {
    // Récupérer les données du formulaire
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $date_of_birth = htmlspecialchars(trim($_POST['date_of_birth'] ?? ''));
    $gender = htmlspecialchars(trim($_POST['gender'] ?? ''));
    $address = htmlspecialchars(trim($_POST['address'] ?? ''));

    // Conserver le chemin de l'ancienne image par défaut
    $profile_picture_path = $patient_data['profile_picture'];

    // --- Validation basique côté serveur ---
    if (empty($first_name) || empty($last_name)) {
        $message = "Les champs Prénom et Nom sont obligatoires.";
        $message_type = 'error';
    } else {
        // Gérer le téléchargement de la nouvelle image si elle est présente
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5 Mo

            if (in_array($_FILES['profile_picture']['type'], $allowed_types) && $_FILES['profile_picture']['size'] <= $max_size) {
                $upload_dir = '../../uploads/profile_images/'; // Chemin d'upload
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $new_file_name = uniqid('profile_', true) . '.' . $file_extension;
                $target_file = $upload_dir . $new_file_name;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                    // Supprimer l'ancienne image si elle existe et n'est pas l'image par défaut
                    if ($profile_picture_path && $profile_picture_path !== 'default.jpg' && file_exists($upload_dir . $profile_picture_path)) {
                        unlink($upload_dir . $profile_picture_path);
                    }
                    $profile_picture_path = $new_file_name; // Mettre à jour le chemin de l'image
                } else {
                    $message = "Erreur lors du téléchargement de la nouvelle image.";
                    $message_type = 'error';
                }
            } else {
                $message = "Type de fichier non autorisé ou taille de fichier trop grande (max 5MB, JPG, PNG, GIF autorisés).";
                $message_type = 'error';
            }
        }
    }

    // Si aucune erreur jusqu'ici, procéder à la mise à jour de la base de données
    if ($message_type !== 'error') {
        try {
            $pdo->beginTransaction();

            // Mettre à jour la table patients
            $stmt = $pdo->prepare("
                UPDATE patients
                SET
                    first_name = :first_name,
                    last_name = :last_name,
                    phone = :phone,
                    date_of_birth = :date_of_birth,
                    gender = :gender,
                    address = :address,
                    profile_picture = :profile_picture
                WHERE user_id = :user_id
            ");
            $stmt->execute([
                ':first_name' => $first_name,
                ':last_name' => $last_name,
                ':phone' => $phone,
                ':date_of_birth' => $date_of_birth,
                ':gender' => $gender,
                ':address' => $address,
                ':profile_picture' => $profile_picture_path,
                ':user_id' => $user_id
            ]);

            $pdo->commit();
            $message = "Votre profil a été mis à jour avec succès !";
            $message_type = 'success';

            // Recharger les données pour que le formulaire affiche les nouvelles informations
            // Ceci est important après une mise à jour réussie
            $stmt = $pdo->prepare("
                SELECT
                    u.email,
                    p.first_name,
                    p.last_name,
                    p.phone,
                    p.date_of_birth,
                    p.gender,
                    p.address,
                    p.profile_picture
                FROM users u
                JOIN patients p ON u.id = p.user_id
                WHERE u.id = :user_id
            ");
            $stmt->execute([':user_id' => $user_id]);
            $patient_data = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "Une erreur est survenue lors de la mise à jour du profil : " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Définir le chemin de l'image de profil pour l'affichage dans le formulaire
$display_profile_picture_src = '../../uploads/profile_images/' . ($patient_data['profile_picture'] ?? 'default.jpg');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier mon Profil Patient</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; }
        .edit-profile-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 600px; margin: 30px auto; }
        h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; }
        input[type="text"],
        input[type="email"],
        input[type="date"],
        select,
        textarea {
            width: calc(100% - 22px); /* Ajusté pour le padding/border */
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
        }
        .profile-picture-preview {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .profile-picture-preview img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid #2a9d8f;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #2a9d8f;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover { background-color: #264653; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .back-link { display: block; text-align: center; margin-top: 20px; color: #007bff; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php include '../../includes/patient/entete.php'; // Inclure l'en-tête ?>

    <div class="edit-profile-container">
        <h2>Modifier mon Profil</h2>

        <?php if (!empty($message)): ?>
            <div class="message <?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($patient_data)): ?>
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="profile_picture">Image de profil actuelle:</label>
                    <div class="profile-picture-preview">
                        <img src="<?php echo htmlspecialchars($display_profile_picture_src); ?>" alt="Image de profil actuelle">
                        <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/gif">
                    </div>
                </div>

                <div class="form-group">
                    <label for="first_name">Prénom:</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($patient_data['first_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Nom:</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($patient_data['last_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Téléphone:</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($patient_data['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date de naissance:</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($patient_data['date_of_birth'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="gender">Sexe:</label>
                    <select id="gender" name="gender">
                        <option value="">Sélectionner</option>
                        <option value="male" <?php echo (isset($patient_data['gender']) && $patient_data['gender'] == 'male') ? 'selected' : ''; ?>>Homme</option>
                        <option value="female" <?php echo (isset($patient_data['gender']) && $patient_data['gender'] == 'female') ? 'selected' : ''; ?>>Femme</option>
                        <option value="other" <?php echo (isset($patient_data['gender']) && $patient_data['gender'] == 'other') ? 'selected' : ''; ?>>Autre</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="address">Adresse:</label>
                    <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($patient_data['address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="email">Email (non modifiable):</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($patient_data['email'] ?? ''); ?>" disabled>
                </div>

                <button type="submit">Enregistrer les modifications</button>
            </form>
        <?php endif; ?>
        <a href="patient_profile.php" class="back-link">Retour au profil</a>
    </div>
</body>
</html>