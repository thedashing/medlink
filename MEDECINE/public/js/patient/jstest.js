document.addEventListener('DOMContentLoaded', function() {
    // Données simulées pour les rendez-vous
    const appointments = [
        {
            id: 1,
            startTime: '08:00',
            endTime: '08:30',
            patient: 'Adama Traoré',
            type: 'Consultation prénatale',
            clinic: 'Clinique Santé Plus',
            status: 'confirmed'
        },
        {
            id: 2,
            startTime: '09:00',
            endTime: '09:30',
            patient: 'Fatou Diallo',
            type: 'Échographie obstétrique',
            clinic: 'Clinique Santé Plus',
            status: 'confirmed'
        },
        {
            id: 3,
            startTime: '10:00',
            endTime: '10:30',
            patient: 'Boubacar Sawadogo',
            type: 'Consultation',
            clinic: 'Clinique Santé Plus',
            status: 'pending'
        },
        {
            id: 4,
            startTime: '11:00',
            endTime: '11:30',
            patient: 'Aïcha Bamba',
            type: 'Vaccination',
            clinic: 'Clinique Santé Plus',
            status: 'confirmed'
        },
        {
            id: 5,
            startTime: '14:00',
            endTime: '14:30',
            patient: 'Moussa Diarra',
            type: 'Consultation',
            clinic: 'Polyclinique Espoir',
            status: 'cancelled'
        }
    ];

    // Variables pour la gestion du modal
    const modal = document.getElementById('timeslot-modal');
    const openModalBtn = document.querySelector('.btn-primary');
    const closeModalBtns = document.querySelectorAll('.close-modal');
    const timeslotForm = document.getElementById('timeslot-form');

    // Afficher les rendez-vous dans l'agenda
    function renderAppointments() {
        const appointmentsGrid = document.getElementById('appointments-grid');
        appointmentsGrid.innerHTML = '';

        // Créer des créneaux libres
        const allTimeSlots = [
            '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
            '11:00', '11:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'
        ];

        // Trouver les créneaux occupés
        const occupiedSlots = appointments.map(app => {
            return {
                start: app.startTime,
                end: app.endTime
            };
        });

        // Afficher les rendez-vous
        appointments.forEach(appointment => {
            if (appointment.clinic === 'Clinique Santé Plus') {
                const startIndex = allTimeSlots.indexOf(appointment.startTime);
                const endIndex = allTimeSlots.indexOf(appointment.endTime);
                const duration = (endIndex - startIndex) * 60; // en minutes

                const appointmentSlot = document.createElement('div');
                appointmentSlot.className = 'appointment-slot';
                appointmentSlot.style.top = `${startIndex * 60}px`;
                appointmentSlot.style.height = `${duration}px`;
                
                let statusClass = '';
                switch(appointment.status) {
                    case 'confirmed':
                        statusClass = 'confirmed';
                        break;
                    case 'pending':
                        statusClass = 'pending';
                        break;
                    case 'cancelled':
                        statusClass = 'cancelled';
                        break;
                }

                appointmentSlot.innerHTML = `
                    <div class="patient-name">${appointment.patient}</div>
                    <div class="appointment-type">${appointment.type}</div>
                `;

                appointmentSlot.addEventListener('click', () => {
                    alert(`Détails du rendez-vous avec ${appointment.patient}\nType: ${appointment.type}\nStatut: ${appointment.status}`);
                });

                appointmentsGrid.appendChild(appointmentSlot);
            }
        });
    }

    // Gestion du modal
    openModalBtn.addEventListener('click', () => {
        modal.classList.add('active');
    });

    closeModalBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            modal.classList.remove('active');
        });
    });

    // Fermer le modal en cliquant à l'extérieur
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });

    // Gestion du formulaire
    timeslotForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const clinic = document.getElementById('clinic-select').value;
        const date = document.getElementById('date-input').value;
        const startTime = document.getElementById('start-time').value;
        const endTime = document.getElementById('end-time').value;
        const activityType = document.getElementById('activity-type').value;
        const maxPatients = document.getElementById('max-patients').value;

        // Ici, vous enverriez normalement les données au serveur
        console.log('Nouveau créneau:', {
            clinic,
            date,
            startTime,
            endTime,
            activityType,
            maxPatients
        });

        alert('Créneau ajouté avec succès!');
        modal.classList.remove('active');
        timeslotForm.reset();
    });

    // Initialisation
    renderAppointments();

    // Gestion des onglets
    const tabButtons = document.querySelectorAll('.tab-btn');
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            tabButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            // Ici, vous rechargeriez les rendez-vous pour la clinique sélectionnée
        });
    });

    // Gestion des boutons de navigation de date
    const dateNavButtons = document.querySelectorAll('.nav-date');
    dateNavButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Ici, vous changeriez la date affichée et rechargeriez les rendez-vous
            alert('Changement de date - à implémenter');
        });
    });

    // Gestion des actions sur les patients
    const patientActionButtons = document.querySelectorAll('.patient-actions .btn-icon');
    patientActionButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            const action = button.querySelector('i').className;
            const patientCard = button.closest('.patient-card');
            const patientName = patientCard.querySelector('h4').textContent;

            if (action.includes('fa-file-medical')) {
                alert(`Ouvrir le dossier médical de ${patientName}`);
            } else if (action.includes('fa-sms')) {
                alert(`Envoyer un rappel SMS à ${patientName}`);
            } else if (action.includes('fa-times')) {
                if (confirm(`Annuler le rendez-vous de ${patientName} ?`)) {
                    patientCard.style.opacity = '0.5';
                    patientCard.querySelector('.patient-time').innerHTML = `
                        <i class="fas fa-times" style="color: var(--danger-color);"></i>
                        <span style="color: var(--danger-color);">Rendez-vous annulé</span>
                    `;
                }
            }
        });
    });
});