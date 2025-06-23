<?php
include_once "../../../includes/clinic/manage_doctor_schedule_funct.php";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gérer les Horaires de <?php echo $doctor_info ? htmlspecialchars($doctor_info['first_name'] . ' ' . $doctor_info['last_name']) : 'Médecin'; ?></title>
       <link rel="stylesheet" href="../../../public/css/clinic/manage_doctor_schedule.css">

</head>
<body>
    <div class="container">
        <?php if ($doctor_info && $clinic_info): ?>
            <h1>Gérer les Horaires de Dr. <?php echo htmlspecialchars($doctor_info['first_name'] . ' ' . $doctor_info['last_name']); ?></h1>
            <p style="text-align: center;">Pour la Clinique : <?php echo htmlspecialchars($clinic_info['name']); ?></p>

            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <h2>Ajouter un nouveau créneau</h2>
            <form action="" method="POST">
                <input type="hidden" name="action" value="add_schedule">
                <div class="form-group">
                    <label for="day_of_week">Jour de la semaine:</label>
                    <select id="day_of_week" name="day_of_week" required>
                        <?php foreach ($days_of_week as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="start_time">Heure de début:</label>
                    <input type="time" id="start_time" name="start_time" required>
                </div>
                <div class="form-group">
                    <label for="end_time">Heure de fin:</label>
                    <input type="time" id="end_time" name="end_time" required>
                </div>
                <button type="submit">Ajouter le créneau</button>
            </form>

            <hr>

            <h2>Créneaux horaires existants</h2>
            <?php if (empty($doctor_schedules)): ?>
                <p style="text-align: center;">Aucun créneau horaire n'est défini pour ce médecin dans cette clinique.</p>
            <?php else: ?>
                <div class="schedule-list">
                    <?php foreach ($doctor_schedules as $schedule): ?>
                        <div class="schedule-item">
                            <span>
                                <?php echo htmlspecialchars($days_of_week[$schedule['day_of_week']]); ?> :
                                <?php echo htmlspecialchars(substr($schedule['start_time'], 0, 5)); ?> -
                                <?php echo htmlspecialchars(substr($schedule['end_time'], 0, 5)); ?>
                            </span>
                            <form action="" method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce créneau ?');">
                                <input type="hidden" name="action" value="delete_schedule">
                                <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                                <button type="submit" class="delete-button">Supprimer</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="message error">
                <p>Impossible de charger les informations du médecin ou de la clinique. Assurez-vous d'avoir les autorisations nécessaires et que les IDs sont corrects.</p>
            </div>
        <?php endif; ?>

        <a href="../dashboard_clinic.php" class="nav-link-back">Retour à la liste des médecins</a>
    </div>
</body>
</html>