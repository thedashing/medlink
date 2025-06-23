<?php
// Fichier : includes/Messaging.php

// Assurez-vous que Database.php est inclus ici ou qu'il l'est dans un fichier parent.
require_once '../../includes/Database.php';

class Messaging {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Envoie une notification à un patient concernant son rendez-vous.
     * Le message est envoyé par l'utilisateur spécifié par $sender_user_id.
     *
     * @param int $appointment_id L'ID du rendez-vous concerné.
     * @param string $action Le type d'action ('confirmed', 'cancelled', 'rescheduled', 'completed').
     * @param int $sender_user_id L'ID de l'utilisateur (médecin ou système) qui envoie la notification.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function sendAppointmentNotification($appointment_id, $action, $sender_user_id) {
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
        
        if (!$appointment) {
            error_log("Tentative d'envoi de notification pour un rendez-vous inexistant (ID: {$appointment_id}).");
            return false;
        }
        
        // Déterminer le sujet et le contenu en fonction de l'action
        $subjects = [
            'confirmed' => 'Confirmation de votre rendez-vous',
            'cancelled' => 'Annulation de votre rendez-vous',
            'rescheduled' => 'Modification de votre rendez-vous',
            'completed' => 'Votre rendez-vous est terminé' // NOUVEAU SUJET pour l'action 'completed'
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
                                à {$appointment['clinic_name']}.",
            'completed' => "Votre rendez-vous avec Dr. {$appointment['doctor_first_name']} {$appointment['doctor_last_name']} 
                            prévu le " . date('d/m/Y à H:i', strtotime($appointment['appointment_datetime'])) . " 
                            à {$appointment['clinic_name']} est maintenant terminé. Un dossier médical a été enregistré." // NOUVEAU CONTENU
        ];

        // Vérifiez si l'action est reconnue
        if (!isset($subjects[$action]) || !isset($contents[$action])) {
            error_log("Action de notification inconnue: {$action}");
            return false;
        }
        
        // L'expéditeur est l'ID de l'utilisateur passé en paramètre (médecin ou système)
        $sender_id = $sender_user_id;
        
        return $this->sendMessage(
            $sender_id,
            $appointment['patient_user_id'], // C'est le user_id du patient qui est le destinataire du message
            $subjects[$action],
            $contents[$action],
            $appointment_id
        );
    }
    
    /**
     * Récupère le nombre de messages non lus pour un utilisateur donné.
     * @param int $user_id L'ID de l'utilisateur dont on veut compter les messages non lus.
     * @return int Le nombre de messages non lus.
     */
    public function getUnreadMessagesCount($user_id) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM messages WHERE recipient_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    /**
     * Marque un message spécifique comme lu.
     * @param int $message_id L'ID du message à marquer.
     * @param int $user_id L'ID de l'utilisateur qui a lu le message (pour s'assurer qu'il est le destinataire).
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function markAsRead($message_id, $user_id) {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1, read_at = NOW() 
                                WHERE id = ? AND recipient_id = ? AND is_read = 0");
        return $stmt->execute([$message_id, $user_id]);
    }

    /**
     * Envoie un message générique d'un utilisateur à un autre.
     * @param int $sender_user_id L'ID de l'expéditeur (user_id).
     * @param int $recipient_user_id L'ID du destinataire (user_id).
     * @param string $subject Le sujet du message.
     * @param string $content Le contenu du message.
     * @param int|null $appointment_id Optionnel: Lien vers un rendez-vous si applicable.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function sendMessage($sender_user_id, $recipient_user_id, $subject, $content, $appointment_id = null) {
        $pdo = $this->db->getConnection();
        try {
            $stmt = $pdo->prepare("INSERT INTO messages
                                   (sender_id, recipient_id, subject, content, appointment_id, created_at)
                                   VALUES (?, ?, ?, ?, ?, NOW())");
            return $stmt->execute([
                $sender_user_id,
                $recipient_user_id,
                $subject,
                $content,
                $appointment_id
            ]);
        } catch (PDOException $e) {
            error_log("Erreur lors de l'envoi du message: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Marque tous les messages non lus reçus par un utilisateur comme lus.
     * Utile lorsque l'utilisateur consulte sa boîte de réception.
     * @param int $user_id L'ID de l'utilisateur dont les messages doivent être marqués comme lus.
     * @return bool Vrai en cas de succès, faux en cas d'échec.
     */
    public function markAllAsRead($user_id) {
        $pdo = $this->db->getConnection();
        try {
            $stmt = $pdo->prepare("UPDATE messages SET is_read = 1, read_at = NOW()
                                   WHERE recipient_id = ? AND is_read = 0");
            return $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Erreur lors du marquage des messages comme lus: " . $e->getMessage());
            return false;
        }
    }
}