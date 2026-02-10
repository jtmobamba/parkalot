<?php
/**
 * Payment DAO
 *
 * Handles database operations for payment records.
 * Supports multiple booking types and payment methods.
 */

class PaymentDAO
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create a payment record
     *
     * @param array $data Payment data
     * @return array Result with payment_id
     */
    public function create(array $data): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payments (
                    user_id, booking_type, booking_id, amount, currency,
                    payment_method, stripe_payment_intent_id, stripe_customer_id,
                    status, metadata
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([
                $data['user_id'],
                $data['booking_type'],
                $data['booking_id'],
                $data['amount'],
                $data['currency'] ?? 'GBP',
                $data['payment_method'] ?? 'stripe',
                $data['stripe_payment_intent_id'] ?? null,
                $data['stripe_customer_id'] ?? null,
                $data['status'] ?? 'pending',
                isset($data['metadata']) ? json_encode($data['metadata']) : null
            ]);

            if ($result) {
                return [
                    'success' => true,
                    'payment_id' => $this->db->lastInsertId()
                ];
            }

            return ['success' => false, 'error' => 'Failed to create payment record'];
        } catch (PDOException $e) {
            error_log("PaymentDAO::create error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error creating payment'];
        }
    }

    /**
     * Get payment by ID
     *
     * @param int $paymentId Payment ID
     * @return array|null Payment data
     */
    public function getById(int $paymentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, u.name as user_name, u.email as user_email
            FROM payments p
            JOIN users u ON p.user_id = u.user_id
            WHERE p.payment_id = ?
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($payment && $payment['metadata']) {
            $payment['metadata'] = json_decode($payment['metadata'], true);
        }

        return $payment ?: null;
    }

    /**
     * Get payment by Stripe Payment Intent ID
     *
     * @param string $paymentIntentId Stripe Payment Intent ID
     * @return array|null Payment data
     */
    public function getByPaymentIntentId(string $paymentIntentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM payments WHERE stripe_payment_intent_id = ?
        ");
        $stmt->execute([$paymentIntentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get payments for a user
     *
     * @param int $userId User ID
     * @param int $limit Number of records
     * @param int $offset Offset for pagination
     * @return array Payments
     */
    public function getByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM payments
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payments for a booking
     *
     * @param string $bookingType Booking type
     * @param int $bookingId Booking ID
     * @return array Payments
     */
    public function getByBooking(string $bookingType, int $bookingId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM payments
            WHERE booking_type = ? AND booking_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$bookingType, $bookingId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update payment status
     *
     * @param int $paymentId Payment ID
     * @param string $status New status
     * @param array $additionalData Optional additional fields
     * @return bool Success
     */
    public function updateStatus(int $paymentId, string $status, array $additionalData = []): bool
    {
        $updates = ['status = ?'];
        $params = [$status];

        if (isset($additionalData['stripe_charge_id'])) {
            $updates[] = 'stripe_charge_id = ?';
            $params[] = $additionalData['stripe_charge_id'];
        }

        if (isset($additionalData['failure_reason'])) {
            $updates[] = 'failure_reason = ?';
            $params[] = $additionalData['failure_reason'];
        }

        $params[] = $paymentId;

        $stmt = $this->db->prepare("
            UPDATE payments
            SET " . implode(', ', $updates) . ", updated_at = NOW()
            WHERE payment_id = ?
        ");
        return $stmt->execute($params);
    }

    /**
     * Update payment by Stripe Payment Intent ID
     *
     * @param string $paymentIntentId Stripe Payment Intent ID
     * @param string $status New status
     * @param array $additionalData Optional additional fields
     * @return bool Success
     */
    public function updateByPaymentIntentId(string $paymentIntentId, string $status, array $additionalData = []): bool
    {
        $updates = ['status = ?'];
        $params = [$status];

        if (isset($additionalData['stripe_charge_id'])) {
            $updates[] = 'stripe_charge_id = ?';
            $params[] = $additionalData['stripe_charge_id'];
        }

        if (isset($additionalData['failure_reason'])) {
            $updates[] = 'failure_reason = ?';
            $params[] = $additionalData['failure_reason'];
        }

        $params[] = $paymentIntentId;

        $stmt = $this->db->prepare("
            UPDATE payments
            SET " . implode(', ', $updates) . ", updated_at = NOW()
            WHERE stripe_payment_intent_id = ?
        ");
        return $stmt->execute($params);
    }

    /**
     * Record a refund
     *
     * @param int $paymentId Payment ID
     * @param float $amount Refund amount
     * @return bool Success
     */
    public function recordRefund(int $paymentId, float $amount): bool
    {
        $stmt = $this->db->prepare("
            UPDATE payments
            SET status = 'refunded',
                refund_amount = ?,
                refunded_at = NOW(),
                updated_at = NOW()
            WHERE payment_id = ?
        ");
        return $stmt->execute([$amount, $paymentId]);
    }

    /**
     * Get payment statistics for a user
     *
     * @param int $userId User ID
     * @return array Statistics
     */
    public function getUserStats(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_payments,
                COALESCE(SUM(CASE WHEN status = 'succeeded' THEN amount ELSE 0 END), 0) as total_spent,
                COALESCE(SUM(CASE WHEN status = 'refunded' THEN refund_amount ELSE 0 END), 0) as total_refunded,
                COUNT(CASE WHEN status = 'succeeded' THEN 1 END) as successful_payments,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments
            FROM payments
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get recent payments for admin dashboard
     *
     * @param int $limit Number of records
     * @return array Recent payments
     */
    public function getRecent(int $limit = 10): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, u.name as user_name
            FROM payments p
            JOIN users u ON p.user_id = u.user_id
            ORDER BY p.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment totals by period
     *
     * @param string $period 'day', 'week', 'month', 'year'
     * @return array Totals
     */
    public function getTotalsByPeriod(string $period = 'month'): array
    {
        $dateFormat = '%Y-%m-%d';
        $groupBy = 'DATE(created_at)';

        switch ($period) {
            case 'week':
                $dateFormat = '%Y-%u'; // Year-Week
                $groupBy = 'YEARWEEK(created_at)';
                break;
            case 'month':
                $dateFormat = '%Y-%m';
                $groupBy = 'DATE_FORMAT(created_at, "%Y-%m")';
                break;
            case 'year':
                $dateFormat = '%Y';
                $groupBy = 'YEAR(created_at)';
                break;
        }

        $stmt = $this->db->prepare("
            SELECT
                DATE_FORMAT(created_at, '{$dateFormat}') as period,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'succeeded' THEN amount ELSE 0 END) as total,
                SUM(CASE WHEN status = 'refunded' THEN refund_amount ELSE 0 END) as refunded
            FROM payments
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 {$period})
            GROUP BY {$groupBy}
            ORDER BY period DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
