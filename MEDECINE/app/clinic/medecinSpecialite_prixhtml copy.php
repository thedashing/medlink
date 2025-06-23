

<?php




// Fichier : MEDECINE/medecinSpecialite_prix.php

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';

require_login('clinic'); // Assurez-vous que seules les cliniques connectées peuvent y accéder

$user_id = htmlspecialchars($_SESSION['user_id']); // ID utilisateur de la clinique connectée

$clinic_id = null;
$doctors = [];
$available_specialties = []; // All available specialties
$doctor_assigned_specialties = []; // Specialties assigned to each doctor
$doctor_offered_services = []; // Services each doctor offers (from 'services' table)
$general_service_types = []; // General service types from a conceptual 'master services' table (if you had one)
                              // For now, we'll list common names or assume clinic creates them.

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // 1. Récupérer l'ID de la clinique pour l'utilisateur connecté
    $stmt = $pdo->prepare("SELECT id FROM clinics WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $user_id]);
    $clinic_info = $stmt->fetch();
    if ($clinic_info) {
        $clinic_id = $clinic_info['id'];
    } else {
        die("Clinique introuvable pour cet utilisateur.");
    }

    // 2. Récupérer tous les médecins associés à cette clinique
    $stmt = $pdo->prepare("SELECT d.id, d.first_name, d.last_name FROM doctors d JOIN clinic_doctors cd ON d.id = cd.doctor_id WHERE cd.clinic_id = :clinic_id");
    $stmt->execute([':clinic_id' => $clinic_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Récupérer toutes les spécialités disponibles
    $stmt = $pdo->query("SELECT id, name FROM specialties ORDER BY name");
    $available_specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Récupérer les spécialités déjà assignées à chaque médecin
    foreach ($doctors as $doctor) {
        $stmt = $pdo->prepare("SELECT s.id, s.name FROM doctor_specialties ds JOIN specialties s ON ds.specialty_id = s.id WHERE ds.doctor_id = :doctor_id");
        $stmt->execute([':doctor_id' => $doctor['id']]);
        $doctor_assigned_specialties[$doctor['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. Récupérer les services offerts par chaque médecin (avec leurs prix et durées)
    // Nous utilisons la table 'services' où le clinic_id est pertinent.
    $stmt = $pdo->prepare("
        SELECT
            s.id AS service_id,
            s.doctor_id,
            s.name AS service_name,
            s.description,
            s.price,
            s.duration_minutes
        FROM
            services s
        WHERE
            s.clinic_id = :clinic_id
        ORDER BY s.doctor_id, s.name
    ");
    $stmt->execute([':clinic_id' => $clinic_id]);
    $all_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize services by doctor_id
    foreach ($all_services as $service) {
        if (!isset($doctor_offered_services[$service['doctor_id']])) {
            $doctor_offered_services[$service['doctor_id']] = [];
        }
        $doctor_offered_services[$service['doctor_id']][] = $service;
    }


    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // --- Action: Add/Update Doctor's Specialties ---
        if ($_POST['action'] === 'manage_doctor_specialties') {
            $selected_doctor_id = htmlspecialchars($_POST['doctor_id_specialty']);
            $new_specialty_ids = isset($_POST['specialty_ids']) ? $_POST['specialty_ids'] : [];

            // Basic validation
            if (empty($selected_doctor_id)) {
                $error_message = "Veuillez sélectionner un médecin.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. Check if the doctor belongs to this clinic for security
                    $stmt_check_doctor = $pdo->prepare("SELECT id FROM doctors d JOIN clinic_doctors cd ON d.id = cd.doctor_id WHERE d.id = :doctor_id AND cd.clinic_id = :clinic_id");
                    $stmt_check_doctor->execute([':doctor_id' => $selected_doctor_id, ':clinic_id' => $clinic_id]);
                    if (!$stmt_check_doctor->fetch()) {
                        $error_message = "Sélection de médecin invalide.";
                        $pdo->rollBack();
                    } else {
                        // 2. Remove existing specialties for this doctor
                        $stmt_delete = $pdo->prepare("DELETE FROM doctor_specialties WHERE doctor_id = :doctor_id");
                        $stmt_delete->execute([':doctor_id' => $selected_doctor_id]);

                        // 3. Add new specialties
                        if (!empty($new_specialty_ids)) {
                            $insert_values = [];
                            $placeholders = [];
                            foreach ($new_specialty_ids as $specialty_id) {
                                $placeholders[] = "(?, ?)";
                                $insert_values[] = $selected_doctor_id;
                                $insert_values[] = htmlspecialchars($specialty_id);
                            }
                            $stmt_insert = $pdo->prepare("INSERT INTO doctor_specialties (doctor_id, specialty_id) VALUES " . implode(", ", $placeholders));
                            $stmt_insert->execute($insert_values);
                        }
                        $pdo->commit();
                        $success_message = "Spécialités du médecin mises à jour avec succès.";
                        // Refresh the specialties list
                        header("Location: medecinSpecialite_prix.php");
                        exit();
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Erreur lors de la mise à jour des spécialités: " . $e->getMessage());
                    $error_message = "Erreur lors de la mise à jour des spécialités.";
                }
            }
        }

        // --- Action: Add New Service for a Doctor ---
        if ($_POST['action'] === 'add_doctor_service') {
            $selected_doctor_id = htmlspecialchars($_POST['doctor_id_service']);
            $service_name = htmlspecialchars($_POST['service_name']);
            $service_description = htmlspecialchars($_POST['service_description']);
            $price = floatval($_POST['price']);
            $duration_minutes = intval($_POST['duration_minutes']);

            // Basic validation
            if (empty($selected_doctor_id) || empty($service_name) || empty($price) || empty($duration_minutes)) {
                $error_message = "Tous les champs pour le service sont requis.";
            } else {
                try {
                    // Check if the doctor belongs to this clinic for security
                    $stmt_check_doctor = $pdo->prepare("SELECT id FROM doctors d JOIN clinic_doctors cd ON d.id = cd.doctor_id WHERE d.id = :doctor_id AND cd.clinic_id = :clinic_id");
                    $stmt_check_doctor->execute([':doctor_id' => $selected_doctor_id, ':clinic_id' => $clinic_id]);
                    if ($stmt_check_doctor->fetch()) {
                        // Insert a new service into the 'services' table.
                        // The 'services' table has clinic_id, doctor_id, name, description, price, duration_minutes.
                        $stmt_insert_service = $pdo->prepare("
                            INSERT INTO services (clinic_id, doctor_id, name, description, price, duration_minutes)
                            VALUES (:clinic_id, :doctor_id, :name, :description, :price, :duration_minutes)
                        ");
                        $success = $stmt_insert_service->execute([
                            ':clinic_id' => $clinic_id,
                            ':doctor_id' => $selected_doctor_id,
                            ':name' => $service_name,
                            ':description' => $service_description,
                            ':price' => $price,
                            ':duration_minutes' => $duration_minutes
                        ]);

                        if ($success) {
                            $success_message = "Service ajouté avec succès pour le médecin.";
                            header("Location: medecinSpecialite_prix.php"); // Redirect to prevent re-submission
                            exit();
                        } else {
                            $error_message = "Erreur lors de l'ajout du service.";
                        }
                    } else {
                        $error_message = "Sélection de médecin invalide.";
                    }
                } catch (PDOException $e) {
                    error_log("Erreur lors de l'ajout du service: " . $e->getMessage());
                    $error_message = "Une erreur de base de données est survenue lors de l'ajout du service.";
                }
            }
        }
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error_message = "Une erreur de base de données est survenue. Veuillez réessayer plus tard.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer Spécialités et Services des Médecins</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f0fff0; padding: 20px; }
        .header { background-color: #28a745; color: white; padding: 15px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; }
        .header a { color: white; text-decoration: none; padding: 8px 15px; border: 1px solid white; border-radius: 4px; }
        .header a:hover { background-color: #218838; }
        .content { background-color: #ffffff; padding: 20px; margin-top: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .section-title { color: #28a745; margin-top: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .form-group select[multiple] { height: 100px; }
        .form-group button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .form-group button:hover { background-color: #0056b3; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; }
        .doctor-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
        }
        .doctor-card h3 { margin-top: 0; color: #007bff; }
        .doctor-card ul { list-style: none; padding: 0; margin: 5px 0;}
        .doctor-card ul li { margin-bottom: 3px; }
        .tab-buttons { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-button {
            padding: 10px 15px;
            background-color: #eee;
            border: 1px solid #ccc;
            border-radius: 5px;
            cursor: pointer;
        }
        .tab-button.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        .tab-content { border: 1px solid #ccc; padding: 20px; border-radius: 5px; background-color: #fff; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Gérer Spécialités et Services des Médecins</h1>
        <a href="dashboard_clinic.php">Retour au Tableau de Bord</a>
    </div>

    <div class="content">
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="tab-buttons">
            <button class="tab-button active" onclick="showTab('listDoctors')">Liste des Médecins et Services</button>
            <button class="tab-button" onclick="showTab('addSpecialty')">Gérer les Spécialités des Médecins</button>
            <button class="tab-button" onclick="showTab('addService')">Ajouter un Service à un Médecin</button>
        </div>

        <div id="listDoctors" class="tab-content">
            <h2 class="section-title">Aperçu des Médecins, Spécialités et Services</h2>
            <?php if (empty($doctors)): ?>
                <p>Aucun médecin n'est enregistré pour cette clinique.</p>
            <?php else: ?>
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-card">
                        <h3><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>

                        <h4>Spécialités :</h4>
                        <?php if (!empty($doctor_assigned_specialties[$doctor['id']])): ?>
                            <ul>
                                <?php foreach ($doctor_assigned_specialties[$doctor['id']] as $specialty): ?>
                                    <li><?php echo htmlspecialchars($specialty['name']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>Aucune spécialité assignée.</p>
                        <?php endif; ?>

                        <h4>Services offerts :</h4>
                        <?php if (!empty($doctor_offered_services[$doctor['id']])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom du Service</th>
                                        <th>Description</th>
                                        <th>Prix</th>
                                        <th>Durée (min)</th>
                                        </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($doctor_offered_services[$doctor['id']] as $service): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                            <td><?php echo htmlspecialchars($service['description'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(number_format($service['price'], 2)) . ' €'; ?></td>
                                            <td><?php echo htmlspecialchars($service['duration_minutes']); ?></td>
                                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>Aucun service spécifique n'est offert par ce médecin.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="addSpecialty" class="tab-content" style="display:none;">
            <h2 class="section-title">Gérer les Spécialités des Médecins</h2>
            <form method="POST">
                <input type="hidden" name="action" value="manage_doctor_specialties">
                <div class="form-group">
                    <label for="doctor_id_specialty">Sélectionner un Médecin :</label>
                    <select id="doctor_id_specialty" name="doctor_id_specialty" required onchange="loadDoctorSpecialties(this.value)">
                        <option value="">-- Choisir un médecin --</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo htmlspecialchars($doctor['id']); ?>">
                                <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="specialty_ids">Assigner des Spécialités (maintenez Ctrl/Cmd pour sélectionner plusieurs) :</label>
                    <select id="specialty_ids" name="specialty_ids[]" multiple required style="height: 200px;">
                        <?php foreach ($available_specialties as $specialty): ?>
                            <option value="<?php echo htmlspecialchars($specialty['id']); ?>">
                                <?php echo htmlspecialchars($specialty['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit">Mettre à jour les Spécialités</button>
                </div>
            </form>
        </div>

        <div id="addService" class="tab-content" style="display:none;">
            <h2 class="section-title">Ajouter un Service Spécifique à un Médecin</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_doctor_service">
                <div class="form-group">
                    <label for="doctor_id_service">Sélectionner un Médecin :</label>
                    <select id="doctor_id_service" name="doctor_id_service" required>
                        <option value="">-- Choisir un médecin --</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo htmlspecialchars($doctor['id']); ?>">
                                <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="service_name">Nom du Service :</label>
                    <input type="text" id="service_name" name="service_name" required placeholder="Ex: Consultation initiale, Bilan sanguin">
                </div>

                <div class="form-group">
                    <label for="service_description">Description du Service (optionnel) :</label>
                    <textarea id="service_description" name="service_description" rows="3" placeholder="Décrivez brièvement le service..."></textarea>
                </div>

                <div class="form-group">
                    <label for="price">Prix du Service (EUR) :</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required placeholder="Ex: 50.00">
                </div>

                <div class="form-group">
                    <label for="duration_minutes">Durée du Service (minutes) :</label>
                    <input type="number" id="duration_minutes" name="duration_minutes" min="5" required placeholder="Ex: 30">
                </div>

                <div class="form-group">
                    <button type="submit">Ajouter le Service au Médecin</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            document.getElementById(tabId).style.display = 'block';
            document.querySelector(`.tab-button[onclick="showTab('${tabId}')"`).classList.add('active');
        }

        // JavaScript to pre-select specialties when a doctor is chosen
        function loadDoctorSpecialties(doctorId) {
            // This would typically involve an AJAX call to fetch specialties for the selected doctor
            // For now, we'll use the pre-loaded PHP data if possible, or clear if not.
            const specialtySelect = document.getElementById('specialty_ids');
            // Clear previous selections
            for (let i = 0; i < specialtySelect.options.length; i++) {
                specialtySelect.options[i].selected = false;
            }

            // A more robust solution would fetch from the server.
            // For demonstration, we'll use a placeholder for existing specialties
            // You'd need to pass doctor_assigned_specialties to JS or make an AJAX call.
            // Example:
            const doctorAssignedSpecialtiesData = <?php echo json_encode($doctor_assigned_specialties); ?>;
            if (doctorAssignedSpecialtiesData[doctorId]) {
                const assignedIds = doctorAssignedSpecialtiesData[doctorId].map(s => String(s.id));
                for (let i = 0; i < specialtySelect.options.length; i++) {
                    if (assignedIds.includes(specialtySelect.options[i].value)) {
                        specialtySelect.options[i].selected = true;
                    }
                }
            }
        }

        // Show the first tab by default
        document.addEventListener('DOMContentLoaded', () => {
            showTab('listDoctors');
        });
    </script>
</body>
</html>