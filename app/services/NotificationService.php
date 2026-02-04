<?php
/**
 * NotificationService - Handles multi-system notifications
 * Implements: EmailService, InventorySystem, AccountingSystem, CleaningStaffApp
 */
class NotificationService {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * EmailService: Send confirmation emails to users
     */
    public function sendEmailNotification($to, $subject, $message, $type = 'confirmation') {
        try {
            // In production, use PHPMailer or similar
            // For now, log the email
            $this->logNotification('email', $to, $subject, $message);
            
            // Simulate email sending
            $headers = "From: noreply@parkalot.com\r\n";
            $headers .= "Reply-To: support@parkalot.com\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            
            // In development, just log it
            error_log("EMAIL TO: {$to} | SUBJECT: {$subject} | MESSAGE: {$message}");
            
            return true;
        } catch (Exception $e) {
            error_log("Email notification failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send reservation confirmation email
     */
    public function sendReservationConfirmation($userId, $reservationId, $garageDetails) {
        try {
            // Get user email
            $stmt = $this->db->prepare("SELECT email, full_name FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) return false;
            
            $subject = "ParkaLot - Reservation Confirmation #{$reservationId}";
            $message = "
                <h2>Reservation Confirmed!</h2>
                <p>Dear {$user['full_name']},</p>
                <p>Your parking reservation has been confirmed.</p>
                <h3>Reservation Details:</h3>
                <ul>
                    <li><strong>Reservation ID:</strong> {$reservationId}</li>
                    <li><strong>Garage:</strong> {$garageDetails['name']}</li>
                    <li><strong>Location:</strong> {$garageDetails['location']}</li>
                    <li><strong>Start:</strong> {$garageDetails['start_time']}</li>
                    <li><strong>End:</strong> {$garageDetails['end_time']}</li>
                    <li><strong>Price:</strong> £{$garageDetails['price']}</li>
                </ul>
                <p>Thank you for choosing ParkaLot!</p>
            ";
            
            return $this->sendEmailNotification($user['email'], $subject, $message);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * InventorySystem: Manage garage space availability
     */
    public function updateInventorySystem($garageId, $action = 'reserve') {
        try {
            // Get current inventory status
            $stmt = $this->db->prepare("
                SELECT 
                    g.garage_name,
                    g.total_spaces,
                    COUNT(r.reservation_id) as active_reservations
                FROM garages g
                LEFT JOIN reservations r ON g.garage_id = r.garage_id 
                    AND r.status = 'active'
                WHERE g.garage_id = ?
                GROUP BY g.garage_id
            ");
            $stmt->execute([$garageId]);
            $inventory = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$inventory) return false;
            
            $availableSpaces = $inventory['total_spaces'] - $inventory['active_reservations'];
            $occupancyRate = ($inventory['active_reservations'] / $inventory['total_spaces']) * 100;
            
            // Log inventory change
            $this->logNotification(
                'inventory',
                $garageId,
                "Inventory Update - {$action}",
                json_encode([
                    'garage_name' => $inventory['garage_name'],
                    'action' => $action,
                    'total_spaces' => $inventory['total_spaces'],
                    'active_reservations' => $inventory['active_reservations'],
                    'available_spaces' => $availableSpaces,
                    'occupancy_rate' => round($occupancyRate, 2) . '%'
                ])
            );
            
            // Alert if near capacity
            if ($occupancyRate >= 90) {
                $this->alertManagement($garageId, 'High Occupancy Alert', 
                    "{$inventory['garage_name']} is at {$occupancyRate}% capacity");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Inventory system update failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * AccountingSystem: Process payments and refunds
     */
    public function processAccountingTransaction($reservationId, $amount, $type = 'payment') {
        try {
            // Get reservation details
            $stmt = $this->db->prepare("
                SELECT r.*, u.email, u.full_name 
                FROM reservations r
                JOIN users u ON r.user_id = u.user_id
                WHERE r.reservation_id = ?
            ");
            $stmt->execute([$reservationId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reservation) return false;
            
            if ($type === 'refund') {
                // Initiate refund processing
                $this->logNotification(
                    'accounting',
                    $reservation['user_id'],
                    "Refund Processing - Reservation #{$reservationId}",
                    json_encode([
                        'reservation_id' => $reservationId,
                        'amount' => $amount,
                        'type' => 'refund',
                        'status' => 'processing',
                        'user_email' => $reservation['email']
                    ])
                );
                
                // Send refund notification email
                $this->sendEmailNotification(
                    $reservation['email'],
                    "ParkaLot - Refund Processing",
                    "Your refund of £{$amount} for reservation #{$reservationId} is being processed."
                );
                
            } else {
                // Log payment
                $this->logNotification(
                    'accounting',
                    $reservation['user_id'],
                    "Payment Received - Reservation #{$reservationId}",
                    json_encode([
                        'reservation_id' => $reservationId,
                        'amount' => $amount,
                        'type' => 'payment',
                        'status' => 'completed'
                    ])
                );
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Accounting transaction failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * CleaningStaffApp: Notify cleaning staff about room preparation
     */
    public function notifyCleaningStaff($garageId, $spaceNumber, $action = 'prepare') {
        try {
            // Get garage details
            $stmt = $this->db->prepare("SELECT garage_name, location FROM garages WHERE garage_id = ?");
            $stmt->execute([$garageId]);
            $garage = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$garage) return false;
            
            // Get cleaning staff members
            $stmt = $this->db->prepare("
                SELECT u.user_id, u.email, u.full_name
                FROM users u
                JOIN employee_contracts ec ON u.user_id = ec.user_id
                WHERE ec.department = 'Cleaning' AND ec.status = 'active'
            ");
            $stmt->execute();
            $cleaningStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $message = match($action) {
                'prepare' => "Space #{$spaceNumber} at {$garage['garage_name']} needs preparation",
                'clean' => "Space #{$spaceNumber} at {$garage['garage_name']} requires cleaning",
                'inspect' => "Space #{$spaceNumber} at {$garage['garage_name']} needs inspection",
                default => "Task assigned for space #{$spaceNumber} at {$garage['garage_name']}"
            };
            
            // Notify each cleaning staff member
            foreach ($cleaningStaff as $staff) {
                $this->sendEmailNotification(
                    $staff['email'],
                    "ParkaLot - Cleaning Task Assignment",
                    "<p>Hi {$staff['full_name']},</p><p>{$message}</p><p>Location: {$garage['location']}</p>"
                );
            }
            
            // Log notification
            $this->logNotification(
                'cleaning_staff',
                $garageId,
                "Cleaning Staff Notification - {$action}",
                json_encode([
                    'garage_name' => $garage['garage_name'],
                    'space_number' => $spaceNumber,
                    'action' => $action,
                    'staff_notified' => count($cleaningStaff)
                ])
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Cleaning staff notification failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Alert management about critical issues
     */
    private function alertManagement($garageId, $alertType, $message) {
        try {
            // Get managers
            $stmt = $this->db->query("
                SELECT email, full_name FROM users WHERE role = 'manager'
            ");
            $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($managers as $manager) {
                $this->sendEmailNotification(
                    $manager['email'],
                    "ParkaLot - {$alertType}",
                    "<p>Hi {$manager['full_name']},</p><p>{$message}</p>"
                );
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Log all notifications for audit trail
     */
    private function logNotification($type, $recipient, $subject, $message) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, role, action, description)
                VALUES (?, ?, ?, ?)
            ");
            
            $description = json_encode([
                'type' => $type,
                'recipient' => $recipient,
                'subject' => $subject,
                'message' => $message,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $stmt->execute([0, 'system', "notification_{$type}", $description]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Trigger all notifications for a reservation event
     */
    public function triggerReservationNotifications($reservationId, $event = 'created') {
        try {
            // Get reservation details
            $stmt = $this->db->prepare("
                SELECT 
                    r.*,
                    g.garage_name,
                    g.location,
                    u.user_id,
                    u.email
                FROM reservations r
                JOIN garages g ON r.garage_id = g.garage_id
                JOIN users u ON r.user_id = u.user_id
                WHERE r.reservation_id = ?
            ");
            $stmt->execute([$reservationId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$data) return false;
            
            switch ($event) {
                case 'created':
                    // Email confirmation
                    $this->sendReservationConfirmation($data['user_id'], $reservationId, [
                        'name' => $data['garage_name'],
                        'location' => $data['location'],
                        'start_time' => $data['start_time'],
                        'end_time' => $data['end_time'],
                        'price' => $data['price']
                    ]);
                    
                    // Update inventory
                    $this->updateInventorySystem($data['garage_id'], 'reserve');
                    
                    // Process payment
                    $this->processAccountingTransaction($reservationId, $data['price'], 'payment');
                    
                    // Notify cleaning staff for preparation
                    $this->notifyCleaningStaff($data['garage_id'], rand(1, 50), 'prepare');
                    break;
                
                case 'cancelled':
                    // Free up inventory
                    $this->updateInventorySystem($data['garage_id'], 'cancel');
                    
                    // Process refund
                    $this->processAccountingTransaction($reservationId, $data['price'], 'refund');
                    
                    // Notify cleaning staff
                    $this->notifyCleaningStaff($data['garage_id'], rand(1, 50), 'clean');
                    break;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Notification trigger failed: " . $e->getMessage());
            return false;
        }
    }
}
?>
