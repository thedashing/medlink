<?php
// Fichier : MEDECINE/medecinSpecialite_prix.php

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';

require_login('clinic');

$user_id = htmlspecialchars($_SESSION['user_id']);

$clinic_id = null;
$doctors = [];
$available_specialties = []; // Toutes les spécialités disponibles
// ATTENTION : doctor_assigned_specialties va maintenant stocker les spécialités du médecin DANS CETTE CLINIQUE
$doctor_assigned_specialties_in_clinic = []; // Spécialités assignées à chaque médecin POUR CETTE CLINIQUE
$doctor_offered_services = []; // Services que chaque médecin offre (depuis la table 'services')

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
    $stmt = $pdo->prepare("SELECT d.id, d.first_name, d.last_name FROM doctors d JOIN clinic_doctors cd ON d.id = cd.doctor_id WHERE cd.clinic_id = :clinic_id ORDER BY d.last_name, d.first_name");
    $stmt->execute([':clinic_id' => $clinic_id]);
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Récupérer toutes les spécialités disponibles (les spécialités générales)
    $stmt = $pdo->query("SELECT id, name FROM specialties ORDER BY name");
    $available_specialties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. Récupérer les spécialités déjà assignées à chaque médecin POUR CETTE CLINIQUE
    foreach ($doctors as $doctor) {
        $stmt = $pdo->prepare("SELECT s.id, s.name FROM doctor_clinic_specialties dcs JOIN specialties s ON dcs.specialty_id = s.id WHERE dcs.doctor_id = :doctor_id AND dcs.clinic_id = :clinic_id");
        $stmt->execute([':doctor_id' => $doctor['id'], ':clinic_id' => $clinic_id]);
        $doctor_assigned_specialties_in_clinic[$doctor['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 5. Récupérer les services offerts par chaque médecin (avec leurs prix et durées)
    // Nous continuons d'utiliser la table 'services' qui lie service, docteur et clinique.
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

    // Organiser les services par doctor_id
    foreach ($all_services as $service) {
        if (!isset($doctor_offered_services[$service['doctor_id']])) {
            $doctor_offered_services[$service['doctor_id']] = [];
        }
        $doctor_offered_services[$service['doctor_id']][] = $service;
    }


    // Gérer les soumissions de formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        // --- Action : Gérer les spécialités du médecin DANS CETTE CLINIQUE ---
        if ($_POST['action'] === 'manage_doctor_clinic_specialties') { // CHANGEMENT D'ACTION
            $selected_doctor_id = htmlspecialchars($_POST['doctor_id_specialty']);
            $new_specialty_ids = isset($_POST['specialty_ids']) ? $_POST['specialty_ids'] : [];

            if (empty($selected_doctor_id)) {
                $error_message = "Veuillez sélectionner un médecin.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // Vérifier si le médecin appartient à cette clinique pour des raisons de sécurité
                    $stmt_check_doctor = $pdo->prepare("SELECT d.id FROM doctors d JOIN clinic_doctors cd ON d.id = cd.doctor_id WHERE d.id = :doctor_id AND cd.clinic_id = :clinic_id");
                    $stmt_check_doctor->execute([':doctor_id' => $selected_doctor_id, ':clinic_id' => $clinic_id]);
                    if (!$stmt_check_doctor->fetch()) {
                        $error_message = "Sélection de médecin invalide.";
                        $pdo->rollBack();
                    } else {
                        // Supprimer les spécialités existantes pour ce médecin DANS CETTE CLINIQUE
                        $stmt_delete = $pdo->prepare("DELETE FROM doctor_clinic_specialties WHERE doctor_id = :doctor_id AND clinic_id = :clinic_id");
                        $stmt_delete->execute([':doctor_id' => $selected_doctor_id, ':clinic_id' => $clinic_id]);

                        // Ajouter les nouvelles spécialités POUR CETTE CLINIQUE
                        if (!empty($new_specialty_ids)) {
                            $insert_values = [];
                            $placeholders = [];
                            foreach ($new_specialty_ids as $specialty_id) {
                                $placeholders[] = "(?, ?, ?)"; // doctor_id, clinic_id, specialty_id
                                $insert_values[] = $selected_doctor_id;
                                $insert_values[] = $clinic_id; // Ajout de clinic_id
                                $insert_values[] = htmlspecialchars($specialty_id);
                            }
                            $stmt_insert = $pdo->prepare("INSERT INTO doctor_clinic_specialties (doctor_id, clinic_id, specialty_id) VALUES " . implode(", ", $placeholders));
                            $stmt_insert->execute($insert_values);
                        }
                        $pdo->commit();
                        $success_message = "Spécialités du médecin (pour cette clinique) mises à jour avec succès.";
                        header("Location: medecinSpecialite_prix.php"); // Rediriger pour rafraîchir
                        exit();
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Erreur lors de la mise à jour des spécialités de la clinique pour le médecin : " . $e->getMessage());
                    $error_message = "Erreur lors de la mise à jour des spécialités du médecin (pour cette clinique).";
                }
            }
        }

        // --- Action : Ajouter un nouveau service pour un médecin (inchangé) ---
        if ($_POST['action'] === 'add_doctor_service') {
            $selected_doctor_id = htmlspecialchars($_POST['doctor_id_service']);
            $service_name = htmlspecialchars($_POST['service_name']);
            $service_description = htmlspecialchars($_POST['service_description']);
            $price = floatval($_POST['price']);
            $duration_minutes = intval($_POST['duration_minutes']);

            if (empty($selected_doctor_id) || empty($service_name) || empty($price) || empty($duration_minutes)) {
                $error_message = "Tous les champs pour le service sont requis.";
            } else {
                try {
                    $stmt_check_doctor = $pdo->prepare("SELECT d.id FROM doctors d JOIN clinic_doctors cd ON d.id = cd.doctor_id WHERE d.id = :doctor_id AND cd.clinic_id = :clinic_id");
                    $stmt_check_doctor->execute([':doctor_id' => $selected_doctor_id, ':clinic_id' => $clinic_id]);
                    if ($stmt_check_doctor->fetch()) {
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
                            header("Location: medecinSpecialite_prix.php");
                            exit();
                        } else {
                            $error_message = "Erreur lors de l'ajout du service.";
                        }
                    } else {
                        $error_message = "Sélection de médecin invalide.";
                    }
                } catch (PDOException $e) {
                    error_log("Erreur lors de l'ajout du service : " . $e->getMessage());
                    $error_message = "Une erreur de base de données est survenue lors de l'ajout du service.";
                }
            }
        }
    }

} catch (PDOException $e) {
    error_log("Erreur de base de données : " . $e->getMessage());
    $error_message = "Une erreur de base de données est survenue. Veuillez réessayer plus tard.";
}
?>