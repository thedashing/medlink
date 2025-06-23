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
                const response = await fetch('../../includes/patient/data_slot_service/get_available_days_for_month.php', { // Nouveau endpoint à créer !
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
            fetch('../../includes/patient/data_slot_service/get_available_slots.php', {
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
                const response = await fetch('../../includes/patient/data_slot_service/get_services_for_clinic.php', {
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