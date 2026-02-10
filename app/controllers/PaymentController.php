<?php
/**
 * Payment Controller
 *
 * Handles payment-related API endpoints:
 * - Payment intent creation
 * - Payment confirmation
 * - Webhook processing
 * - Refunds
 */

require_once __DIR__ . '/../services/StripeService.php';

class PaymentController
{
    private $stripeService;
    private $paymentDAO;
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->stripeService = new StripeService($db);
        $this->paymentDAO = DAOFactory::paymentDAO($db);
    }

    /**
     * Get public Stripe configuration
     *
     * @return array Public config for frontend
     */
    public function getConfig(): array
    {
        return [
            'success' => true,
            'config' => $this->stripeService->getPublicConfig()
        ];
    }

    /**
     * Create a payment intent
     *
     * @param object $data Request data
     * @param int $userId User ID
     * @return array Response with client secret
     */
    public function createIntent($data, int $userId): array
    {
        // Validate required fields
        if (empty($data->amount) || !is_numeric($data->amount) || $data->amount <= 0) {
            return ['error' => 'Valid amount required', 'code' => 'validation'];
        }

        if (empty($data->booking_type)) {
            return ['error' => 'Booking type required', 'code' => 'validation'];
        }

        $validTypes = ['garage', 'customer_space', 'airport'];
        if (!in_array($data->booking_type, $validTypes)) {
            return ['error' => 'Invalid booking type', 'code' => 'validation'];
        }

        // Build metadata
        $metadata = [
            'user_id' => $userId,
            'booking_type' => $data->booking_type
        ];

        if (!empty($data->booking_id)) {
            $metadata['booking_id'] = $data->booking_id;
        }

        if (!empty($data->space_id)) {
            $metadata['space_id'] = $data->space_id;
        }

        // Get or create Stripe customer
        $userDAO = DAOFactory::userDAO($this->db);
        $user = $userDAO->getById($userId);

        $customerResult = $this->stripeService->createOrGetCustomer(
            $userId,
            $user['email'],
            $user['name']
        );

        $customerId = $customerResult['success'] ? $customerResult['customerId'] : null;

        // Create payment intent
        $description = $this->getPaymentDescription($data->booking_type, $metadata);

        $result = $this->stripeService->createPaymentIntent(
            (float)$data->amount,
            $metadata,
            $customerId,
            $description
        );

        if (!$result['success']) {
            return ['error' => $result['error'] ?? 'Failed to create payment', 'code' => 'payment_error'];
        }

        // Create payment record in database
        $paymentRecord = $this->paymentDAO->create([
            'user_id' => $userId,
            'booking_type' => $data->booking_type,
            'booking_id' => $data->booking_id ?? 0,
            'amount' => $data->amount,
            'stripe_payment_intent_id' => $result['paymentIntentId'],
            'stripe_customer_id' => $customerId,
            'status' => 'pending',
            'metadata' => $metadata
        ]);

        return [
            'success' => true,
            'clientSecret' => $result['clientSecret'],
            'paymentIntentId' => $result['paymentIntentId'],
            'amount' => $result['amount'],
            'testMode' => $result['testMode'] ?? false
        ];
    }

    /**
     * Create payment intent for customer space booking
     *
     * @param object $data Request data
     * @param int $userId User ID
     * @return array Response
     */
    public function createSpacePayment($data, int $userId): array
    {
        if (empty($data->space_id) || empty($data->amount)) {
            return ['error' => 'Space ID and amount required', 'code' => 'validation'];
        }

        // Get space and owner details
        $spaceDAO = DAOFactory::customerSpaceDAO($this->db);
        $space = $spaceDAO->getById($data->space_id);

        if (!$space) {
            return ['error' => 'Space not found', 'code' => 'not_found'];
        }

        // Get owner's Stripe Connect ID
        $stmt = $this->db->prepare("SELECT stripe_connect_id FROM users WHERE user_id = ?");
        $stmt->execute([$space['owner_id']]);
        $ownerConnectId = $stmt->fetchColumn();

        // Build metadata
        $metadata = [
            'user_id' => $userId,
            'booking_type' => 'customer_space',
            'space_id' => $data->space_id,
            'owner_id' => $space['owner_id']
        ];

        if (!empty($data->booking_id)) {
            $metadata['booking_id'] = $data->booking_id;
        }

        // Create payment intent (with platform fee if owner has Connect account)
        if ($ownerConnectId) {
            $result = $this->stripeService->createSpaceBookingPayment(
                (float)$data->amount,
                $ownerConnectId,
                $metadata
            );
        } else {
            // Owner doesn't have Connect - regular payment
            $result = $this->stripeService->createPaymentIntent(
                (float)$data->amount,
                $metadata,
                null,
                "ParkaLot - {$space['space_name']}"
            );
        }

        if (!$result['success']) {
            return ['error' => $result['error'] ?? 'Failed to create payment'];
        }

        // Create payment record
        $this->paymentDAO->create([
            'user_id' => $userId,
            'booking_type' => 'customer_space',
            'booking_id' => $data->booking_id ?? 0,
            'amount' => $data->amount,
            'stripe_payment_intent_id' => $result['paymentIntentId'],
            'status' => 'pending',
            'metadata' => $metadata
        ]);

        return [
            'success' => true,
            'clientSecret' => $result['clientSecret'],
            'paymentIntentId' => $result['paymentIntentId'],
            'amount' => $result['amount'],
            'platformFee' => $result['platformFee'] ?? null,
            'ownerPayout' => $result['ownerPayout'] ?? null
        ];
    }

    /**
     * Confirm payment was successful (called after Stripe confirms on frontend)
     *
     * @param object $data Request data
     * @param int $userId User ID
     * @return array Response
     */
    public function confirmPayment($data, int $userId): array
    {
        if (empty($data->payment_intent_id)) {
            return ['error' => 'Payment intent ID required', 'code' => 'validation'];
        }

        // Verify payment intent status with Stripe
        $paymentIntent = $this->stripeService->getPaymentIntent($data->payment_intent_id);

        if (!$paymentIntent) {
            return ['error' => 'Payment not found', 'code' => 'not_found'];
        }

        $status = $paymentIntent['status'] ?? '';

        if ($status === 'succeeded') {
            // Update payment record
            $this->paymentDAO->updateByPaymentIntentId($data->payment_intent_id, 'succeeded');

            // Update booking based on type
            $this->updateBookingPaymentStatus($data->payment_intent_id, 'paid');

            return [
                'success' => true,
                'message' => 'Payment confirmed',
                'status' => 'succeeded'
            ];
        }

        return [
            'success' => false,
            'status' => $status,
            'message' => 'Payment not yet completed'
        ];
    }

    /**
     * Process Stripe webhook
     *
     * @param string $payload Raw request body
     * @param string $signature Stripe-Signature header
     * @return array Response
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        // Verify webhook signature
        $event = $this->stripeService->verifyWebhook($payload, $signature);

        if (!$event) {
            return ['error' => 'Invalid webhook signature', 'code' => 'invalid_signature'];
        }

        // Process the event
        $result = $this->stripeService->handleWebhookEvent($event);

        // Log webhook event
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (role, action, description, ip_address)
                VALUES ('system', 'stripe_webhook', ?, ?)
            ");
            $stmt->execute([
                json_encode(['type' => $event['type'], 'id' => $event['id']]),
                $_SERVER['REMOTE_ADDR'] ?? 'CLI'
            ]);
        } catch (Exception $e) {
            error_log("Failed to log webhook: " . $e->getMessage());
        }

        return [
            'success' => true,
            'received' => true,
            'type' => $event['type']
        ];
    }

    /**
     * Request a refund
     *
     * @param object $data Request data
     * @param int $userId User ID
     * @return array Response
     */
    public function refund($data, int $userId): array
    {
        if (empty($data->payment_id) && empty($data->payment_intent_id)) {
            return ['error' => 'Payment ID or Payment Intent ID required', 'code' => 'validation'];
        }

        // Get payment record
        $payment = null;
        if (!empty($data->payment_id)) {
            $payment = $this->paymentDAO->getById($data->payment_id);
        } else {
            $payment = $this->paymentDAO->getByPaymentIntentId($data->payment_intent_id);
        }

        if (!$payment) {
            return ['error' => 'Payment not found', 'code' => 'not_found'];
        }

        // Verify user owns this payment
        if ($payment['user_id'] != $userId) {
            return ['error' => 'Access denied', 'code' => 'forbidden'];
        }

        // Check if already refunded
        if ($payment['status'] === 'refunded') {
            return ['error' => 'Payment already refunded', 'code' => 'already_refunded'];
        }

        // Process refund
        $amount = !empty($data->amount) ? (float)$data->amount : null;
        $result = $this->stripeService->createRefund(
            $payment['stripe_payment_intent_id'],
            $amount,
            $data->reason ?? 'requested_by_customer'
        );

        if (!$result['success']) {
            return ['error' => $result['error'] ?? 'Refund failed'];
        }

        // Update payment record
        $this->paymentDAO->recordRefund($payment['payment_id'], $result['amount']);

        // Update booking status
        $this->updateBookingPaymentStatus(
            $payment['stripe_payment_intent_id'],
            $amount && $amount < $payment['amount'] ? 'partial_refund' : 'refunded'
        );

        return [
            'success' => true,
            'message' => 'Refund processed',
            'refund_id' => $result['refundId'],
            'refund_amount' => $result['amount']
        ];
    }

    /**
     * Get payment history for a user
     *
     * @param int $userId User ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Response
     */
    public function getHistory(int $userId, int $limit = 20, int $offset = 0): array
    {
        $payments = $this->paymentDAO->getByUser($userId, $limit, $offset);
        $stats = $this->paymentDAO->getUserStats($userId);

        return [
            'success' => true,
            'payments' => $payments,
            'stats' => $stats,
            'count' => count($payments)
        ];
    }

    /**
     * Update booking payment status based on payment intent
     */
    private function updateBookingPaymentStatus(string $paymentIntentId, string $status): void
    {
        // Get payment record to find booking
        $payment = $this->paymentDAO->getByPaymentIntentId($paymentIntentId);

        if (!$payment) {
            return;
        }

        $bookingType = $payment['booking_type'];
        $bookingId = $payment['booking_id'];

        if (!$bookingId) {
            return;
        }

        try {
            switch ($bookingType) {
                case 'customer_space':
                    $bookingDAO = DAOFactory::customerSpaceBookingDAO($this->db);
                    $bookingDAO->updatePaymentStatus($bookingId, $status, $paymentIntentId);
                    break;

                case 'airport':
                    $stmt = $this->db->prepare("
                        UPDATE parking_bookings_live
                        SET payment_status = ?, stripe_payment_intent_id = ?
                        WHERE booking_id = ?
                    ");
                    $stmt->execute([$status, $paymentIntentId, $bookingId]);
                    break;

                case 'garage':
                    // Update reservations table if it has payment status
                    $stmt = $this->db->prepare("
                        UPDATE reservations
                        SET status = CASE WHEN ? = 'paid' THEN 'active' ELSE status END
                        WHERE reservation_id = ?
                    ");
                    $stmt->execute([$status, $bookingId]);
                    break;
            }
        } catch (Exception $e) {
            error_log("Failed to update booking payment status: " . $e->getMessage());
        }
    }

    /**
     * Generate payment description
     */
    private function getPaymentDescription(string $bookingType, array $metadata): string
    {
        switch ($bookingType) {
            case 'customer_space':
                return 'ParkaLot - Private Parking Space Booking';
            case 'airport':
                return 'ParkaLot - Airport Parking Booking';
            case 'garage':
                return 'ParkaLot - Garage Parking Booking';
            default:
                return 'ParkaLot - Parking Booking';
        }
    }
}
