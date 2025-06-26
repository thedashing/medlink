<?php
require_once '../../includes/patient/rendezvous_function.php';
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
                            <a href="#upcoming-appointments-content">À venir <span class="status-badge status-upcoming">
                                <?php echo count($upcoming_appointments ?? []); // Utilisation de null coalescing pour éviter les erreurs si la variable n'est pas définie ?>
                            </span></a>
                        </button>
                        <button class="tab-btn" data-tab="past-appointments-tab">
                            <a href="#past-appointments-content">Passés <span class="status-badge status-past">
                                <?php echo count($past_completed_appointments ?? []); ?>
                            </span></a>
                        </button>
                        <button class="tab-btn" data-tab="canceled-appointments-tab">
                            <a href="#canceled-appointments-content">Annulés <span class="status-badge status-canceled">
                                <?php echo count($cancelled_appointments ?? []); ?>
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
                                // Calculate time difference for cancellation policy (24 hours)
                                $appointment_timestamp = strtotime($appointment['appointment_datetime']);
                                $current_timestamp = time();
                                $time_diff_hours = ($appointment_timestamp - $current_timestamp) / 3600; // Difference in hours
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

            // Écouteur d'événements pour les clics sur les boutons d'onglet
            tabButtons.forEach(button => {
                button.addEventListener('click', (event) => {
                    // Empêche le comportement par défaut du lien si existant
                    event.preventDefault();

                    const tabId = button.dataset.tab.replace('-tab', '-content'); // Convertit data-tab="xxx-tab" en id="xxx-content"
                    showTab(tabId);
                });
            });

            // Afficher le premier onglet par défaut au chargement de la page
            if (tabButtons.length > 0) {
                const initialTabId = tabButtons[0].dataset.tab.replace('-tab', '-content');
                showTab(initialTabId);
            }
        });
    </script>
</body>
</html>