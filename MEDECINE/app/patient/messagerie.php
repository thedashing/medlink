<?php
// Fichier : MEDECINE/app/patient/messagerie.php

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once '../../includes/patient/Messaging_functions.php';

require_login('patient'); // Redirige si non connecté en tant que patient

$user_id = $_SESSION['user_id'];
$user_email = htmlspecialchars($_SESSION['user_email']);
$unread_messages_count = 0;

$messaging = new Messaging();
$pdo = Database::getInstance()->getConnection();

// --- LOGIQUE DE TRAITEMENT AJAX ---
// Cette partie du code s'exécutera uniquement si c'est une requête AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json'); // La réponse sera du JSON

    $response = [
        'html' => '',
        'unread_count' => 0,
        'status' => 'success',
        'message' => ''
    ];

    // Traitement pour marquer un message comme lu via AJAX
    if (isset($_POST['action']) && $_POST['action'] === 'mark_as_read' && isset($_POST['message_id']) && is_numeric($_POST['message_id'])) {
        $messageIdToMark = (int)$_POST['message_id'];
        if ($messaging->markAsRead($messageIdToMark, $user_id)) {
            $response['message'] = 'Message marqué comme lu.';
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Échec de la lecture du message.';
        }
    }

    // Récupérer les messages (toujours, car c'est la fonction principale de cette page)
    $stmt = $pdo->prepare("SELECT m.*,
                            CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
                            cl.name AS clinic_name
                            FROM messages m
                            LEFT JOIN appointments a ON m.appointment_id = a.id
                            LEFT JOIN doctors d ON a.doctor_id = d.id
                            LEFT JOIN clinics cl ON a.clinic_id = cl.id
                            WHERE m.recipient_id = :user_id
                            ORDER BY
                                m.is_read ASC,
                                CASE WHEN m.is_read = 0 THEN m.created_at END DESC,
                                CASE WHEN m.is_read = 1 THEN m.read_at END DESC");
    $stmt->execute([':user_id' => $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Démarrer la mise en mémoire tampon pour capturer le HTML des messages
    ob_start();
    if (empty($messages)) {
        echo '<div class="no-messages">';
        echo '<p>Aucun message pour le moment.</p>';
        echo '<p>Vos médecins pourront vous envoyer des informations importantes ou des rappels ici.</p>';
        echo '</div>';
    } else {
        foreach ($messages as $message) {
            // Utiliser un modèle séparé pour chaque carte de message (TRÈS RECOMMANDÉ)
            // Assurez-vous que $message est disponible dans le scope de ce modèle
            include '../../includes/patient/message_card_template.php';
        }
    }
    $response['html'] = ob_get_clean(); // Récupérer le HTML capturé

    // Mettre à jour le compteur de messages non lus
    $response['unread_count'] = $messaging->getUnreadMessagesCount($user_id);

    echo json_encode($response);
    exit(); // Très important : arrêter l'exécution du script après la réponse AJAX
}
// --- FIN DE LA LOGIQUE DE TRAITEMENT AJAX ---


// Cette partie s'exécute lors du chargement initial de la page (non-AJAX)
// Récupérer les messages pour le rendu initial
$stmt = $pdo->prepare("SELECT m.*,
                        CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
                        cl.name AS clinic_name
                        FROM messages m
                        LEFT JOIN appointments a ON m.appointment_id = a.id
                        LEFT JOIN doctors d ON a.doctor_id = d.id
                        LEFT JOIN clinics cl ON a.clinic_id = cl.id
                        WHERE m.recipient_id = :user_id
                        ORDER BY
                            m.is_read ASC,
                            CASE WHEN m.is_read = 0 THEN m.created_at END DESC,
                            CASE WHEN m.is_read = 1 THEN m.read_at END DESC");
$stmt->execute([':user_id' => $user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le nombre de messages non lus pour l'affichage initial du badge
$unread_messages_count = $messaging->getUnreadMessagesCount($user_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma Messagerie - Patient</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="../../public/css/patient/messagerie.css">
</head>
<body>
    <?php include '../../includes/patient/entete.php'; ?>

    <div class="patient-main">
        <div class="main-content">
            <h2 class="section-title">Mes Messages Privés</h2>

            <div id="message-list-container" class="message-list">
                <?php if (empty($messages)): ?>
                    <div class="no-messages">
                        <p>Aucun message pour le moment.</p>
                        <p>Vos médecins pourront vous envoyer des informations importantes ou des rappels ici.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $message): ?>
                        <?php include '../../includes/patient/message_card_template.php'; // Inclure le modèle ici ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const messageListContainer = document.getElementById('message-list-container');
            const unreadCountBadge = document.getElementById('unread-messages-count-badge'); // Assurez-vous d'avoir cet ID dans votre entete.php

            // Fonction pour récupérer et afficher les messages via AJAX
            const fetchMessages = async () => {
                try {
                    const response = await fetch('messagerie.php', {
                        method: 'GET', // Par défaut, mais explicite
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest' // Indique que c'est une requête AJAX
                        }
                    });
                    const data = await response.json(); // La réponse est au format JSON

                    if (data.status === 'success') {
                        messageListContainer.innerHTML = data.html; // Mettre à jour le contenu HTML des messages

                        // Mettre à jour le compteur de messages non lus dans l'en-tête
                        if (unreadCountBadge) {
                            if (data.unread_count > 0) {
                                unreadCountBadge.textContent = data.unread_count;
                                unreadCountBadge.style.display = 'inline-block'; // Assurez-vous qu'il est visible
                            } else {
                                unreadCountBadge.style.display = 'none'; // Masquer s'il n'y a pas de messages non lus
                            }
                        }

                        // Rattacher les écouteurs d'événements aux nouvelles cartes de messages
                        attachMessageCardListeners();
                    } else {
                        console.error('Erreur lors de la récupération des messages :', data.message);
                        // Vous pouvez afficher un message d'erreur à l'utilisateur ici
                    }

                } catch (error) {
                    console.error('Erreur réseau ou du serveur lors de la récupération des messages :', error);
                    // Vous pouvez afficher un message d'erreur plus général ici
                }
            };

            // Fonction pour marquer un message comme lu via AJAX
            const markMessageAsRead = async (messageId) => {
                try {
                    const formData = new FormData();
                    formData.append('action', 'mark_as_read');
                    formData.append('message_id', messageId);

                    const response = await fetch('messagerie.php', {
                        method: 'POST', // Utiliser POST pour les actions
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest' // Indique que c'est une requête AJAX
                        },
                        body: formData // Envoyer les données du formulaire
                    });

                    const data = await response.json();

                    if (data.status === 'success') {
                        console.log(data.message);
                        fetchMessages(); // Recharger tous les messages pour refléter le changement
                    } else {
                        console.error('Erreur :', data.message);
                    }
                } catch (error) {
                    console.error('Erreur réseau lors de la lecture du message :', error);
                }
            };

            // Fonction pour attacher les écouteurs d'événements aux cartes de messages
            const attachMessageCardListeners = () => {
                document.querySelectorAll('.message-card.unread').forEach(card => {
                    // Supprimer l'écouteur existant pour éviter les doublons lors des mises à jour AJAX
                    card.removeEventListener('click', handleMessageCardClick);
                    // Ajouter le nouvel écouteur
                    card.addEventListener('click', handleMessageCardClick);
                });
            };

            const handleMessageCardClick = function() {
                const messageId = this.dataset.messageId;
                if (messageId && this.classList.contains('unread')) { // Vérifier s'il est encore non lu pour éviter des appels inutiles
                    markMessageAsRead(messageId);
                }
            };

            // Appel initial pour charger les messages au chargement de la page
            fetchMessages();

            // Optionnel : Recharger les messages à intervalles réguliers (ex: toutes les 30 secondes)
            // Cela peut être utile si de nouveaux messages peuvent arriver fréquemment.
            setInterval(fetchMessages, 30000); // 30000 ms = 30 secondes
        });
    </script>
</body>
</html>