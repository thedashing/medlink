<?php
require_once '../../includes/patient/rendezvous_function.php';

// Supposons que ces variables soient remplies par rendezvous_function.php
// Pour des raisons de démonstration, assurons-nous qu'elles sont définies, même si elles sont vides
$upcoming_appointments = $upcoming_appointments ?? [];
$past_completed_appointments = $past_completed_appointments ?? [];
$cancelled_appointments = $cancelled_appointments ?? [];

// Gérer les requêtes AJAX
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // C'est une requête AJAX, retourner uniquement le contenu
    header('Content-Type: application/json');

    $response = [
        'upcoming' => '',
        'past' => '',
        'canceled' => '',
        'counts' => [
            'upcoming' => count($upcoming_appointments),
            'past' => count($past_completed_appointments),
            'canceled' => count($cancelled_appointments)
        ]
    ];

    ob_start(); // Démarrer la mise en mémoire tampon de la sortie pour les rendez-vous à venir
    if (empty($upcoming_appointments)) {
        echo '<div class="no-appointments"><p>Vous n\'avez aucun rendez-vous à venir. <a href="../../search.php">Rechercher un médecin pour prendre rendez-vous</a>.</p></div>';
    } else {
        foreach ($upcoming_appointments as $appointment) {
            // Calculer la différence de temps pour la politique d'annulation (24 heures)
            $appointment_timestamp = strtotime($appointment['appointment_datetime']);
            $current_timestamp = time();
            $time_diff_hours = ($appointment_timestamp - $current_timestamp) / 3600; // Différence en heures
            $can_cancel = ($time_diff_hours > 24); // Peut annuler si plus de 24h avant
            include '../../includes/patient/appointment_card_template.php'; // Utiliser un modèle pour la carte de rendez-vous
        }
    }
    $response['upcoming'] = ob_get_clean(); // Obtenir le contenu et nettoyer la mémoire tampon

    ob_start(); // Démarrer la mise en mémoire tampon de la sortie pour les rendez-vous passés
    if (empty($past_completed_appointments)) {
        echo '<div class="no-appointments"><p>Vous n\'avez aucun rendez-vous passé. <a href="../../search.php">Rechercher un médecin pour prendre rendez-vous</a>.</p></div>';
    } else {
        foreach ($past_completed_appointments as $appointment) {
            // Note: $can_cancel n'est pas pertinent ici pour les rendez-vous passés,
            // mais le template est réutilisé.
            $can_cancel = false;
            include '../../includes/patient/appointment_card_template.php'; // Utiliser un modèle pour la carte de rendez-vous
        }
    }
    $response['past'] = ob_get_clean();

    ob_start(); // Démarrer la mise en mémoire tampon de la sortie pour les rendez-vous annulés
    if (empty($cancelled_appointments)) {
        echo '<div class="no-appointments"><p>Vous n\'avez aucun rendez-vous annulé. <a href="../../search.php">Rechercher un médecin pour prendre rendez-vous</a>.</p></div>';
    } else {
        foreach ($cancelled_appointments as $appointment) {
            // Note: $can_cancel n'est pas pertinent ici pour les rendez-vous annulés.
            $can_cancel = false;
            include '../../includes/patient/appointment_card_template.php'; // Utiliser un modèle pour la carte de rendez-vous
        }
    }
    $response['canceled'] = ob_get_clean();

    echo json_encode($response);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rendez-vous - Patient</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/patient/mes_rendez_vous.css">
