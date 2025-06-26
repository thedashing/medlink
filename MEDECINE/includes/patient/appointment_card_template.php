<div class="appointment-card <?php echo htmlspecialchars(strtolower($appointment['status'])); ?>">
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
            <p><i class="fas fa-info-circle"></i> Statut: <span class="status-badge status-<?php echo htmlspecialchars(strtolower($appointment['status'])); ?>"><?php echo htmlspecialchars(ucfirst($appointment['status'])); ?></span></p>
            <?php if (strtolower($appointment['status']) === 'canceled' && !empty($appointment['cancel_reason'])): ?>
                <p class="cancel-reason"><i class="fas fa-comment-dots"></i> Raison: <?php echo htmlspecialchars($appointment['cancel_reason']); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="appointment-actions">
        <?php if (strtolower($appointment['status']) === 'upcoming'): ?>
            <?php
                $appointment_timestamp = strtotime($appointment['appointment_datetime']);
                $current_timestamp = time();
                $time_diff_hours = ($appointment_timestamp - $current_timestamp) / 3600;
                $can_cancel = ($time_diff_hours > 24);
            ?>
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
        <?php elseif (strtolower($appointment['status']) === 'completed'): ?>
            <p>Rendez-vous terminé.</p>
        <?php elseif (strtolower($appointment['status']) === 'canceled'): ?>
            <p>Ce rendez-vous a été annulé.</p>
        <?php endif; ?>
    </div>
</div>