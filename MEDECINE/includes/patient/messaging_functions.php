<?php
require_once '../../includes/Database.php';

class Messaging {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Envoie une notification à un patient concernant son rendez-vous
     */
    public function sendAppointmentNotification($appointment_id, $action) {
        $pdo = $this->db->getConnection();
        
        // Récupérer les infos du rendez-vous
        $stmt = $pdo->prepare("SELECT a.*, p.user_id AS patient_user_id, d.first_name AS doctor_first_name, 
                              d.last_name AS doctor_last_name, cl.name AS clinic_name
                              FROM appointments a
                              JOIN patients p ON a.patient_id = p.id
                              JOIN doctors d ON a.doctor_id = d.id
                              JOIN clinics cl ON a.clinic_id = cl.id
                              WHERE a.id = ?");
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$appointment) return false;
        
        // Déterminer le sujet et le contenu en fonction de l'action
        $subjects = [
            'confirmed' => 'Confirmation de votre rendez-vous',
            'cancelled' => 'Annulation de votre rendez-vous',
            'rescheduled' => 'Modification de votre rendez-vous'
        ];
        
        $contents = [
            'confirmed' => "Votre rendez-vous avec Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']} 
                          le " . date('d/m/Y à H:i', strtotime($appointment['appointment_datetime'])) . " 
                          à {$appointment['clinic_name']} a été confirmé.",
                          
            'cancelled' => "Votre rendez-vous avec Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']} 
                          prévu le " . date('d/m/Y à H:i', strtotime($appointment['appointment_datetime'])) . " 
                          à {$appointment['clinic_name']} a été annulé.",
                          
            'rescheduled' => "Votre rendez-vous avec Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']} 
                            a été modifié. Nouvelle date: " . date('d/m/Y à H:i', strtotime($appointment['appointment_datetime'])) . " 
                            à {$appointment['clinic_name']}."
        ];
        
        // Envoyer le message
        $stmt = $pdo->prepare("INSERT INTO messages 
                              (sender_id, recipient_id, subject, content, appointment_id, created_at)
                              VALUES (?, ?, ?, ?, ?, NOW())");
        
        // L'expéditeur est le système (user_id = 0) ou le médecin/clinique
        $sender_id = 0; // ou mettre l'ID du médecin/clinique si nécessaire
        
        return $stmt->execute([
            $sender_id,
            $appointment['patient_user_id'],
            $subjects[$action],
            $contents[$action],
            $appointment_id
        ]);
    }
    
    /**
     * Récupère les messages non lus pour un utilisateur
     */
    public function getUnreadMessagesCount($user_id) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM messages WHERE recipient_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    /**
     * Marque un message comme lu
     */
     public function markAsRead($message_id, $user_id) { // Nom de fonction corrigé
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1, read_at = NOW() 
                                WHERE id = ? AND recipient_id = ?");
        return $stmt->execute([$message_id, $user_id]);
    }
}
?>