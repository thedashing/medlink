<?php
require_once '../../includes/patient/rendezvous_function.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medlink - Mes Rendez-vous</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../../public/css/patient/rendezvoustest.css">

    <style>
        :root {
            --primary: #4F46E5;
            --primary-light: #6366F1;
            --secondary: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-500: #6B7280;
            --gray-700: #374151;
            --gray-900: #111827;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        
        body {
            background-color: #f9fafb;
            color: var(--gray-900);
            line-height: 1.6;
        }
        
        .patient-main {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        /* Header & Tabs */
        .section-header {
            margin-bottom: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .section-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            position: relative;
            display: inline-block;
        }
        
        .section-header h1::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }
        
        .appointments-tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 1px solid var(--gray-200);
            padding-bottom: 0.5rem;
            overflow-x: auto;
        }
        
        .tab-btn {
            padding: 0.75rem 1.5rem;
            border: none;
            background: transparent;
            color: var(--gray-500);
            font-weight: 600;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        
        .tab-btn.active {
            color: var(--primary);
            background: rgba(79, 70, 229, 0.1);
            position: relative;
        }
        
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary);
            border-radius: 3px 3px 0 0;
        }
        
        /* Cards Container */
        .appointments-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);

            gap: 1.5rem;
        }
        
        .tab-content {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .tab-content.active {
            display: grid;
            animation: fadeIn 0.5s ease;
        }
        
        /* Appointment Card */
        .appointment-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-left: 4px solid var(--primary);
            display: flex;
            flex-direction: column;
        }
        
        .appointment-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        
        .appointment-card.past {
            border-left-color: var(--gray-500);
            opacity: 0.9;
        }
        
        .appointment-card.canceled {
            border-left-color: var(--danger);
            position: relative;
        }
        
        .appointment-card.canceled::before {
            content: 'Annulé';
            position: absolute;
            top: 12px;
            right: -30px;
            background: var(--danger);
            color: white;
            padding: 0.25rem 2rem;
            transform: rotate(45deg);
            font-size: 0.75rem;
            font-weight: bold;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        
        .appointment-info {
            padding: 1.5rem;
        }
        
        .doctor-info {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
        }
        
        .doctor-info img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-light);
        }
        
        .doctor-info h3 {
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            color: var(--gray-900);
        }
        
        .specialty {
            color: var(--primary);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .rating {
            color: var(--warning);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .rating span {
            color: var(--gray-700);
            margin-left: 0.25rem;
        }
        
        .appointment-details {
            display: grid;
            gap: 0.75rem;
        }
        
        .appointment-details p {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }
        
        .appointment-details i {
            color: var(--primary);
            width: 20px;
            text-align: center;
        }
        
        .cancel-reason {
            color: var(--danger);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .appointment-actions {
            padding: 1rem 1.5rem;
            background: var(--gray-100);
            border-top: 1px solid var(--gray-200);
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: auto;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: 1px solid transparent;
        }
        
        .btn i {
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-light);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: white;
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-secondary:hover {
            background: rgba(79, 70, 229, 0.1);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--gray-700);
            border-color: var(--gray-300);
        }
        
        .btn-outline:hover {
            background: var(--gray-100);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #DC2626;
        }
        
        /* Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transform: translateY(0);
            animation: slideUp 0.3s ease;
        }
        
        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: 1.25rem;
            color: var(--gray-900);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-500);
            transition: color 0.2s ease;
        }
        
        .close-modal:hover {
            color: var(--danger);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-body p {
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--gray-200);
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .modal-notice {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-notice i {
            font-size: 1.1rem;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                transform: translateY(20px);
                opacity: 0;
            }
            to { 
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .tab-content {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .appointments-tabs {
                width: 100%;
            }
            
            .appointment-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Micro-interactions */
        .btn:active {
            transform: scale(0.98);
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: auto;
        }
        
        .status-upcoming {
            background: rgba(16, 185, 129, 0.1);
            color: var(--secondary);
        }
        
        .status-past {
            background: rgba(107, 114, 128, 0.1);
            color: var(--gray-500);
        }
        
        .status-canceled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
    </style>

</head>
<body>
   <header class="patient-header">
        <div class="container">
            <div class="logo">
                <img src="d5.jpg" alt="Medlink Logo">
            </div>
            <nav class="patient-nav">
                <ul>
                    <li><a href="../patient/dashboard_patient.php" >
                        <i class="fas fa-search"></i> Tableau de Bord</a>
                    </li>
                    <li><a href="../patient/mes_rendez_vous.php" class="active">
                        <i class="fas fa-calendar-alt"></i> Mes rendez-vous</a>
                    </li>
                    <li><a href="../patient/mon_dossier.php">
                        <i class="fas fa-file-medical"></i> Mon dossier médical</a>
                    </li> 
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
        <section class="appointments-section">
            <div class="container">
                <div class="section-header">
                    <h1>Mes rendez-vous</h1>
                    <div class="appointments-tabs">
                        <button class="tab-btn active" data-tab="upcoming"><a href="#avenir">À venir</a> <span class="status-badge status-upcoming">2</span></button>
                        <button class="tab-btn" data-tab="past"><a href="#historique">Passés </a><span class="status-badge status-past">1</span></button>
                        <button class="tab-btn" data-tab="canceled"><a href="#annuler">Annulés</a> <span class="status-badge status-canceled">1</span></button>
                    </div>
                </div>
                 <?php if (!empty($message)): ?>
                    <div class="system-message <?php echo $message_type; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <div id="avenir" class="appointments-container">
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
                    <!-- Onglet "À venir" -->
                            <div class="tab-content active" id="upcoming">

                                <div class="appointment-card upcoming">
                                    <div class="appointment-info">
                                        <div class="doctor-info">
                                            <?php if ($appointment['profile_picture_url']): ?>
                                                <img src="../../<?php echo htmlspecialchars($appointment['profile_picture_url']); ?>" alt="Photo de <?php echo htmlspecialchars($doctor['first_name']); ?>">
                                                <?php else: ?>
                                                    <img src="../../Uploads/d1.jpg" alt="Avatar par défaut">
                                                <?php endif; ?>
                                            <div>
                                                <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h3>
                                                <p class="specialty"><?php echo htmlspecialchars($appointment['service_name']); ?> </p>
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
                                            <p><i class="fas fa-clock"></i>  <?php echo date('H:i', strtotime($appointment['appointment_datetime'])); ?>-<?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                                            <p><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                                            <p><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?> FCFA</p>
                                            <p><i class="fas fa-money-bill-wave"></i>Statut: <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="appointment-actions">
                                        <button class="btn btn-outline cancel-appointment">
                                              <a href="../../includes/patient/cancel_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                                class="action-button <?php echo !$can_cancel ? 'disabled' : ''; ?>" 
                                                onclick="return <?php echo $can_cancel ? "confirm('Êtes-vous sûr de vouloir annuler ce rendez-vous ?')" : "false"; ?>;"
                                                <?php echo !$can_cancel ? 'title="Annulation non autorisée moins de 24h avant le rendez-vous." onclick="return false;"' : ''; ?>>
                                                    <i class="fas fa-times"></i> Annuler
                                            </a>
                                            
                                        </button>
                                        <button class="btn btn-primary">
                                            <a href="reschedule_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                                class="action-button reschedule <?php echo !$can_cancel ? 'disabled' : ''; ?>"
                                                <?php echo !$can_cancel ? 'title="Modification non autorisée moins de 24h avant le rendez-vous." onclick="return false;"' : ''; ?>>
                                                     Reporter
                                            </a>
                                            
                                        </button>
                                       
                                    </div>
                                </div>

                               
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div> 
                
                     <h2 class="section-title">Historique des Rendez-vous</h2>

                <div id="historique" class="appointments-container">
                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="no-appointments">
                            <p>Vous n'avez aucun rendez-vous à venir. <a href="../../search.php">Rechercher un médecin pour prendre rendez-vous</a>.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($past_completed_appointments as $appointment): ?>
                            <?php
                                // Calculate time difference for cancellation policy (24 hours)
                                $appointment_timestamp = strtotime($appointment['appointment_datetime']);
                                $current_timestamp = time();
                                $time_diff_hours = ($appointment_timestamp - $current_timestamp) / 3600; // Difference in hours

                                $can_cancel = ($time_diff_hours > 24);
                            ?>
                    <!-- Onglet "À venir" -->
                            <div class="tab-content active" id="upcoming">

                                <div class="appointment-card upcoming">
                                    <div class="appointment-info">
                                         <div class="doctor-info">
                                            <?php if ($appointment['profile_picture_url']): ?>
                                                <img src="../../<?php echo htmlspecialchars($appointment['profile_picture_url']); ?>" alt="Photo de <?php echo htmlspecialchars($doctor['first_name']); ?>">
                                                <?php else: ?>
                                                    <img src="../../Uploads/d1.jpg" alt="Avatar par défaut">
                                                <?php endif; ?>
                                            <div>
                                                <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h3>
                                                <p class="specialty"><?php echo htmlspecialchars($appointment['service_name']); ?> </p>
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
                                            <p><i class="fas fa-clock"></i>  <?php echo date('H:i', strtotime($appointment['appointment_datetime'])); ?>-<?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                                            <p><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                                            <p><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?> FCFA</p>
                                            <p><i class="fas fa-money-bill-wave"></i>Statut: <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></p>
                                        </div>
                                    </div>
                                      <div class="appointment-actions">
                                        <p>Terminer</p>
                                    </div>
                                </div>

                               
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>

                <h2 class="section-title">Rendez-vous Annulés</h2>
                <div id="annuler" class="appointments-container">
                    <?php if (empty($upcoming_appointments)): ?>
                        <div class="no-appointments">
                            <p>Vous n'avez aucun rendez-vous à venir. <a href="../../search.php">Rechercher un médecin pour prendre rendez-vous</a>.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($cancelled_appointments as $appointment): ?>
                            <?php
                                // Calculate time difference for cancellation policy (24 hours)
                                $appointment_timestamp = strtotime($appointment['appointment_datetime']);
                                $current_timestamp = time();
                                $time_diff_hours = ($appointment_timestamp - $current_timestamp) / 3600; // Difference in hours

                                $can_cancel = ($time_diff_hours > 24);
                            ?>
                    <!-- Onglet "À venir" -->
                            <div class="tab-content active" id="upcoming">

                                <div class="appointment-card upcoming">
                                    <div class="appointment-info">
                                         <div class="doctor-info">
                                            <?php if ($appointment['profile_picture_url']): ?>
                                                <img src="../../<?php echo htmlspecialchars($appointment['profile_picture_url']); ?>" alt="Photo de <?php echo htmlspecialchars($doctor['first_name']); ?>">
                                                <?php else: ?>
                                                    <img src="../../Uploads/d1.jpg" alt="Avatar par défaut">
                                                <?php endif; ?>
                                            <div>
                                                <h3>Dr. <?php echo htmlspecialchars($appointment['doctor_first_name'] . ' ' . $appointment['doctor_last_name']); ?></h3>
                                                <p class="specialty"><?php echo htmlspecialchars($appointment['service_name']); ?> </p>
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
                                            <p><i class="fas fa-clock"></i>  <?php echo date('H:i', strtotime($appointment['appointment_datetime'])); ?>-<?php echo date('H:i', strtotime($appointment['end_datetime'])); ?></p>
                                            <p><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($appointment['clinic_name']); ?>, <?php echo htmlspecialchars($appointment['clinic_address']); ?></p>
                                            <p><i class="fas fa-money-bill-wave"></i> <?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?> FCFA</p>
                                            <p><i class="fas fa-money-bill-wave"></i>Statut: <?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></p>
                                        </div>
                                    </div>
                                      <div class="appointment-actions">
                                        <p>Annuler</p>
                                    </div>
                                </div>

                               
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
           
                



               

            </div>
        </section>
    </main>

   

    <script src="script.js"></script>
</body>
</html>