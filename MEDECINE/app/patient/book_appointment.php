<?php
require_once '../../includes/patient/book_appointment_function.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prendre Rendez-vous avec <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/patient/book_appointment.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-calendar-check"></i> Prendre Rendez-vous</h1>
            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($doctor): ?>
            <div class="doctor-info">
                <?php if ($doctor['profile_picture_url']): ?>
                    <img src="../../<?php echo htmlspecialchars($doctor['profile_picture_url']); ?>" alt="Photo de <?php echo htmlspecialchars($doctor['first_name']); ?>">
                <?php else: ?>
                    <img src="assets/images/default-doctor.jpg" alt="Avatar par défaut">
                <?php endif; ?>
                <div class="doctor-details">
                    <h2><i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h2>
                    <p><strong><i class="fas fa-stethoscope"></i> Spécialités :</strong> <?php echo htmlspecialchars($doctor['specialties_names'] ?? 'Non spécifiées'); ?></p>
                    <p><strong><i class="fas fa-info-circle"></i> Bio :</strong> <?php echo nl2br(htmlspecialchars($doctor['bio'] ?? 'Non disponible')); ?></p>
                    <p><strong><i class="fas fa-language"></i> Langues :</strong> <?php echo htmlspecialchars($doctor['language'] ?? 'Non spécifié'); ?></p>
                </div>
            </div>

            <form id="appointmentForm" method="POST">
                <input type="hidden" name="doctor_id" value="<?php echo htmlspecialchars($doctor_id); ?>">
                <input type="hidden" id="selectedClinicId" name="clinic_id" value="<?php echo htmlspecialchars($clinic_id); ?>">
                <input type="hidden" id="selectedServiceId" name="service_id">
                <input type="hidden" id="selectedDatetime" name="selected_datetime">
                <input type="hidden" name="book_appointment" value="1">

                <div class="form-section">
                    <h3><i class="fas fa-clipboard-list"></i> 1. Choisir un Service</h3>
                    <div class="form-group">
                        <label for="service_id"><i class="fas fa-tasks"></i> Sélectionner un Service :</label>
                        <select id="service_id" required>
                            <option value="">-- Choisir un service --</option>
                            <?php foreach ($services_for_clinic_doctor as $service): ?>
                                <option value="<?php echo htmlspecialchars($service['id']); ?>"
                                        data-duration="<?php echo htmlspecialchars($service['duration_minutes']); ?>">
                                    <?php echo htmlspecialchars($service['name'] . ' (' . number_format($service['price'], 2) . ' FCFA - ' . $service['duration_minutes'] . ' min)'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="service_details">
                        <p><i class="fas fa-info-circle"></i> Veuillez sélectionner un service pour voir sa durée.</p>
                    </div>
                </div>

                <div class="form-section">
                    <h3><i class="far fa-calendar-alt"></i> 2. Choisir une Date et un Créneau</h3>
                    <div class="calendar-nav">
                        <button type="button" id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                        <span id="currentMonthYear"></span>
                        <button type="button" id="nextMonth"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <div class="calendar-grid" id="calendarGrid">
                        <div class="calendar-day-header">Lun</div>
                        <div class="calendar-day-header">Mar</div>
                        <div class="calendar-day-header">Mer</div>
                        <div class="calendar-day-header">Jeu</div>
                        <div class="calendar-day-header">Ven</div>
                        <div class="calendar-day-header">Sam</div>
                        <div class="calendar-day-header">Dim</div>
                        <!-- Les jours seront ajoutés dynamiquement par JavaScript -->
                    </div>
                    <div class="time-slots-list" id="timeSlotsList">
                        <p style="text-align: center; color: var(--gray);"><i class="far fa-calendar"></i> Veuillez sélectionner une date pour voir les créneaux disponibles.</p>
                    </div>
                </div>

                <button type="submit" class="submit-button" id="bookBtn" disabled>
                    <i class="fas fa-calendar-plus"></i> Confirmer le Rendez-vous
                </button>
            </form>

        <?php else: ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $message; ?>
            </div>
            <p style="text-align: center; margin-top: 1.5rem;">
                <a href="find_doctor.php" style="color: var(--primary); text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Retourner à la recherche de médecins
                </a>
            </p>
        <?php endif; ?>
    </div>

    <script>
        const doctorId = <?php echo json_encode($doctor_id); ?>;
        const initialClinicId = <?php echo json_encode($clinic_id); ?>;
        // servicesData contiendra la liste des services pour la clinique INITIALE
        let servicesData = <?php echo json_encode($services_for_clinic_doctor); ?>;
          const calendarGrid = document.getElementById('calendarGrid');
        const currentMonthYearSpan = document.getElementById('currentMonthYear');
        const prevMonthBtn = document.getElementById('prevMonth');
        const nextMonthBtn = document.getElementById('nextMonth');
        const timeSlotsList = document.getElementById('timeSlotsList');
        const selectedDatetimeInput = document.getElementById('selectedDatetime');
        const bookBtn = document.getElementById('bookBtn');
        const serviceSelect = document.getElementById('service_id');
        const clinicSelect = document.getElementById('clinic_select');
        const selectedClinicIdInput = document.getElementById('selectedClinicId');
        const serviceDetailsDiv = document.getElementById('service_details');


        let currentCalendarDate = new Date(); // La date actuellement affichée dans le calendrier
        let selectedDayElement = null; // Pour suivre le jour sélectionné visuellement
        let selectedSlotElement = null; // Pour suivre le créneau sélectionné visuellement
        let selectedServiceId = null; // Pour stocker l'ID du service sélectionné
        // Initialise selectedServiceDuration avec le premier service de la liste initiale, ou 30 par défaut
        let selectedServiceDuration = servicesData.length > 0 ? servicesData[0].duration_minutes : 30;

        // --- Fonctions utilitaires ---

        // Met à jour la visibilité du bouton de réservation
        function updateBookButtonState() {
            const hasService = !!selectedServiceId; // Vérifie si selectedServiceId est défini
            const hasDate = !!selectedDayElement;
            const hasTime = !!selectedSlotElement;
            bookBtn.disabled = !(hasService && hasDate && hasTime);
        }

        // Affiche les détails du service et met à jour la durée sélectionnée
        function updateServiceDetails() {
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            if (selectedOption.value) {
                const serviceId = selectedOption.value;
                const duration = parseInt(selectedOption.dataset.duration);
                const name = selectedOption.textContent.split('(')[0].trim(); // Récupère le nom sans le prix/durée
                
                selectedServiceId = serviceId; // Met à jour la variable globale pour la soumission
                document.getElementById('selectedServiceId').value = serviceId; // Met à jour l'input caché

                selectedServiceDuration = duration;
                serviceDetailsDiv.innerHTML = `<p><strong>Service sélectionné :</strong> ${name}</p><p><strong>Durée estimée :</strong> ${duration} minutes</p>`;

                // Si une date est déjà sélectionnée, rafraîchir les créneaux avec la nouvelle durée
                if (selectedDayElement) {
                    displayTimeSlots(selectedDayElement.dataset.date);
                }
            } else {
                selectedServiceId = null;
                document.getElementById('selectedServiceId').value = '';
                selectedServiceDuration = 30; // Réinitialiser à la durée par défaut si aucun service
                serviceDetailsDiv.innerHTML = '<p>Veuillez sélectionner un service pour voir sa durée.</p>';
            }
            updateBookButtonState();
        }


        // Fonction pour générer le calendrier
        function generateCalendar(year, month) {
            calendarGrid.innerHTML = ''; // Nettoyer l'ancienne grille
            currentMonthYearSpan.textContent = new Date(year, month).toLocaleString('fr-FR', { month: 'long', year: 'numeric' });

            const firstDayOfMonth = new Date(year, month, 1);
            const lastDayOfMonth = new Date(year, month + 1, 0);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Pour comparer juste les dates

            // Jour de la semaine du premier jour du mois, en faisant Lundi = 0, Mardi = 1, ..., Dimanche = 6
            let startDayIndex = firstDayOfMonth.getDay(); // 0 (Dim) ... 6 (Sam)
            if (startDayIndex === 0) { // Si c'est Dimanche, on veut qu'il soit la dernière colonne (index 6)
                startDayIndex = 6;
            } else { // Sinon, décaler Lundi (1) à 0, Mardi (2) à 1, etc.
                startDayIndex--;
            }
            
            // Remplir les jours vides avant le premier jour du mois
            for (let i = 0; i < startDayIndex; i++) {
                const emptyDay = document.createElement('div');
                emptyDay.classList.add('calendar-day', 'empty');
                calendarGrid.appendChild(emptyDay);
            }

            for (let day = 1; day <= lastDayOfMonth.getDate(); day++) {
                const date = new Date(year, month, day+1); // Utiliser 'day' directement pour la date
                const dayElement = document.createElement('div');
                dayElement.classList.add('calendar-day');
                dayElement.textContent = day;
                dayElement.dataset.date = date.toISOString().split('T')[0]; // Format YYYY-MM-DD

                // Styles pour les jours passés et le jour actuel
                if (date.toDateString() === today.toDateString()) {
                    dayElement.classList.add('current-day');
                }
                if (date < today) {
                    dayElement.classList.add('past-day');
                }

                // La classe 'has-slots' sera ajoutée par AJAX si nécessaire (voir fetchHasSlotsForMonth ci-dessous)
                
                dayElement.addEventListener('click', () => {
                    if (dayElement.classList.contains('past-day') || dayElement.classList.contains('disabled')) {
                        return; // Ne rien faire pour les jours passés ou désactivés
                    }
                    if (selectedDayElement) {
                        selectedDayElement.classList.remove('selected');
                    }
                    dayElement.classList.add('selected');
                    selectedDayElement = dayElement;
                    
                    // Réinitialiser le créneau sélectionné lorsque le jour change
                    if (selectedSlotElement) {
                        selectedSlotElement.classList.remove('selected-slot');
                        selectedSlotElement = null;
                    }
                    selectedDatetimeInput.value = '';
                    
                    displayTimeSlots(dayElement.dataset.date);
                    updateBookButtonState();
                });
                calendarGrid.appendChild(dayElement);
            }
            // Restaurer la sélection visuelle si l'utilisateur revient sur le mois du jour sélectionné
            if (selectedDayElement && selectedDayElement.dataset.date.startsWith(`${year}-${String(month + 1).padStart(2, '0')}`)) {
                const currentSelectedDate = new Date(selectedDayElement.dataset.date);
                if (currentSelectedDate.getFullYear() === year && currentSelectedDate.getMonth() === month) {
                    const dayToSelect = calendarGrid.querySelector(`[data-date="${selectedDayElement.dataset.date}"]`);
                    if (dayToSelect) {
                        dayToSelect.classList.add('selected');
                    }
                }
            }
             // Après avoir généré le calendrier, récupérer les jours ayant des créneaux pour ce mois
            fetchHasSlotsForMonth(year, month);
        }

        // Nouvelle fonction pour récupérer les jours ayant des créneaux pour le mois affiché
        async function fetchHasSlotsForMonth(year, month) {
            const clinicId = selectedClinicIdInput.value;
            const monthStr = String(month + 1).padStart(2, '0');
            const yearMonth = `${year}-${monthStr}`;

            try {
                const response = await fetch('get_available_days_for_month.php', { // Nouveau endpoint à créer !
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `doctor_id=${doctorId}&clinic_id=${clinicId}&year_month=${yearMonth}&service_duration=${selectedServiceDuration}`
                });
                const daysWithSlots = await response.json(); // Ex: ['2025-07-10', '2025-07-15']

                daysWithSlots.forEach(dateStr => {
                    const dayElement = calendarGrid.querySelector(`[data-date="${dateStr}"]`);
                    if (dayElement && !dayElement.classList.contains('past-day')) {
                        dayElement.classList.add('has-slots');
                    }
                });
            } catch (error) {
                console.error('Erreur lors de la récupération des jours avec créneaux :', error);
            }
        }


        // Fonction pour afficher les créneaux horaires d'une date sélectionnée
        function displayTimeSlots(date) {
            timeSlotsList.innerHTML = ''; // Nettoyer les anciens créneaux
            selectedDatetimeInput.value = ''; // Réinitialiser le champ caché
            bookBtn.disabled = true; // Désactiver le bouton de confirmation

            const clinicId = selectedClinicIdInput.value; // Utiliser la valeur de l'input caché

            // Vérifier si un service est sélectionné et sa durée
            if (!selectedServiceId) {
                timeSlotsList.innerHTML = '<p style="text-align: center; color: #777;">Veuillez d\'abord sélectionner un service.</p>';
                return;
            }

            // Envoyer une requête AJAX pour obtenir les créneaux mis à jour
            fetch('get_available_slots.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `doctor_id=${doctorId}&clinic_id=${clinicId}&date=${date}&service_duration=${selectedServiceDuration}`
            })
            .then(response => response.json())
            .then(slots => {
                if (slots.error) { // Gérer les erreurs renvoyées par le serveur PHP
                    timeSlotsList.innerHTML = `<p style="text-align: center; color: red;">Erreur: ${slots.error}</p>`;
                    return;
                }
                if (slots.length === 0) {
                    timeSlotsList.innerHTML = '<p style="text-align: center; color: #777;">Aucun créneau disponible pour cette date.</p>';
                } else {
                    slots.forEach(slot => {
                        const slotElement = document.createElement('div');
                        slotElement.classList.add('time-slot');
                        slotElement.textContent = slot.start; // Ex: "09:00"
                        slotElement.dataset.fullDatetime = slot.full_start; // Ex: "2025-06-15 09:00:00"

                        slotElement.addEventListener('click', () => {
                            if (selectedSlotElement) {
                                selectedSlotElement.classList.remove('selected-slot');
                            }
                            slotElement.classList.add('selected-slot');
                            selectedSlotElement = slotElement;
                            selectedDatetimeInput.value = slotElement.dataset.fullDatetime;
                            updateBookButtonState();
                        });
                        timeSlotsList.appendChild(slotElement);
                    });
                }
            })
            .catch(error => {
                console.error('Erreur lors de la récupération des créneaux :', error);
                timeSlotsList.innerHTML = '<p style="text-align: center; color: red;">Erreur lors du chargement des créneaux.</p>';
            });
        }

        // Fonction pour charger les services dynamiquement
        async function loadServicesForClinic(clinicId) {
            try {
                const response = await fetch('get_services_for_clinic.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `doctor_id=${doctorId}&clinic_id=${clinicId}`
                });
                const services = await response.json();
                
                // Mettre à jour la variable globale servicesData
                servicesData = services;

                // Remplir le select des services
                serviceSelect.innerHTML = '<option value="">-- Choisir un service --</option>';
                if (services.length > 0) {
                    services.forEach(service => {
                        const option = document.createElement('option');
                        option.value = service.id;
                        option.dataset.duration = service.duration_minutes;
                        option.textContent = `${service.name} (${parseFloat(service.price).toFixed(2)} € - ${service.duration_minutes} min)`;
                        serviceSelect.appendChild(option);
                    });
                    // Sélectionner le premier service par défaut et mettre à jour les détails
                    serviceSelect.value = services[0].id;
                } else {
                    serviceSelect.innerHTML = '<option value="">Aucun service disponible pour cette clinique et ce médecin.</option>';
                }
                // Déclencher la mise à jour des détails du service et la durée
                updateServiceDetails(); 

                // Après avoir mis à jour les services, rafraîchir le calendrier et les créneaux si une date est sélectionnée
                // Cela permettra de s'assurer que les créneaux affichés sont pour le bon service
                generateCalendar(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth());
                if (selectedDayElement) {
                    displayTimeSlots(selectedDayElement.dataset.date);
                } else {
                    timeSlotsList.innerHTML = '<p style="text-align: center; color: #777;">Veuillez sélectionner une date pour voir les créneaux disponibles.</p>';
                }

            } catch (error) {
                console.error('Erreur lors du chargement des services :', error);
                serviceSelect.innerHTML = '<option value="">Erreur de chargement des services.</option>';
                serviceDetailsDiv.innerHTML = '<p style="color: red;">Erreur lors du chargement des services.</p>';
            }
        }


        // --- Écouteurs d'événements ---

        prevMonthBtn.addEventListener('click', () => {
            currentCalendarDate.setMonth(currentCalendarDate.getMonth() - 1);
            generateCalendar(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth());
            // Réinitialiser la sélection de jour et de créneau après changement de mois
            if (selectedDayElement) { selectedDayElement.classList.remove('selected'); selectedDayElement = null; }
            if (selectedSlotElement) { selectedSlotElement.classList.remove('selected-slot'); selectedSlotElement = null; }
            selectedDatetimeInput.value = '';
            timeSlotsList.innerHTML = '<p style="text-align: center; color: #777;">Veuillez sélectionner une date pour voir les créneaux disponibles.</p>';
            updateBookButtonState();
        });

        nextMonthBtn.addEventListener('click', () => {
            currentCalendarDate.setMonth(currentCalendarDate.getMonth() + 1);
            generateCalendar(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth());
            // Réinitialiser la sélection de jour et de créneau après changement de mois
            if (selectedDayElement) { selectedDayElement.classList.remove('selected'); selectedDayElement = null; }
            if (selectedSlotElement) { selectedSlotElement.classList.remove('selected-slot'); selectedSlotElement = null; }
            selectedDatetimeInput.value = '';
            timeSlotsList.innerHTML = '<p style="text-align: center; color: #777;">Veuillez sélectionner une date pour voir les créneaux disponibles.</p>';
            updateBookButtonState();
        });

        serviceSelect.addEventListener('change', updateServiceDetails);

        if (clinicSelect) {
            clinicSelect.addEventListener('change', () => {
                const newClinicId = clinicSelect.value;
                selectedClinicIdInput.value = newClinicId; // Met à jour l'input caché de la clinique
                loadServicesForClinic(newClinicId); // Charge les services pour la nouvelle clinique
            });
        }

        // --- Initialisation au chargement de la page ---
        document.addEventListener('DOMContentLoaded', () => {
            generateCalendar(currentCalendarDate.getFullYear(), currentCalendarDate.getMonth());
            // Initialise les détails du service pour la sélection par défaut
            updateServiceDetails(); 
            updateBookButtonState();

            // Si des services sont chargés initialement, essayez de sélectionner le premier
            if (servicesData.length > 0) {
                serviceSelect.value = servicesData[0].id;
                updateServiceDetails(); // Pour s'assurer que la durée et l'input caché sont bien définis
            }
        });
    </script>
    <!-- <script src="../../public/js/patient/book_appointment.js"></script> -->
    
</body>
</html>