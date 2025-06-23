<?php
require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once '../../includes/patient/messaging_functions.php'; // Assurez-vous que ce fichier définit la classe Messaging et ses méthodes

require_login('patient'); // Redirige si non connecté en tant que patient

$user_id = $_SESSION['user_id'];
$user_email = htmlspecialchars($_SESSION['user_email']); // Supposons que user_email est dans la session
$messaging = new Messaging();

// Marquer un message comme lu si nécessaire
if (isset($_GET['mark_as_read']) && is_numeric($_GET['mark_as_read'])) {
    $messageIdToMark = (int)$_GET['mark_as_read'];
    // Assurez-vous que la méthode est bien nommée markAsRead dans messaging_functions.php
    $messaging->markAsRead($messageIdToMark, $user_id); 
    // Rediriger pour éviter que le paramètre GET ne reste dans l'URL et marque à nouveau
    header('Location: patient_messages.php');
    exit();
}

// Récupérer les messages avec le nouvel ordre de tri
$pdo = Database::getInstance()->getConnection();
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

// Optionnel: Récupérer le nombre de messages non lus pour affichage dans le badge
$unread_count = $messaging->getUnreadMessagesCount($user_id); 
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma Messagerie - Patient</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007bff; /* Bleu */
            --primary-dark: #0056b3;  /* Bleu plus foncé */
            --light-bg: #f8f9fa; /* Gris très clair */
            --text-color: #343a40; /* Gris foncé */
            --card-bg: #ffffff;
            --shadow-light: rgba(0, 0, 0, 0.08);
            --border-color: #e9ecef;
            --unread-bg: #e6f7ff; /* Bleu clair pour les non lus */
            --unread-border: #007bff;
            --new-badge-bg: #28a745; /* Vert pour le badge "Nouveau" */
            --secondary-text: #6c757d; /* Gris plus clair pour les dates/détails */
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--light-bg);
            margin: 0;
            padding: 0;
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 25px;
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 6px 15px var(--shadow-light);
        }

        .header-main {
            background-color: var(--primary-color);
            color: white;
            padding: 20px 30px;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: -25px -25px 20px -25px; /* Ajuster la marge pour s'aligner avec le conteneur */
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            flex-wrap: wrap; /* Permet aux éléments de passer à la ligne sur les petits écrans */
        }
        .header-main h1 {
            margin: 0;
            font-size: 2em;
            font-weight: 700;
        }
        .header-main nav {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .header-main nav a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 4px;
            transition: background-color 0.3s ease, border-color 0.3s ease;
            white-space: nowrap;
        }
        .header-main nav a:hover {
            background-color: var(--primary-dark);
            border-color: white;
        }

        .user-info {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1.1em;
            color: #555;
            padding-top: 10px;
        }
        .user-info a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
        }
        .user-info a:hover {
            text-decoration: underline;
        }

        h2 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 30px;
            font-size: 2em;
            font-weight: 700;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .message-list {
            padding: 0 10px; /* Ajouter un peu de rembourrage autour de la liste */
        }

        .message-card {
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: var(--card-bg);
            box-shadow: 0 4px 10px var(--shadow-light);
            cursor: pointer; /* Indiquer qu'il est cliquable */
            transition: transform 0.2s ease, box-shadow 0.2s ease, background-color 0.3s ease;
        }
        .message-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.12);
        }

        .message-card.unread {
            background-color: var(--unread-bg);
            border-left: 6px solid var(--unread-border);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15); /* Ombre plus forte pour les non lus */
        }
        .message-card.unread:hover {
             background-color: #d8efff; /* Bleu clair légèrement plus foncé au survol */
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap; /* Permettre le retour à la ligne sur les petits écrans */
            gap: 10px; /* Espacement entre les éléments */
        }

        .message-subject {
            font-weight: 700;
            font-size: 1.25em;
            color: var(--primary-color);
            flex-grow: 1; /* Permet au sujet de prendre l'espace disponible */
        }

        .message-date {
            color: var(--secondary-text);
            font-size: 0.9em;
            white-space: nowrap; /* Garder la date sur une ligne */
        }

        .message-info {
            font-style: italic;
            color: var(--secondary-text);
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .message-content {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px dashed var(--border-color);
            line-height: 1.8;
        }
        .message-content p {
            margin: 0 0 8px 0; /* Ajuster l'espacement pour les paragraphes dans le contenu */
        }

        .badge {
            background-color: var(--new-badge-bg);
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.75em;
            font-weight: bold;
            margin-left: 10px;
            white-space: nowrap;
        }

        .no-messages {
            text-align: center;
            padding: 40px;
            color: var(--secondary-text);
            font-size: 1.2em;
            background-color: #f1f1f1;
            border-radius: 8px;
            margin-top: 30px;
            box-shadow: inset 0 1px 5px rgba(0,0,0,0.03);
        }

        /* Ajustements responsifs */
        @media (max-width: 768px) {
            .header-main {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px 20px;
            }
            .header-main h1 {
                font-size: 1.8em;
                margin-bottom: 10px;
            }
            .header-main nav {
                flex-direction: column;
                width: 100%;
                gap: 8px;
            }
            .header-main nav a {
                width: calc(100% - 20px);
                text-align: center;
            }
            .container {
                margin: 20px 15px;
                padding: 20px;
            }
            h2 {
                font-size: 1.8em;
            }
            .message-card {
                padding: 15px;
            }
            .message-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            .message-subject {
                font-size: 1.1em;
            }
            .message-date {
                text-align: left;
                width: 100%;
            }
            .badge {
                margin-left: 0;
                margin-top: 5px;
            }
            .message-info {
                font-size: 0.85em;
            }
            .message-content {
                padding-top: 10px;
            }
        }
    </style>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.message-card.unread').forEach(function(card) {
            card.addEventListener('click', function() {
                const messageId = this.dataset.messageId;
                if (messageId) {
                    // C'est déjà corrigé si vous avez suivi mes instructions précédentes
                    window.location.href = `messagerie.php?mark_as_read=${messageId}`; 
                }
            });
        });
    });
