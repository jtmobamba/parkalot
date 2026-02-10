<?php
/**
 * Stripe Payment Service
 *
 * Handles all Stripe payment operations including:
 * - Payment Intent creation and confirmation
 * - Refunds and partial refunds
 * - Webhook event processing
 * - Connect payouts to space owners
 */

require_once __DIR__ . '/BaseApiService.php';
require_once __DIR__ . '/../../config/stripe.php';

class StripeService extends BaseApiService
{
    protected string $apiSource = 'stripe';
    private bool $initialized = false;

    /**
     * Constructor
     *
     * @param PDO|null $db Database connection
     */
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db, STRIPE_SECRET_KEY, 30);
        $this->baseUrl = 'https://api.stripe.com/v1';
        $this->useMockData = !isStripeConfigured();
    }

    /**
     * Get HTTP headers for Stripe API
     *
     * @return array Headers
     */
    protected function getHeaders(): array
    {
        return [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/x-www-form-urlencoded',
            'Stripe-Version: 2023-10-16'
        ];
    }

    /**
     * Format response (not used for Stripe)
     */
    protected function formatResponse(array $data): array
    {
        return $data;
    }

    /**
     * Get fallback data for when Stripe is unavailable
     */
    protected function getFallbackData(): array
    {
        return [
            'success' => false,
            'error' => 'Payment service temporarily unavailable'
        ];
    }

    /**
     * Get public configuration for frontend
     *
     * @return array Public Stripe config
     */
    public function getPublicConfig(): array
    {
        return getStripePublicConfig();
    }

    /**
     * Create a Payment Intent
     *
     * @param float $amount Amount to charge
     * @param array $metadata Additional metadata
     * @param string|null $customerId Stripe customer ID
     * @param string|null $description Payment description
     * @return array Payment Intent data or error
     */
    public function createPaymentIntent(
        float $amount,
        array $metadata = [],
        ?string $customerId = null,
        ?string $description = null
    ): array {
        if ($this->useMockData) {
            return $this->createMockPaymentIntent($amount, $metadata);
        }

        $params = [
            'amount' => toStripeAmount($amount),
            'currency' => STRIPE_CURRENCY,
            'automatic_payment_methods[enabled]' => 'true',
            'metadata[source]' => 'parkalot',
        ];

        if ($customerId) {
            $params['customer'] = $customerId;
        }

        if ($description) {
            $params['description'] = $description;
        }

        // Add booking metadata
        foreach ($metadata as $key => $value) {
            $params["metadata[{$key}]"] = $value;
        }

        // Statement descriptor
        $params['statement_descriptor'] = substr(STRIPE_STATEMENT_DESCRIPTOR, 0, 22);

        $response = $this->makeStripeRequest('/payment_intents', 'POST', $params);

        if (!$response) {
            return $this->getFallbackData();
        }

        if (isset($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Payment creation failed'
            ];
        }

        return [
            'success' => true,
            'paymentIntentId' => $response['id'],
            'clientSecret' => $response['client_secret'],
            'amount' => fromStripeAmount($response['amount']),
            'currency' => $response['currency'],
            'status' => $response['status']
        ];
    }

    /**
     * Create Payment Intent for customer space booking (with platform fee)
     *
     * @param float $amount Total booking amount
     * @param string $ownerConnectId Space owner's Stripe Connect ID
     * @param array $metadata Booking metadata
     * @return array Payment Intent data
     */
    public function createSpaceBookingPayment(
        float $amount,
        string $ownerConnectId,
        array $metadata = []
    ): array {
        if ($this->useMockData) {
            return $this->createMockPaymentIntent($amount, $metadata);
        }

        $platformFee = calculatePlatformFee($amount);

        $params = [
            'amount' => toStripeAmount($amount),
            'currency' => STRIPE_CURRENCY,
            'automatic_payment_methods[enabled]' => 'true',
            'application_fee_amount' => toStripeAmount($platformFee),
            'transfer_data[destination]' => $ownerConnectId,
            'metadata[source]' => 'parkalot',
            'metadata[booking_type]' => 'customer_space',
            'metadata[platform_fee]' => $platformFee,
        ];

        foreach ($metadata as $key => $value) {
            $params["metadata[{$key}]"] = $value;
        }

        $response = $this->makeStripeRequest('/payment_intents', 'POST', $params);

        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Payment creation failed'
            ];
        }

        return [
            'success' => true,
            'paymentIntentId' => $response['id'],
            'clientSecret' => $response['client_secret'],
            'amount' => fromStripeAmount($response['amount']),
            'platformFee' => $platformFee,
            'ownerPayout' => calculateOwnerPayout($amount),
            'status' => $response['status']
        ];
    }

    /**
     * Retrieve a Payment Intent
     *
     * @param string $paymentIntentId Payment Intent ID
     * @return array|null Payment Intent data
     */
    public function getPaymentIntent(string $paymentIntentId): ?array
    {
        if ($this->useMockData) {
            return [
                'id' => $paymentIntentId,
                'status' => 'succeeded',
                'amount' => 1000,
                'currency' => 'gbp'
            ];
        }

        return $this->makeStripeRequest('/payment_intents/' . $paymentIntentId);
    }

    /**
     * Confirm a Payment Intent
     *
     * @param string $paymentIntentId Payment Intent ID
     * @param string $paymentMethodId Payment Method ID
     * @return array Confirmation result
     */
    public function confirmPaymentIntent(string $paymentIntentId, string $paymentMethodId): array
    {
        if ($this->useMockData) {
            return [
                'success' => true,
                'status' => 'succeeded',
                'paymentIntentId' => $paymentIntentId
            ];
        }

        $response = $this->makeStripeRequest(
            '/payment_intents/' . $paymentIntentId . '/confirm',
            'POST',
            ['payment_method' => $paymentMethodId]
        );

        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Payment confirmation failed'
            ];
        }

        return [
            'success' => true,
            'status' => $response['status'],
            'paymentIntentId' => $response['id']
        ];
    }

    /**
     * Create a refund
     *
     * @param string $paymentIntentId Payment Intent to refund
     * @param float|null $amount Amount to refund (null for full refund)
     * @param string $reason Refund reason
     * @return array Refund result
     */
    public function createRefund(
        string $paymentIntentId,
        ?float $amount = null,
        string $reason = 'requested_by_customer'
    ): array {
        if ($this->useMockData) {
            return [
                'success' => true,
                'refundId' => 're_mock_' . time(),
                'amount' => $amount ?? 10.00,
                'status' => 'succeeded'
            ];
        }

        $params = [
            'payment_intent' => $paymentIntentId,
            'reason' => $reason
        ];

        if ($amount !== null) {
            $params['amount'] = toStripeAmount($amount);
        }

        $response = $this->makeStripeRequest('/refunds', 'POST', $params);

        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Refund failed'
            ];
        }

        return [
            'success' => true,
            'refundId' => $response['id'],
            'amount' => fromStripeAmount($response['amount']),
            'status' => $response['status']
        ];
    }

    /**
     * Create or retrieve a Stripe Customer
     *
     * @param int $userId User ID
     * @param string $email User email
     * @param string $name User name
     * @return array Customer data
     */
    public function createOrGetCustomer(int $userId, string $email, string $name): array
    {
        if ($this->useMockData) {
            return [
                'success' => true,
                'customerId' => 'cus_mock_' . $userId
            ];
        }

        // Check if user already has a Stripe customer ID
        if ($this->db) {
            $stmt = $this->db->prepare("SELECT stripe_customer_id FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $existingId = $stmt->fetchColumn();

            if ($existingId) {
                return [
                    'success' => true,
                    'customerId' => $existingId
                ];
            }
        }

        // Create new customer
        $response = $this->makeStripeRequest('/customers', 'POST', [
            'email' => $email,
            'name' => $name,
            'metadata[user_id]' => $userId,
            'metadata[source]' => 'parkalot'
        ]);

        if (!$response || isset($response['error'])) {
            return [
                'success' => false,
                'error' => $response['error']['message'] ?? 'Failed to create customer'
            ];
        }

        // Save customer ID to database
        if ($this->db) {
            $stmt = $this->db->prepare("UPDATE users SET stripe_customer_id = ? WHERE user_id = ?");
            $stmt->execute([$response['id'], $userId]);
        }

        return [
            'success' => true,
            'customerId' => $response['id']
        ];
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload Raw request body
     * @param string $signature Stripe-Signature header
     * @return array|null Parsed event or null if invalid
     */
    public function verifyWebhook(string $payload, string $signature): ?array
    {
        if ($this->useMockData) {
            return json_decode($payload, true);
        }

        $signedPayload = $this->computeSignature($payload, $signature);

        if (!$signedPayload) {
            $this->logError('Webhook signature verification failed');
            return null;
        }

        return json_decode($payload, true);
    }

    /**
     * Compute and verify webhook signature
     *
     * @param string $payload Request payload
     * @param string $header Stripe-Signature header
     * @return bool Verification result
     */
    private function computeSignature(string $payload, string $header): bool
    {
        // Parse the header
        $parts = explode(',', $header);
        $timestamp = null;
        $signatures = [];

        foreach ($parts as $part) {
            [$key, $value] = explode('=', $part, 2);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (!$timestamp || empty($signatures)) {
            return false;
        }

        // Check timestamp tolerance (5 minutes)
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }

        // Compute expected signature
        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, STRIPE_WEBHOOK_SECRET);

        // Compare signatures
        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle webhook event
     *
     * @param array $event Stripe event data
     * @return array Processing result
     */
    public function handleWebhookEvent(array $event): array
    {
        $type = $event['type'] ?? '';
        $data = $event['data']['object'] ?? [];

        switch ($type) {
            case 'payment_intent.succeeded':
                return $this->handlePaymentSucceeded($data);

            case 'payment_intent.payment_failed':
                return $this->handlePaymentFailed($data);

            case 'charge.refunded':
                return $this->handleChargeRefunded($data);

            default:
                return ['success' => true, 'message' => 'Event type not handled'];
        }
    }

    /**
     * Handle successful payment
     */
    private function handlePaymentSucceeded(array $data): array
    {
        $paymentIntentId = $data['id'] ?? '';
        $metadata = $data['metadata'] ?? [];

        if (!$this->db) {
            return ['success' => true, 'message' => 'No database connection'];
        }

        // Update payment record
        $stmt = $this->db->prepare("
            UPDATE payments
            SET payment_status = 'succeeded', updated_at = NOW()
            WHERE stripe_payment_intent_id = ?
        ");
        $stmt->execute([$paymentIntentId]);

        // Update booking if metadata contains booking info
        $bookingType = $metadata['booking_type'] ?? '';
        $bookingId = $metadata['booking_id'] ?? '';

        if ($bookingType === 'customer_space' && $bookingId) {
            $stmt = $this->db->prepare("
                UPDATE customer_space_bookings
                SET payment_status = 'paid', booking_status = 'confirmed', updated_at = NOW()
                WHERE booking_id = ? AND stripe_payment_intent_id = ?
            ");
            $stmt->execute([$bookingId, $paymentIntentId]);
        } elseif ($bookingType === 'airport' && $bookingId) {
            $stmt = $this->db->prepare("
                UPDATE parking_bookings_live
                SET payment_status = 'paid', booking_status = 'confirmed', updated_at = NOW()
                WHERE booking_id = ? AND stripe_payment_intent_id = ?
            ");
            $stmt->execute([$bookingId, $paymentIntentId]);
        }

        return ['success' => true, 'message' => 'Payment processed'];
    }

    /**
     * Handle failed payment
     */
    private function handlePaymentFailed(array $data): array
    {
        $paymentIntentId = $data['id'] ?? '';
        $failureMessage = $data['last_payment_error']['message'] ?? 'Unknown error';

        if (!$this->db) {
            return ['success' => true, 'message' => 'No database connection'];
        }

        $stmt = $this->db->prepare("
            UPDATE payments
            SET payment_status = 'failed', updated_at = NOW()
            WHERE stripe_payment_intent_id = ?
        ");
        $stmt->execute([$paymentIntentId]);

        return ['success' => true, 'message' => 'Payment failure recorded'];
    }

    /**
     * Handle refund
     */
    private function handleChargeRefunded(array $data): array
    {
        $paymentIntentId = $data['payment_intent'] ?? '';
        $refunded = $data['amount_refunded'] ?? 0;

        if (!$this->db || !$paymentIntentId) {
            return ['success' => true, 'message' => 'No database connection or payment intent'];
        }

        $stmt = $this->db->prepare("
            UPDATE payments
            SET payment_status = 'refunded', updated_at = NOW()
            WHERE stripe_payment_intent_id = ?
        ");
        $stmt->execute([$paymentIntentId]);

        return ['success' => true, 'message' => 'Refund processed'];
    }

    /**
     * Make a Stripe API request
     */
    private function makeStripeRequest(string $endpoint, string $method = 'GET', array $params = []): ?array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $this->getHeaders(),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'ParkaLot-System/1.0'
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($params);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logError("Stripe cURL error: {$error}");
            return null;
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            $this->logError("Stripe API error {$httpCode}: " . ($data['error']['message'] ?? 'Unknown'));
        }

        return $data;
    }

    /**
     * Create mock payment intent for development
     */
    private function createMockPaymentIntent(float $amount, array $metadata): array
    {
        $mockId = 'pi_mock_' . bin2hex(random_bytes(12));

        return [
            'success' => true,
            'paymentIntentId' => $mockId,
            'clientSecret' => $mockId . '_secret_mock',
            'amount' => $amount,
            'currency' => STRIPE_CURRENCY,
            'status' => 'requires_payment_method',
            'testMode' => true,
            'message' => 'Using mock payment - configure Stripe API keys for real payments'
        ];
    }
}
