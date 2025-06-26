<?php
// Fichier : MEDECINE/app/patient/mon_dossier.php

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once '../../includes/patient/Messaging_functions.php'; // Inclure la classe Messaging pour le compteur de messages

// S'assurer que l'utilisateur est connecté et est un patient
require_login('patient');

$user_id = htmlspecialchars($_SESSION['user_id']); // L'ID de l'utilisateur (patient) connecté
$patient_db_id = null; // L'ID du patient dans la table 'patients'
$medical_records = [];
$message = '';
$message_type = '';
$unread_messages_count = 0; // Initialisation du compteur

// Retrieve user's email for the header, ensuring it's set
$user_email = isset($_SESSION['user_email']) ? htmlspecialchars($_SESSION['user_email']) : 'Patient';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // D'abord, récupérer l'ID du patient à partir de l'ID utilisateur
    $stmt_patient_id = $pdo->prepare("SELECT id FROM patients WHERE user_id = :user_id");
    $stmt_patient_id->execute([':user_id' => $user_id]);
    $patient_info = $stmt_patient_id->fetch(PDO::FETCH_ASSOC);

    if ($patient_info) {
        $patient_db_id = $patient_info['id'];
    } else {
        throw new Exception("Aucun profil patient trouvé pour votre compte. Veuillez contacter l'administration.");
    }

    // Récupérer les dossiers médicaux pour ce patient
    $stmt_records = $pdo->prepare("SELECT
                                    mr.id AS record_id,
                                    mr.diagnosis,
                                    mr.treatment,
                                    mr.notes,
                                    mr.record_date,
                                    a.appointment_datetime,
                                    d.first_name AS doctor_first_name,
                                    d.last_name AS doctor_last_name,
                                    cl.name AS clinic_name,
                                    cl.address AS clinic_address,
                                    s.name AS service_name,
                                    s.price AS service_price
                                FROM
                                    medical_records mr
                                JOIN
                                    appointments a ON mr.appointment_id = a.id
                                JOIN
                                    doctors d ON a.doctor_id = d.id
                                JOIN
                                    clinics cl ON a.clinic_id = cl.id
                                JOIN
                                    services s ON a.service_id = s.id
                                WHERE
                                    mr.patient_id = :patient_db_id
                                ORDER BY
                                    mr.record_date DESC"); // Trier par date du dossier, le plus récent en premier
    $stmt_records->execute([':patient_db_id' => $patient_db_id]);
    $medical_records = $stmt_records->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = "Erreur : " . $e->getMessage();
    $message_type = 'error';
    error_log("Medical records page error: " . $e->getMessage());
}
$messaging = new Messaging();
$unread_messages_count = $messaging->getUnreadMessagesCount($user_id);


