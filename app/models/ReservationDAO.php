<?php
class ReservationDAO {
    public $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function countActive($garageId) {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM reservations
             WHERE garage_id=? AND status='active'"
        );
        $stmt->execute([$garageId]);
        return $stmt->fetchColumn();
    }

    public function create($userId, $garageId, $start, $end) {
        try {
            // Get the garage hourly rate
            $stmt = $this->db->prepare(
                "SELECT price_per_hour FROM garages WHERE garage_id = ?"
            );
            $stmt->execute([$garageId]);
            $pricePerHour = $stmt->fetchColumn();
            
            if (!$pricePerHour) {
                throw new Exception("Garage not found or price not available");
            }
            
            // Calculate duration in hours
            $startTime = new DateTime($start);
            $endTime = new DateTime($end);
            $interval = $startTime->diff($endTime);
            $durationHours = ($interval->days * 24) + $interval->h + ($interval->i / 60);
            
            // Calculate total price
            $totalPrice = $durationHours * $pricePerHour;
            
            // Insert reservation with price
            $stmt = $this->db->prepare(
                "INSERT INTO reservations (user_id, garage_id, start_time, end_time, price)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $result = $stmt->execute([$userId, $garageId, $start, $end, $totalPrice]);
            
            if ($result) {
                return [
                    'success' => true,
                    'reservation_id' => $this->db->lastInsertId(),
                    'duration_hours' => round($durationHours, 2),
                    'price_per_hour' => $pricePerHour,
                    'total_price' => round($totalPrice, 2)
                ];
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Reservation creation failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function getUserReservations($userId) {
        $stmt = $this->db->prepare(
            "SELECT r.*, g.garage_name, g.location 
             FROM reservations r
             JOIN garages g ON r.garage_id = g.garage_id
             WHERE r.user_id = ?
             ORDER BY r.created_at DESC"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getReservationById($reservationId) {
        $stmt = $this->db->prepare(
            "SELECT r.*, g.garage_name, g.location, g.price_per_hour
             FROM reservations r
             JOIN garages g ON r.garage_id = g.garage_id
             WHERE r.reservation_id = ?"
        );
        $stmt->execute([$reservationId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function cancelReservation($reservationId, $userId) {
        $stmt = $this->db->prepare(
            "UPDATE reservations 
             SET status = 'cancelled'
             WHERE reservation_id = ? AND user_id = ? AND status = 'active'"
        );
        return $stmt->execute([$reservationId, $userId]);
    }
}
?>