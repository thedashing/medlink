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