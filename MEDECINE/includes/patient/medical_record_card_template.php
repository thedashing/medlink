<?php
// Ce fichier est un modèle pour une seule carte de dossier médical.
// Il s'attend à ce que la variable $record soit définie et contienne les données d'un dossier.
?>
<div class="record-card">
    <h3>Dossier du <?php echo date('d/m/Y', strtotime($record['record_date'])); ?></h3>
    <p><strong>Date de la consultation:</strong> <span class="detail-value"><?php echo date('d/m/Y à H:i', strtotime($record['appointment_datetime'])); ?></span></p>
    <p><strong>Médecin:</strong> <span class="detail-value">Dr. <?php echo htmlspecialchars($record['doctor_first_name'] . ' ' . $record['doctor_last_name']); ?></span></p>
    <p><strong>Clinique:</strong> <span class="detail-value"><?php echo htmlspecialchars($record['clinic_name']); ?>, <?php echo htmlspecialchars($record['clinic_address']); ?></span></p>
    <p><strong>Service:</strong> <span class="detail-value"><?php echo htmlspecialchars($record['service_name']); ?> (<?php echo htmlspecialchars(number_format($record['service_price'], 0, ',', ' ')); ?> FCFA)</span></p>
    <div class="diagnosis-notes">
        <p><strong>Diagnostic:</strong> <br><?php echo nl2br(htmlspecialchars($record['diagnosis'])); ?></p>
        <p><strong>Traitement:</strong> <br><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></p>
        <p><strong>Notes du médecin:</strong> <br><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
    </div>
</div>