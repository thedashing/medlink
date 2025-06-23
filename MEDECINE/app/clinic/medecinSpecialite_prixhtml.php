





<?php
include_once "../../includes/clinic/medecin_specialite_funct.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer Spécialités et Services des Médecins</title>
    <link rel="stylesheet" href="../../public/css/clinic/medecin_specialite.css">

</head>
<body>
    <div class="header">
        <h1>Gérer Spécialités et Services des Médecins</h1>
        <a href="dashboard_clinic.php">Retour au Tableau de Bord</a>
    </div>

    <div class="content">
        <?php if (isset($success_message)): ?>
            <div class="message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
            <div class="message error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <div class="tab-buttons">
            <button class="tab-button active" onclick="showTab('listDoctors')">Liste des Médecins et Services</button>
            <button class="tab-button" onclick="showTab('addSpecialty')">Gérer les Spécialités des Médecins (pour la clinique)</button> <button class="tab-button" onclick="showTab('addService')">Ajouter un Service à un Médecin</button>
        </div>

        <div id="listDoctors" class="tab-content">
            <h2 class="section-title">Aperçu des Médecins, Spécialités et Services</h2>
            <?php if (empty($doctors)): ?>
                <p>Aucun médecin n'est enregistré pour cette clinique.</p>
            <?php else: ?>
                <?php foreach ($doctors as $doctor): ?>
                    <div class="doctor-card">
                        <h3><?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?></h3>

                        <h4>Spécialités de ce médecin (pour cette clinique) :</h4> <?php if (isset($doctor_assigned_specialties_in_clinic[$doctor['id']]) && !empty($doctor_assigned_specialties_in_clinic[$doctor['id']])): ?>
                            <ul>
                                <?php foreach ($doctor_assigned_specialties_in_clinic[$doctor['id']] as $specialty): ?>
                                    <li><?php echo htmlspecialchars($specialty['name']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>Aucune spécialité assignée à ce médecin pour cette clinique.</p> <?php endif; ?>

                        <h4>Services offerts par ce médecin :</h4>
                        <?php if (isset($doctor_offered_services[$doctor['id']]) && !empty($doctor_offered_services[$doctor['id']])): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Nom du Service</th>
                                        <th>Description</th>
                                        <th>Prix</th>
                                        <th>Durée (min)</th>
                                        </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($doctor_offered_services[$doctor['id']] as $service): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                            <td><?php echo htmlspecialchars($service['description'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(number_format($service['price'], 2)) . ' €'; ?></td>
                                            <td><?php echo htmlspecialchars($service['duration_minutes']); ?></td>
                                            </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>Aucun service spécifique n'est offert par ce médecin.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="addSpecialty" class="tab-content" style="display:none;">
            <h2 class="section-title">Gérer les Spécialités des Médecins (pour cette clinique)</h2> <form method="POST">
                <input type="hidden" name="action" value="manage_doctor_clinic_specialties"> <div class="form-group">
                    <label for="doctor_id_specialty">Sélectionner un Médecin :</label>
                    <select id="doctor_id_specialty" name="doctor_id_specialty" required onchange="loadDoctorClinicSpecialties(this.value)"> <option value="">-- Choisir un médecin --</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo htmlspecialchars($doctor['id']); ?>">
                                <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="specialty_ids">Assigner des Spécialités (maintenez Ctrl/Cmd pour sélectionner plusieurs) :</label>
                    <select id="specialty_ids" name="specialty_ids[]" multiple required>
                        <?php foreach ($available_specialties as $specialty): ?>
                            <option value="<?php echo htmlspecialchars($specialty['id']); ?>">
                                <?php echo htmlspecialchars($specialty['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit">Mettre à jour les Spécialités (pour cette clinique)</button> </div>
            </form>
        </div>

        <div id="addService" class="tab-content" style="display:none;">
            <h2 class="section-title">Ajouter un Service Spécifique à un Médecin</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_doctor_service">
                <div class="form-group">
                    <label for="doctor_id_service">Sélectionner un Médecin :</label>
                    <select id="doctor_id_service" name="doctor_id_service" required>
                        <option value="">-- Choisir un médecin --</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo htmlspecialchars($doctor['id']); ?>">
                                <?php echo htmlspecialchars($doctor['first_name'] . ' ' . $doctor['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="service_name">Nom du Service :</label>
                    <input type="text" id="service_name" name="service_name" required placeholder="Ex: Consultation initiale, Bilan sanguin">
                </div>

                <div class="form-group">
                    <label for="service_description">Description du Service (optionnel) :</label>
                    <textarea id="service_description" name="service_description" rows="3" placeholder="Décrivez brièvement le service..."></textarea>
                </div>

                <div class="form-group">
                    <label for="price">Prix du Service (EUR) :</label>
                    <input type="number" id="price" name="price" step="0.01" min="0" required placeholder="Ex: 50.00">
                </div>

                <div class="form-group">
                    <label for="duration_minutes">Durée du Service (minutes) :</label>
                    <input type="number" id="duration_minutes" name="duration_minutes" min="5" required placeholder="Ex: 30">
                </div>

                <div class="form-group">
                    <button type="submit">Ajouter le Service au Médecin</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.style.display = 'none';
            });
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('active');
            });
            document.querySelector(`.tab-button[onclick="showTab('${tabId}')"]`).classList.add('active');
            document.getElementById(tabId).style.display = 'block';

            // If switching to the "Manage Specialties" tab, load the specialties for the currently selected doctor (if any)
            if (tabId === 'addSpecialty') {
                const selectedDoctorId = document.getElementById('doctor_id_specialty').value;
                if (selectedDoctorId) {
                    loadDoctorClinicSpecialties(selectedDoctorId); // Appelle la nouvelle fonction
                }
            }
        }

        // JavaScript pour pré-sélectionner les spécialités du médecin DANS CETTE CLINIQUE
        function loadDoctorClinicSpecialties(doctorId) { // Nom de fonction changé
            const specialtySelect = document.getElementById('specialty_ids');
            // Effacer les sélections précédentes
            for (let i = 0; i < specialtySelect.options.length; i++) {
                specialtySelect.options[i].selected = false;
            }

            // Utiliser les données pré-chargées depuis PHP (attention au nom de variable)
            const doctorAssignedSpecialtiesData = <?php echo json_encode($doctor_assigned_specialties_in_clinic); ?>; // Nom de variable PHP changé
            if (doctorAssignedSpecialtiesData[doctorId]) {
                const assignedIds = doctorAssignedSpecialtiesData[doctorId].map(s => String(s.id));
                for (let i = 0; i < specialtySelect.options.length; i++) {
                    if (assignedIds.includes(specialtySelect.options[i].value)) {
                        specialtySelect.options[i].selected = true;
                    }
                }
            }
        }

        // Afficher le premier onglet par défaut au chargement de la page
        document.addEventListener('DOMContentLoaded', () => {
            showTab('listDoctors');
        });
    </script>
</body>
</html>
