// --- DÉBUT DE LA LOGIQUE AJAX ---
// Vérifier si la requête est une requête AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json'); // Définir l'en-tête pour indiquer une réponse JSON

    $response = [
        'html' => '',
        'message' => $message,
        'message_type' => $message_type
    ];

    ob_start(); // Démarrer la mise en mémoire tampon de la sortie

    if (empty($medical_records)) {
        echo '<div class="no-records"><p>Aucun dossier médical trouvé pour le moment.</p><p>Les dossiers médicaux sont ajoutés par votre médecin après une consultation terminée. N\'hésitez pas à prendre un rendez-vous pour commencer votre suivi !</p></div>';
    } else {
        echo '<div class="records-list">';
        foreach ($medical_records as $record) {
            // Inclure le modèle de carte de dossier médical pour réutiliser le code HTML
            include '../../includes/patient/medical_record_card_template.php';
        }
        echo '</div>';
    }

    $response['html'] = ob_get_clean(); // Obtenir le contenu mis en tampon et nettoyer la mémoire tampon

    echo json_encode($response); // Envoyer la réponse JSON
    exit; // Terminer le script après l'envoi de la réponse AJAX
}
// --- FIN DE LA LOGIQUE AJAX ---

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Dossier Médical - Patient</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../public/css/patient/doss.css">
</head>
<body>
    <?php include '../../includes/patient/entete.php'; ?>
    <div class="patient-main">
        <div class="main-content">
            <?php if (!empty($message)): ?>
                <div class="system-message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <h2 class="section-title">Historique de mes Dossiers Médicaux</h2>

            <div id="medical-records-container">
                <?php
                // Ce bloc PHP ne sera exécuté que lors du chargement initial de la page (pas par AJAX)
                // Son contenu sera écrasé par la suite par les données AJAX
                if (empty($medical_records)) {
                    echo '<div class="no-records"><p>Aucun dossier médical trouvé pour le moment.</p><p>Les dossiers médicaux sont ajoutés par votre médecin après une consultation terminée. N\'hésitez pas à prendre un rendez-vous pour commencer votre suivi !</p></div>';
                } else {
                    echo '<div class="records-list">';
                    foreach ($medical_records as $record) {
                        // Inclure le modèle de carte de dossier médical
                        include '../../includes/patient/medical_record_card_template.php';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const medicalRecordsContainer = document.getElementById('medical-records-container');
            const systemMessageDiv = document.querySelector('.system-message'); // Sélectionnez le message système existant

            // Fonction pour récupérer et afficher les dossiers médicaux via AJAX
            const fetchMedicalRecords = async () => {
                try {
                    const response = await fetch('mon_dossier.php', {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest' // Indique que c'est une requête AJAX
                        }
                    });
                    const data = await response.json(); // La réponse est au format JSON

                    // Mettre à jour le contenu HTML des dossiers
                    medicalRecordsContainer.innerHTML = data.html;

                    // Gérer les messages système (succès/erreur)
                    if (data.message) {
                        if (systemMessageDiv) {
                            systemMessageDiv.textContent = data.message;
                            systemMessageDiv.className = `system-message ${data.message_type}`;
                            systemMessageDiv.style.display = 'block'; // Assurez-vous qu'il est visible
                        } else {
                            // Si le div system-message n'existe pas encore, créez-le
                            const newSystemMessageDiv = document.createElement('div');
                            newSystemMessageDiv.classList.add('system-message', data.message_type);
                            newSystemMessageDiv.textContent = data.message;
                            document.querySelector('.main-content').prepend(newSystemMessageDiv);
                        }
                        // Masquer le message après quelques secondes
                        setTimeout(() => {
                            if (document.querySelector('.system-message')) { // Vérifier s'il existe toujours
                                document.querySelector('.system-message').style.display = 'none';
                            }
                        }, 5000);
                    } else {
                         // Si aucun message n'est renvoyé, masquer le message existant
                        if (systemMessageDiv) {
                            systemMessageDiv.style.display = 'none';
                        }
                    }

                } catch (error) {
                    console.error('Erreur lors de la récupération des dossiers médicaux :', error);
                    // Afficher un message d'erreur générique si la requête échoue
                    if (systemMessageDiv) {
                        systemMessageDiv.textContent = 'Une erreur est survenue lors du chargement de vos dossiers médicaux.';
                        systemMessageDiv.className = 'system-message error';
                        systemMessageDiv.style.display = 'block';
                    } else {
                        const newSystemMessageDiv = document.createElement('div');
                        newSystemMessageDiv.classList.add('system-message', 'error');
                        newSystemMessageDiv.textContent = 'Une erreur est survenue lors du chargement de vos dossiers médicaux.';
                        document.querySelector('.main-content').prepend(newSystemMessageDiv);
                    }
                     setTimeout(() => {
                        if (document.querySelector('.system-message')) {
                            document.querySelector('.system-message').style.display = 'none';
                        }
                    }, 5000);
                }
            };

            // Appeler la fonction de chargement initial des dossiers
            fetchMedicalRecords();

            // Optionnel : Recharger les dossiers à intervalles réguliers (par exemple, toutes les 60 secondes)
            // setInterval(fetchMedicalRecords, 60000);
        });
    </script>
</body>
</html>