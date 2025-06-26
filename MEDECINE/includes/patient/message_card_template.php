<?php
// Fichier : ../../includes/patient/message_card_template.php
// Ce fichier est un modèle pour une seule carte de message.
// Il s'attend à ce que la variable $message soit définie et contienne les données d'un message.
?>
<div class="message-card <?php echo !$message['is_read'] ? 'unread' : ''; ?>"
     data-message-id="<?php echo htmlspecialchars($message['id']); ?>">
    <div class="message-header">
        <span class="message-subject">
            <?php echo htmlspecialchars($message['subject']); ?>
            <?php if (!$message['is_read']): ?>
                <span class="badge">Nouveau</span>
            <?php endif; ?>
        </span>
        <span class="message-date">
            <?php echo date('d/m/Y H:i', strtotime($message['created_at'])); ?>
        </span>
    </div>

    <?php if ($message['appointment_id']): ?>
        <p class="message-info">
            <em>Concerne un rendez-vous avec
                <strong>Dr. <?php echo htmlspecialchars($message['doctor_name'] ?? 'Inconnu'); ?></strong>
                <?php if (!empty($message['clinic_name'])): ?>
                    à <strong><?php echo htmlspecialchars($message['clinic_name']); ?></strong>
                <?php endif; ?>
            </em>
        </p>
    <?php endif; ?>

    <div class="message-content">
        <?php echo nl2br(htmlspecialchars($message['content'])); ?>
    </div>
</div>