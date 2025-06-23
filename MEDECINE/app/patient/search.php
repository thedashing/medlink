
<?php
require_once '../../includes/patient/search_function.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medlink - Trouvez votre médecin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../../public/css/patient/test.css">

</head>

<body>
    <header class="patient-header">
        <div class="container">
            <div class="logo">
                <img src="d5.jpg" alt="Medlink Logo">
            </div>
            <nav class="patient-nav">
                <ul>
                    <li><a href="../patient/dashboard_patient.php" class="active">
                        <i class="fas fa-search"></i> Tableau de Bord</a>
                    </li>
                    <li><a href="../patient/mes_rendez_vous.php">
                        <i class="fas fa-calendar-alt"></i> Mes rendez-vous</a>
                    </li>
                    <li><a href="../patient/mon_dossier.php">
                        <i class="fas fa-file-medical"></i> Mon dossier médical</a>
                    </li> 
                    <a href="messagerie.php">
                    e
                    <?php if ($unread_messages_count > 0): ?>
                        <span class="message-badge"><?php echo $unread_messages_count; ?></span>
                    <?php endif; ?>
                </a>
                        <li><a href="../securite/logout.php">
                        <i class="fas fa-file-medical"></i> Se déconnecter</a>
                    </li>
                </ul>
            </nav>

            <div class="patient-account">
                <?php if (is_logged_in()): ?>
                <img src="d4.jpg" alt="Photo profil" class="profile-img">
                <span><?php echo $user_email; ?></span>
                <i class="fas fa-chevron-down"></i>
                 <?php else: ?>
                <p>Non connecté. <a href="../../login.php">Se connecter</a> ou <a href="../../register.php">S'inscrire</a> pour prendre rendez-vous.</p>
            <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="patient-main">
        <section class="search-section">
            <div class="container">
                <h1>Prenez rendez-vous en ligne facilement</h1>
                <p>Trouvez un médecin disponible près de chez vous et réservez en quelques clics</p>
                    <?php if (!empty($message)): ?>
                        <div class="system-message <?php echo $message_type; ?>">
                            <?php echo $message; ?>
                         </div>
                    <?php endif; ?>
                <div class="search-box">
                    <div class="search-tabs">
                        <button class="tab-btn active">Médecin</button>
                        <button class="tab-btn">Clinique</button>
                        <button class="tab-btn">Spécialité</button>
                    </div>

                    <form action="" method="GET" class="search-form">
                        <div class="form-group">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" id="location" name="location" placeholder="Ex: Ouagadougou, 123 Rue Principale" value="<?php echo htmlspecialchars($prev_location); ?>">
                        </div>
                        <div class="form-group">
                            <i class="fas fa-stethoscope"></i>
                            <select id="specialty" name="specialty">
                                <option value="">Toutes les spécialités</option>
                                <?php foreach ($specialties as $specialty): ?>
                                    <option value="<?php echo htmlspecialchars($specialty['id']); ?>"
                                        <?php echo ($prev_specialty == $specialty['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($specialty['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <select id="language" name="language">
                                <option value="">Toutes les langues</option>
                                    <?php foreach ($languages as $lang): ?>
                                        <option value="<?php echo htmlspecialchars($lang['language']); ?>"
                                            <?php echo ($prev_language == $lang['language']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($lang['language']); ?>
                                        </option>
                                    <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="search" class="search-btn">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </form>
                </div>
            </div>
        </section>

        <section class="results-section">
            <div class="container">
                <div class="section-header">
                    <h2>Médecins disponibles près de vous</h2>
                    <div class="sort-filter">
                        <span>Trier par :</span>
                        <select id="sort-select">
                            <option value="distance">Plus proche</option>
                            <option value="availability">Plus tôt disponible</option>
                            <option value="rating">Mieux notés</option>
                            <option value="price">Prix croissant</option>
                        </select>
                    </div>
                </div>
                
                <?php if (empty($clinics) && isset($_GET['search'])): ?>
                    <div class="no-results">
                        <p>Aucune clinique trouvée correspondant à vos critères de recherche.</p>
                        <p>Essayez d'ajuster vos filtres ou de rechercher plus largement.</p>
                    </div>
                <?php elseif (empty($clinics) && !isset($_GET['search'])): ?>
                    <div class="no-results">
                        <p>Utilisez le formulaire ci-dessus pour rechercher des cliniques.</p>
                        <p>Vous pouvez filtrer par spécialité, localisation ou langue des médecins.</p>
                    </div>
                <?php else: ?>


                <div class="medical-grid" id="medical-list">
                    <!-- Clinique - Version Premium -->
                     <?php foreach ($clinics as $clinic): ?>
                        <div class="medical-card clinic-card">
                            <div class="medical-badge">Équipements High-Tech</div>

                            <div class="medical-header">
                                <img src="../../Uploads/d1.jpg" alt="Clinic St Marie" class="medical-image">
                                <div class="medical-actions">
                                    <button class="action-btn like-btn" aria-label="Ajouter aux favoris" data-likes="24">
                                        <i class="fas fa-heart"></i>
                                    </button>
                                    <button class="action-btn share-btn" aria-label="Partager">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="medical-body">
                                <div class="medical-meta">
                                    <span class="meta-tag emergency">Urgences 24/7</span>
                                    <span class="meta-tag parking">Parking gratuit</span>
                                </div>

                                <h3 class="medical-title">
                                    <i class="fas fa-hospital"></i> <?php echo htmlspecialchars($clinic['clinic_name']); ?>
                                    <span class="verified-badge" title="Établissement certifié">
                                        <i class="fas fa-check-circle"></i>
                                    </span>
                                </h3>

                                <div class="medical-specialties">
                                    <div class="specialty-carousel">
                                        <div class="specialty-item active">
                                            <i class="fas fa-heartbeat"></i>
                                            <span><span class="specialties"><?php echo htmlspecialchars($clinic['clinic_available_specialties'] ?: 'Aucune spécialité spécifiée'); ?></span>
                                        </div>
                                        
                                    </div>
                                    <button class="carousel-control next-specialty">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>

                                <div class="medical-details">
                                    <div class="detail-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <div>
                                            <strong>Quartier des Médecins</strong>
                                            <small><?php echo htmlspecialchars($clinic['clinic_address']); ?>, <?php echo htmlspecialchars($clinic['clinic_city']); ?></span></small>
                                        </div>
                                    </div>

                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <div class="schedule-accordion">
                                            <div class="accordion-header">
                                                <strong>Voir les horaires</strong>
                                                <i class="fas fa-chevron-down"></i>
                                            </div>
                                            <div class="accordion-content">
                                                <table class="schedule-table">
                                                    <tr>
                                                        <th>Lun-Ven</th>
                                                        <td>7h30 - 19h00</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Samedi</th>
                                                        <td>8h00 - 16h00</td>
                                                    </tr>
                                                    <tr>
                                                        <th>Dimanche</th>
                                                        <td>Urgences seulement</td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="detail-item">
                                        <i class="fas fa-phone-alt"></i>
                                        <div>
                                            <strong><?php echo htmlspecialchars($clinic['clinic_email'] ?: 'Non spécifié'); ?></strong>
                                            <strong><?php echo htmlspecialchars($clinic['clinic_phone'] ?: 'Non spécifié'); ?></strong>
                                            <small>Standard ouvert 24h/24</small>
                                        </div>
                                        <div>
                                            <strong><span class="specialties"><?php echo htmlspecialchars($clinic['clinic_available_specialties'] ?: 'Aucune spécialité spécifiée'); ?></strong>
                                            <strong><span class="languages"><?php echo htmlspecialchars($clinic['clinic_available_languages'] ?: 'Aucune langue spécifiée'); ?></strong>
                                            
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="medical-footer">
                                <button class="btn primary-btn appointment-btn">
                                     <a href="view_clinic_doctors.php?clinic_id=<?php echo $clinic['clinic_id']; ?>" class="button_link">
                                    <i class="fas fa-calendar-check"></i> Prendre RDV
                                    </a>
                                </button>
                                <button class="btn secondary-btn direction-btn">
                                    <i class="fas fa-directions"></i> Itinéraire
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>

                  
                </div>
        <?php endif; ?> 
        </section>
    </main>

    <!-- Modal de prise de rendez-vous -->
    <div class="modal" id="booking-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Prendre rendez-vous</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="doctor-info">
                    <img src="https://via.placeholder.com/80?text=DR" alt="Photo du médecin">
                    <div>
                        <h4>Dr. Aminata Kone</h4>
                        <p class="specialty">Gynécologue</p>
                        <div class="rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                            <span>4.5 (32 avis)</span>
                        </div>
                    </div>
                </div>

                <div class="clinic-info">
                    <p><i class="fas fa-map-marker-alt"></i> Clinique Santé Plus, Ouagadougou</p>
                    <p><i class="fas fa-money-bill-wave"></i> Consultation: 15,000 FCFA</p>
                </div>

                <form id="booking-form">
                    <div class="form-group">
                        <label for="booking-date">Date</label>
                        <select id="booking-date" class="form-control">
                            <option value="">Sélectionner une date</option>
                            <option value="2023-05-25">Jeudi 25 Mai</option>
                            <option value="2023-05-26">Vendredi 26 Mai</option>
                            <option value="2023-05-29">Lundi 29 Mai</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="booking-time">Créneau horaire</label>
                        <div class="time-slots" id="time-slots">
                            <!-- Créneaux chargés dynamiquement -->
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="booking-reason">Motif de consultation</label>
                        <textarea id="booking-reason" class="form-control"
                            placeholder="Décrivez brièvement la raison de votre consultation"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn btn-outline close-modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Confirmer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation -->
    <div class="modal" id="confirmation-modal">
        <div class="modal-content confirmation">
            <div class="modal-header">
                <h3>Rendez-vous confirmé!</h3>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="confirmation-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h4>Votre rendez-vous est confirmé</h4>
                <p class="appointment-details">
                    <strong>Dr. Aminata Kone</strong><br>
                    <i class="fas fa-calendar-alt"></i> Jeudi 25 Mai 2023 à 10:00<br>
                    <i class="fas fa-map-marker-alt"></i> Clinique Santé Plus, Ouagadougou
                </p>

                <div class="confirmation-actions">
                    <button class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Ajouter à mon calendrier
                    </button>
                    <button class="btn btn-outline">
                        <i class="fas fa-share-alt"></i> Partager
                    </button>
                </div>

                <div class="confirmation-notice">
                    <p><i class="fas fa-info-circle"></i> Vous recevrez un SMS de rappel 24h avant votre rendez-vous.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Gestion des likes
            document.querySelectorAll('.like-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    const currentLikes = parseInt(this.getAttribute('data-likes'));
                    if (this.classList.contains('liked')) {
                        this.classList.remove('liked');
                        this.setAttribute('data-likes', currentLikes - 1);
                    } else {
                        this.classList.add('liked');
                        this.setAttribute('data-likes', currentLikes + 1);
                    }
                });
            });

            // Accordéon des horaires
            document.querySelectorAll('.accordion-header').forEach(header => {
                header.addEventListener('click', function () {
                    this.classList.toggle('active');
                    const content = this.nextElementSibling;
                    content.style.maxHeight = content.style.maxHeight ? null : content.scrollHeight + 'px';
                });
            });

            // Carousel des spécialités
            let currentSlide = 0;
            const specialtyItems = document.querySelectorAll('.specialty-item');
            const nextBtn = document.querySelector('.next-specialty');

            if (nextBtn) {
                nextBtn.addEventListener('click', function () {
                    specialtyItems[currentSlide].classList.remove('active');
                    currentSlide = (currentSlide + 1) % specialtyItems.length;
                    specialtyItems[currentSlide].classList.add('active');
                });
            }

            // Tooltips pour les boutons d'action
            document.querySelectorAll('.action-btn').forEach(btn => {
                btn.addEventListener('mouseenter', function () {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'action-tooltip';
                    tooltip.textContent = this.getAttribute('aria-label');
                    document.body.appendChild(tooltip);

                    const rect = this.getBoundingClientRect();
                    tooltip.style.left = `${rect.left + window.scrollX}px`;
                    tooltip.style.top = `${rect.top + window.scrollY - 35}px`;

                    setTimeout(() => {
                        tooltip.classList.add('show');
                    }, 10);

                    this.addEventListener('mouseleave', function () {
                        tooltip.classList.remove('show');
                        setTimeout(() => {
                            document.body.removeChild(tooltip);
                        }, 300);
                    }, { once: true });
                });
            });
        });
    </script>
</body>

</html>