<?php
class ReservationController {
    private $reservationDAO;
    private $garageDAO;

    const OVERBOOKING_RATE = 1.1;

    public function __construct($db) {
        $this->reservationDAO = DAOFactory::reservationDAO($db);
        $this->garageDAO = DAOFactory::garageDAO($db);
    }

    public function create($data, $userId) {
        // Validate input
        if (empty($data->garage_id) || empty($data->start_time) || empty($data->end_time)) {
            return ["error" => "Missing required fields: garage_id, start_time, end_time"];
        }

        // Check capacity
        $capacity = $this->garageDAO->getCapacity($data->garage_id);
        if (!$capacity) {
            return ["error" => "Garage not found"];
        }

        $current = $this->reservationDAO->countActive($data->garage_id);

        if ($current >= floor($capacity * self::OVERBOOKING_RATE)) {
            return ["error" => "Garage is fully booked"];
        }

        // Validate dates
        $startTime = strtotime($data->start_time);
        $endTime = strtotime($data->end_time);
        
        if ($startTime === false || $endTime === false) {
            return ["error" => "Invalid date format"];
        }
        
        if ($startTime >= $endTime) {
            return ["error" => "End time must be after start time"];
        }
        
        if ($startTime < time()) {
            return ["error" => "Start time cannot be in the past"];
        }

        // Create reservation
        try {
            $result = $this->reservationDAO->create(
                $userId,
                $data->garage_id,
                $data->start_time,
                $data->end_time
            );

            if (!$result || !isset($result['success'])) {
                return ["error" => "Failed to create reservation"];
            }

            $reservationId = $result['reservation_id'];

            // Trigger notifications
            try {
                require_once __DIR__ . '/../services/NotificationService.php';
                $notificationService = new NotificationService($this->reservationDAO->db);
                $notificationService->triggerReservationNotifications($reservationId, 'created');
            } catch (Exception $e) {
                error_log("Notification failed: " . $e->getMessage());
            }

            return [
                "success" => true,
                "message" => "Reservation confirmed successfully!",
                "reservation_id" => $reservationId,
                "duration_hours" => $result['duration_hours'],
                "price_per_hour" => $result['price_per_hour'],
                "total_price" => $result['total_price']
            ];

        } catch (Exception $e) {
            error_log("Reservation error: " . $e->getMessage());
            return ["error" => "Unable to create reservation: " . $e->getMessage()];
        }
    }

    public function getUserReservations($userId) {
        try {
            $reservations = $this->reservationDAO->getUserReservations($userId);
            return [
                "success" => true,
                "reservations" => $reservations,
                "count" => count($reservations)
            ];
        } catch (Exception $e) {
            error_log("Get reservations error: " . $e->getMessage());
            return ["error" => "Unable to fetch reservations"];
        }
    }

    public function cancelReservation($reservationId, $userId) {
        try {
            $result = $this->reservationDAO->cancelReservation($reservationId, $userId);
            if ($result) {
                return ["success" => true, "message" => "Reservation cancelled"];
            }
            return ["error" => "Unable to cancel reservation"];
        } catch (Exception $e) {
            error_log("Cancel reservation error: " . $e->getMessage());
            return ["error" => "Unable to cancel reservation"];
        }
    }
}
?>