</script>
</head>
<body>
    <div class="container">
        <div class="header-main">
            <h1>Ma Messagerie</h1>
            <nav>
                <a href="dashboard_patient.php">Retour au Tableau de Bord</a>
                <a href="../securite/logout.php">Se déconnecter</a>
            </nav>
        </div>

        <div class="user-info">
            <p>Connecté en tant que: **<?php echo $user_email; ?>**</p>
        </div>
        
        <h2>Mes Messages Privés</h2>
        
        <div class="message-list">
            <?php if (empty($messages)): ?>
                <div class="no-messages">
                    <p>Aucun message pour le moment.</p>
                    <p>Vos médecins pourront vous envoyer des informations importantes ou des rappels ici.</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <div class="message-card <?php echo !$message['is_read'] ? 'unread' : ''; ?>" 
                         data-message-id="<?php echo htmlspecialchars($message['id']); ?>">
                        <div class="message-header">
                            <span class="message-subject">
                                <?php echo htmlspecialchars($message['subject']); ?>
                                <?php if (!$message['is_read']): ?>
                                    <span class="badge">Nouveau</span>
                                <?php endif; ?>
                            </span>
                            <span class="message-date">
                                <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?>
                            </span>
                        </div>
                        
                        <?php if ($message['appointment_id']): ?>
                            <p class="message-info">
                                <em>Concerne un rendez-vous avec 
                                    <strong>Dr. <?php echo htmlspecialchars($message['doctor_name'] ?? 'Inconnu'); ?></strong>
                                    <?php if (!empty($message['clinic_name'])): ?>
                                         à <strong><?php echo htmlspecialchars($message['clinic_name']); ?></strong>
                                    <?php endif; ?>
                                </em>
                            </p>
                        <?php endif; ?>
                        
                        <div class="message-content">
                            <?php echo nl2br(htmlspecialchars($message['content'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>