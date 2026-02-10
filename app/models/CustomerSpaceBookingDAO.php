<?php
/**
 * Customer Space Booking DAO
 *
 * Handles database operations for bookings on customer-listed parking spaces.
 */

class CustomerSpaceBookingDAO
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create a new booking
     *
     * @param array $data Booking data
     * @return array Result with booking_id or error
     */
    public function create(array $data): array
    {
        try {
            // Get space details for pricing and owner
            $stmt = $this->db->prepare("
                SELECT space_id, owner_id, price_per_hour, price_per_day, status
                FROM customer_spaces WHERE space_id = ?
            ");
            $stmt->execute([$data['space_id']]);
            $space = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$space) {
                return ['success' => false, 'error' => 'Space not found'];
            }

            if ($space['status'] !== 'active') {
                return ['success' => false, 'error' => 'Space is not available for booking'];
            }

            // Calculate duration and price
            $start = new DateTime($data['start_time']);
            $end = new DateTime($data['end_time']);
            $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

            // Use daily rate if applicable (booking > 8 hours and daily rate exists)
            $totalPrice = $data['total_price'] ?? $this->calculatePrice(
                $hours,
                (float)$space['price_per_hour'],
                (float)($space['price_per_day'] ?? 0)
            );

            // Calculate platform fee (15%)
            $platformFee = round($totalPrice * 0.15, 2);
            $ownerPayout = round($totalPrice - $platformFee, 2);

            $stmt = $this->db->prepare("
                INSERT INTO customer_space_bookings (
                    space_id, renter_id, owner_id, start_time, end_time,
                    vehicle_reg, vehicle_make, vehicle_model, vehicle_color,
                    total_price, platform_fee, owner_payout,
                    booking_status, payment_status, stripe_payment_intent_id, renter_notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?)
            ");

            $result = $stmt->execute([
                $data['space_id'],
                $data['renter_id'],
                $space['owner_id'],
                $data['start_time'],
                $data['end_time'],
                $data['vehicle_reg'] ?? null,
                $data['vehicle_make'] ?? null,
                $data['vehicle_model'] ?? null,
                $data['vehicle_color'] ?? null,
                $totalPrice,
                $platformFee,
                $ownerPayout,
                $data['stripe_payment_intent_id'] ?? null,
                $data['renter_notes'] ?? null
            ]);

            if ($result) {
                $bookingId = $this->db->lastInsertId();

                return [
                    'success' => true,
                    'booking_id' => $bookingId,
                    'total_price' => $totalPrice,
                    'platform_fee' => $platformFee,
                    'owner_payout' => $ownerPayout,
                    'hours' => round($hours, 2),
                    'message' => 'Booking created successfully'
                ];
            }

            return ['success' => false, 'error' => 'Failed to create booking'];
        } catch (PDOException $e) {
            error_log("CustomerSpaceBookingDAO::create error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error creating booking'];
        }
    }

    /**
     * Calculate booking price
     */
    private function calculatePrice(float $hours, float $hourlyRate, float $dailyRate): float
    {
        if ($dailyRate > 0 && $hours >= 8) {
            $days = ceil($hours / 24);
            $remainingHours = $hours % 24;

            if ($remainingHours > 8 || $dailyRate < ($hourlyRate * 8)) {
                // Use daily rate
                return round($days * $dailyRate, 2);
            }
        }

        return round($hours * $hourlyRate, 2);
    }

    /**
     * Get booking by ID
     *
     * @param int $bookingId Booking ID
     * @return array|null Booking data
     */
    public function getById(int $bookingId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT csb.*,
                   cs.space_name, cs.address_line1, cs.city, cs.postcode,
                   cs.latitude, cs.longitude, cs.instructions,
                   renter.name as renter_name, renter.email as renter_email,
                   owner.name as owner_name, owner.email as owner_email
            FROM customer_space_bookings csb
            JOIN customer_spaces cs ON csb.space_id = cs.space_id
            JOIN users renter ON csb.renter_id = renter.user_id
            JOIN users owner ON csb.owner_id = owner.user_id
            WHERE csb.booking_id = ?
        ");
        $stmt->execute([$bookingId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get bookings for a renter
     *
     * @param int $renterId Renter user ID
     * @param string $status Optional status filter
     * @return array Bookings
     */
    public function getByRenter(int $renterId, ?string $status = null): array
    {
        $where = 'csb.renter_id = ?';
        $params = [$renterId];

        if ($status) {
            $where .= ' AND csb.booking_status = ?';
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT csb.*,
                   cs.space_name, cs.address_line1, cs.city, cs.postcode,
                   cs.photos, owner.name as owner_name
            FROM customer_space_bookings csb
            JOIN customer_spaces cs ON csb.space_id = cs.space_id
            JOIN users owner ON csb.owner_id = owner.user_id
            WHERE {$where}
            ORDER BY csb.start_time DESC
        ");
        $stmt->execute($params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bookings as &$booking) {
            $booking['photos'] = json_decode($booking['photos'] ?? '[]', true);
        }

        return $bookings;
    }

    /**
     * Get bookings for a space owner
     *
     * @param int $ownerId Owner user ID
     * @param string $status Optional status filter
     * @return array Bookings
     */
    public function getByOwner(int $ownerId, ?string $status = null): array
    {
        $where = 'csb.owner_id = ?';
        $params = [$ownerId];

        if ($status) {
            $where .= ' AND csb.booking_status = ?';
            $params[] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT csb.*,
                   cs.space_name, cs.address_line1,
                   renter.name as renter_name, renter.email as renter_email
            FROM customer_space_bookings csb
            JOIN customer_spaces cs ON csb.space_id = cs.space_id
            JOIN users renter ON csb.renter_id = renter.user_id
            WHERE {$where}
            ORDER BY csb.start_time DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get bookings for a specific space
     *
     * @param int $spaceId Space ID
     * @param string|null $fromDate Optional start date filter
     * @return array Bookings
     */
    public function getBySpace(int $spaceId, ?string $fromDate = null): array
    {
        $where = 'space_id = ? AND booking_status NOT IN (?, ?)';
        $params = [$spaceId, 'cancelled', 'completed'];

        if ($fromDate) {
            $where .= ' AND end_time >= ?';
            $params[] = $fromDate;
        }

        $stmt = $this->db->prepare("
            SELECT booking_id, start_time, end_time, booking_status
            FROM customer_space_bookings
            WHERE {$where}
            ORDER BY start_time ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update booking status
     *
     * @param int $bookingId Booking ID
     * @param string $status New status
     * @param int|null $userId User making the change (for verification)
     * @return array Result
     */
    public function updateStatus(int $bookingId, string $status, ?int $userId = null): array
    {
        try {
            $validStatuses = ['pending', 'confirmed', 'active', 'completed', 'cancelled', 'disputed'];

            if (!in_array($status, $validStatuses)) {
                return ['success' => false, 'error' => 'Invalid status'];
            }

            // Get current booking
            $booking = $this->getById($bookingId);
            if (!$booking) {
                return ['success' => false, 'error' => 'Booking not found'];
            }

            // Verify user has access
            if ($userId && $userId != $booking['renter_id'] && $userId != $booking['owner_id']) {
                return ['success' => false, 'error' => 'Access denied'];
            }

            $updates = ['booking_status = ?'];
            $params = [$status];

            // Set additional fields based on status
            if ($status === 'active') {
                $updates[] = 'check_in_time = NOW()';
            } elseif ($status === 'completed') {
                $updates[] = 'check_out_time = NOW()';

                // Update space stats
                $this->updateSpaceStats($booking['space_id'], $booking['owner_payout']);
            } elseif ($status === 'cancelled') {
                $updates[] = 'cancelled_at = NOW()';
                if ($userId) {
                    $cancelledBy = ($userId == $booking['renter_id']) ? 'renter' : 'owner';
                    $updates[] = 'cancelled_by = ?';
                    $params[] = $cancelledBy;
                }
            }

            $params[] = $bookingId;

            $stmt = $this->db->prepare("
                UPDATE customer_space_bookings
                SET " . implode(', ', $updates) . "
                WHERE booking_id = ?
            ");
            $stmt->execute($params);

            return ['success' => true, 'message' => 'Booking status updated'];
        } catch (PDOException $e) {
            error_log("CustomerSpaceBookingDAO::updateStatus error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error updating booking'];
        }
    }

    /**
     * Update payment status
     *
     * @param int $bookingId Booking ID
     * @param string $status Payment status
     * @param string|null $paymentIntentId Stripe payment intent ID
     * @return bool Success
     */
    public function updatePaymentStatus(int $bookingId, string $status, ?string $paymentIntentId = null): bool
    {
        $updates = ['payment_status = ?'];
        $params = [$status];

        if ($paymentIntentId) {
            $updates[] = 'stripe_payment_intent_id = ?';
            $params[] = $paymentIntentId;
        }

        // Auto-confirm booking when paid
        if ($status === 'paid') {
            $updates[] = "booking_status = 'confirmed'";
        }

        $params[] = $bookingId;

        $stmt = $this->db->prepare("
            UPDATE customer_space_bookings
            SET " . implode(', ', $updates) . "
            WHERE booking_id = ?
        ");
        return $stmt->execute($params);
    }

    /**
     * Update space statistics after completed booking
     */
    private function updateSpaceStats(int $spaceId, float $earnings): void
    {
        $stmt = $this->db->prepare("
            UPDATE customer_spaces
            SET total_earnings = total_earnings + ?,
                total_bookings = total_bookings + 1
            WHERE space_id = ?
        ");
        $stmt->execute([$earnings, $spaceId]);
    }

    /**
     * Cancel a booking
     *
     * @param int $bookingId Booking ID
     * @param int $userId User requesting cancellation
     * @param string|null $reason Cancellation reason
     * @return array Result with refund info
     */
    public function cancel(int $bookingId, int $userId, ?string $reason = null): array
    {
        try {
            $booking = $this->getById($bookingId);

            if (!$booking) {
                return ['success' => false, 'error' => 'Booking not found'];
            }

            if ($userId != $booking['renter_id'] && $userId != $booking['owner_id']) {
                return ['success' => false, 'error' => 'Access denied'];
            }

            if (in_array($booking['booking_status'], ['cancelled', 'completed'])) {
                return ['success' => false, 'error' => 'Booking cannot be cancelled'];
            }

            // Calculate refund based on timing
            $startTime = new DateTime($booking['start_time']);
            $now = new DateTime();
            $hoursUntilStart = ($startTime->getTimestamp() - $now->getTimestamp()) / 3600;

            $refundAmount = 0;
            if ($booking['payment_status'] === 'paid') {
                if ($hoursUntilStart >= 24) {
                    $refundAmount = $booking['total_price']; // Full refund
                } elseif ($hoursUntilStart >= 6) {
                    $refundAmount = round($booking['total_price'] * 0.5, 2); // 50% refund
                }
                // Less than 6 hours = no refund
            }

            // Update booking
            $cancelledBy = ($userId == $booking['renter_id']) ? 'renter' : 'owner';

            $stmt = $this->db->prepare("
                UPDATE customer_space_bookings
                SET booking_status = 'cancelled',
                    cancelled_by = ?,
                    cancelled_at = NOW(),
                    cancellation_reason = ?
                WHERE booking_id = ?
            ");
            $stmt->execute([$cancelledBy, $reason, $bookingId]);

            return [
                'success' => true,
                'message' => 'Booking cancelled',
                'refund_amount' => $refundAmount,
                'refund_eligible' => $refundAmount > 0
            ];
        } catch (PDOException $e) {
            error_log("CustomerSpaceBookingDAO::cancel error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error cancelling booking'];
        }
    }

    /**
     * Get upcoming bookings count for dashboard
     *
     * @param int $userId User ID
     * @param string $role 'renter' or 'owner'
     * @return int Count
     */
    public function getUpcomingCount(int $userId, string $role = 'renter'): int
    {
        $column = ($role === 'owner') ? 'owner_id' : 'renter_id';

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM customer_space_bookings
            WHERE {$column} = ?
                  AND booking_status IN ('pending', 'confirmed')
                  AND start_time > NOW()
        ");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Calculate price for a potential booking
     *
     * @param int $spaceId Space ID
     * @param string $startTime Start datetime
     * @param string $endTime End datetime
     * @return array Price breakdown
     */
    public function calculateBookingPrice(int $spaceId, string $startTime, string $endTime): array
    {
        $stmt = $this->db->prepare("
            SELECT price_per_hour, price_per_day FROM customer_spaces WHERE space_id = ?
        ");
        $stmt->execute([$spaceId]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$space) {
            return ['error' => 'Space not found'];
        }

        $start = new DateTime($startTime);
        $end = new DateTime($endTime);
        $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;

        if ($hours <= 0) {
            return ['error' => 'Invalid time range'];
        }

        $totalPrice = $this->calculatePrice(
            $hours,
            (float)$space['price_per_hour'],
            (float)($space['price_per_day'] ?? 0)
        );

        return [
            'hours' => round($hours, 2),
            'hourly_rate' => (float)$space['price_per_hour'],
            'daily_rate' => (float)($space['price_per_day'] ?? 0),
            'subtotal' => $totalPrice,
            'service_fee' => round($totalPrice * 0.15, 2),
            'total' => round($totalPrice * 1.15, 2)
        ];
    }
}