</head>
<body>
    <?php include '../../includes/patient/entete.php'; ?>

    <main class="patient-main">
        <section class="appointments-section">
            <div class="container">
                <div class="section-header">
                    <h1>Mes rendez-vous</h1>
                    <div class="appointments-tabs">
                        <button class="tab-btn active" data-tab="upcoming-appointments-tab">
                            <a href="#upcoming-appointments-content">À venir <span class="status-badge status-upcoming" id="upcoming-count">
                                <?php echo count($upcoming_appointments); ?>
                            </span></a>
                        </button>
                        <button class="tab-btn" data-tab="past-appointments-tab">
                            <a href="#past-appointments-content">Passés <span class="status-badge status-past" id="past-count">
                                <?php echo count($past_completed_appointments); ?>
                            </span></a>
                        </button>
                        <button class="tab-btn" data-tab="canceled-appointments-tab">
                            <a href="#canceled-appointments-content">Annulés <span class="status-badge status-canceled" id="canceled-count">
                                <?php echo count($cancelled_appointments); ?>
                            </span></a>
                        </button>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="system-message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <div id="upcoming-appointments-content" class="tab-content active">
                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="no-appointments">
                            <p>Vous n'avez aucun rendez-vous à venir. <a href="../../search.php">Rechercher un médecin pour prendre rendez-vous</a>.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($upcoming_appointments as $appointment): ?>
                            <?php
                                $appointment_timestamp = strtotime($appointment['appointment_datetime']);
                                $current_timestamp = time();
                                $time_diff_hours = ($appointment_timestamp - $current_timestamp) / 3600;
                                $can_cancel = ($time_diff_hours > 24);
                            ?>
                            <div class="appointment-card upcoming">
                                <div class="appointment-info">
                                    <div class="doctor-info">
                                        <?php if (!empty($appointment['profile_picture_url'])): ?>
                                            <img src="../../<?php echo htmlspecialchars($appointment['profile_picture_url']); ?>" alt="Photo de <?php echo htmlspecialchars($appointment['doctor_first_name']); ?>" loading="lazy">
                                        <?php else: ?>
                                            <img src="../../Uploads/d1.jpg" alt="Avatar par défaut du médecin" loading="lazy">
                                        <?php endif; ?>
                                        <div>
                                            <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h3>
                                            <p class="specialty"><?php echo htmlspecialchars($appointment['service_name']); ?></p>
                                            <div class="rating">
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star-half-alt"></i>
                                                <span>4.5 (128 avis)</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="appointment-details">
                                        <p><i class="fas fa-calendar-day"></i> <strong><?php echo date('d/m/Y', strtotime($appointment['appointment_datetime'])); ?></strong></p>
                                        <p><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($appointment['appointment_datetime'])); ?> - <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                                        <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                                        <p><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars(number_format($appointment['service_price'], 2, ',', ' ')); ?> FCFA</p>
                                        <p><i class="fas fa-info-circle"></i> Statut: <span class="status-badge status-upcoming"><?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></span></p>
                                    </div>
                                </div>
                                <div class="appointment-actions">
                                    <a href="../../includes/patient/cancel_appointment.php?id=<?php echo htmlspecialchars($appointment['appointment_id']); ?>"
                                        class="btn btn-outline <?php echo !$can_cancel ? 'disabled' : ''; ?>"
                                        onclick="return <?php echo $can_cancel ? "confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')" : "false"; ?>;"
                                        <?php echo !$can_cancel ? 'title="Annulation non autorisée moins de 24h avant le rendez-vous."' : ''; ?>>
                                        <i class="fas fa-times"></i> Annuler
                                    </a>
                                    <a href="reschedule_appointment.php?id=<?php echo htmlspecialchars($appointment['appointment_id']); ?>"
                                        class="btn btn-primary <?php echo !$can_cancel ? 'disabled' : ''; ?>"
                                        <?php echo !$can_cancel ? 'title="Modification non autorisée moins de 24h avant le rendez-vous." onclick="return false;"' : ''; ?>>
                                        <i class="fas fa-calendar-alt"></i> Reporter
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="past-appointments-content" class="tab-content">
                    <?php if (empty($past_completed_appointments)): ?>
                        <div class="no-appointments">
                            <p>Vous n'avez aucun rendez-vous passé. <a href="../../search.php">Rechercher un médecin pour prendre rendez-vous</a>.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($past_completed_appointments as $appointment): ?>
                            <div class="appointment-card past">
                                <div class="appointment-info">
                                    <div class="doctor-info">
                                        <?php if (!empty($appointment['profile_picture_url'])): ?>
                                            <img src="../../<?php echo htmlspecialchars($appointment['profile_picture_url']); ?>" alt="Photo de <?php echo htmlspecialchars($appointment['doctor_first_name']); ?>" loading="lazy">
                                        <?php else: ?>
                                            <img src="../../Uploads/d1.jpg" alt="Avatar par défaut du médecin" loading="lazy">
                                        <?php endif; ?>
                                        <div>
                                            <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h3>
                                            <p class="specialty"><?php echo htmlspecialchars($appointment['service_name']); ?></p>
                                            <div class="rating">
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star-half-alt"></i>
                                                <span>4.5 (128 avis)</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="appointment-details">
                                        <p><i class="fas fa-calendar-day"></i> <strong><?php echo date('d/m/Y', strtotime($appointment['appointment_datetime'])); ?></strong></p>
                                        <p><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($appointment['appointment_datetime'])); ?> - <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                                        <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                                        <p><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars(number_format($appointment['service_price'], 2, ',', ' ')); ?> FCFA</p>
                                        <p><i class="fas fa-info-circle"></i> Statut: <span class="status-badge status-past"><?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></span></p>
                                    </div>
                                </div>
                                <div class="appointment-actions">
                                    <p>Rendez-vous terminé.</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="canceled-appointments-content" class="tab-content">
                    <?php if (empty($cancelled_appointments)): ?>
                        <div class="no-appointments">
                            <p>Vous n'avez aucun rendez-vous annulé. <a href="../../search.php">Rechercher un médecin pour prendre rendez-vous</a>.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cancelled_appointments as $appointment): ?>
                            <div class="appointment-card canceled">
                                <div class="appointment-info">
                                    <div class="doctor-info">
                                        <?php if (!empty($appointment['profile_picture_url'])): ?>
                                            <img src="../../<?php echo htmlspecialchars($appointment['profile_picture_url']); ?>" alt="Photo de <?php echo htmlspecialchars($appointment['doctor_first_name']); ?>" loading="lazy">
                                        <?php else: ?>
                                            <img src="../../Uploads/d1.jpg" alt="Avatar par défaut du médecin" loading="lazy">
                                        <?php endif; ?>
                                        <div>
                                            <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h3>
                                            <p class="specialty"><?php echo htmlspecialchars($appointment['service_name']); ?></p>
                                            <div class="rating">
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star-half-alt"></i>
                                                <span>4.5 (128 avis)</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="appointment-details">
                                        <p><i class="fas fa-calendar-day"></i> <strong><?php echo date('d/m/Y', strtotime($appointment['appointment_datetime'])); ?></strong></p>
                                        <p><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($appointment['appointment_datetime'])); ?> - <?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                                        <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                                        <p><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars(number_format($appointment['service_price'], 2, ',', ' ')); ?> FCFA</p>
                                        <p><i class="fas fa-info-circle"></i> Statut: <span class="status-badge status-canceled"><?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></span></p>
                                        <?php if (!empty($appointment['cancel_reason'])): ?>
                                            <p class="cancel-reason"><i class="fas fa-comment-dots"></i> Raison: <?php echo htmlspecialchars($appointment['cancel_reason']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="appointment-actions">
                                    <p>Ce rendez-vous a été annulé.</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            const upcomingCountSpan = document.getElementById('upcoming-count');
            const pastCountSpan = document.getElementById('past-count');
            const canceledCountSpan = document.getElementById('canceled-count');

            // Fonction pour afficher un onglet
            const showTab = (tabId) => {
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                tabButtons.forEach(button => {
                    button.classList.remove('active');
                });

                document.getElementById(tabId).classList.add('active');
                document.querySelector(`.tab-btn[data-tab="${tabId.replace('-content', '-tab')}"]`).classList.add('active');
            };

            // Fonction pour récupérer et mettre à jour les rendez-vous via AJAX
            const fetchAppointments = async () => {
                try {
                    const response = await fetch('mes_rendez_vous.php', {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest' // Identifier comme requête AJAX
                        }
                    });
                    const data = await response.json();

                    // Mettre à jour les compteurs
                    upcomingCountSpan.textContent = data.counts.upcoming;
                    pastCountSpan.textContent = data.counts.past;
                    canceledCountSpan.textContent = data.counts.canceled;

                    // Mettre à jour le contenu de chaque onglet
                    document.getElementById('upcoming-appointments-content').innerHTML = data.upcoming;
                    document.getElementById('past-appointments-content').innerHTML = data.past;
                    document.getElementById('canceled-appointments-content').innerHTML = data.canceled;

                    // Rattacher les écouteurs d'événements pour le contenu dynamique (boutons annuler/reporter)
                    attachActionListener();

                } catch (error) {
                    console.error('Erreur lors de la récupération des rendez-vous :', error);
                }
            };

            // Fonction pour attacher les écouteurs d'événements aux éléments dynamiques (comme les boutons d'annulation)
            const attachActionListener = () => {
                const actionButtons = document.querySelectorAll('.appointment-actions .btn-outline, .appointment-actions .btn-primary');
                actionButtons.forEach(button => {
                    button.removeEventListener('click', handleAppointmentAction); // Empêcher les écouteurs en double
                    button.addEventListener('click', handleAppointmentAction);
                });
            };

            // Écouteur d'événements pour les clics sur les boutons d'onglet
            tabButtons.forEach(button => {
                button.addEventListener('click', (event) => {
                    event.preventDefault(); // Empêcher le comportement par défaut du lien
                    const tabId = button.dataset.tab.replace('-tab', '-content');
                    showTab(tabId);
                });
            });

            // Gérer les actions Annuler/Reporter via AJAX
            const handleAppointmentAction = async (event) => {
                // Vérifier si le bouton est désactivé
                if (event.currentTarget.classList.contains('disabled')) {
                    event.preventDefault(); // Arrêter l'action par défaut
                    return;
                }

                if (!confirm('Êtes-vous sûr de vouloir effectuer cette action ?')) {
                    event.preventDefault();
                    return;
                }

                event.preventDefault(); // Empêcher la soumission de formulaire par défaut ou la navigation de lien

                const url = event.currentTarget.href;

                try {
                    const response = await fetch(url, {
                        method: 'GET', // Ou POST si votre logique d'annulation/report le prévoit
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const result = await response.json();

                    if (result.status === 'success') {
                        // Afficher le message de succès
                        const systemMessageDiv = document.createElement('div');
                        systemMessageDiv.classList.add('system-message', 'success');
                        systemMessageDiv.textContent = result.message;
                        document.querySelector('.container').prepend(systemMessageDiv); // Ajouter en haut du conteneur
                        setTimeout(() => systemMessageDiv.remove(), 5000); // Supprimer après 5 secondes

                        fetchAppointments(); // Re-récupérer tous les rendez-vous pour mettre à jour les listes et les compteurs
                    } else {
                        // Afficher le message d'erreur
                        const systemMessageDiv = document.createElement('div');
                        systemMessageDiv.classList.add('system-message', 'error');
                        systemMessageDiv.textContent = result.message;
                        document.querySelector('.container').prepend(systemMessageDiv);
                        setTimeout(() => systemMessageDiv.remove(), 5000);
                    }
                } catch (error) {
                    console.error('Erreur lors de l\'exécution de l\'action :', error);
                    const systemMessageDiv = document.createElement('div');
                    systemMessageDiv.classList.add('system-message', 'error');
                    systemMessageDiv.textContent = 'Une erreur est survenue lors de l\'opération.';
                    document.querySelector('.container').prepend(systemMessageDiv);
                    setTimeout(() => systemMessageDiv.remove(), 5000);
                }
            };

            // Chargement initial : afficher le premier onglet et récupérer les rendez-vous
            if (tabButtons.length > 0) {
                const initialTabId = tabButtons[0].dataset.tab.replace('-tab', '-content');
                showTab(initialTabId);
                fetchAppointments(); // Récupérer les rendez-vous au chargement de la page
            }
        });
    </script>
</body>
</html>