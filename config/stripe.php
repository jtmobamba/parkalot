<?php
/**
 * Stripe Configuration
 *
 * Handles Stripe API keys and configuration for payment processing.
 * Supports both regular payments and Connect for marketplace payouts.
 *
 * SECURITY NOTE: Never commit real API keys to version control.
 * Set these values in your .env file or server environment variables.
 */

// Prevent direct access
if (!defined('PARKALOT_INIT') && !isset($GLOBALS['stripe_init'])) {
    $GLOBALS['stripe_init'] = true;
}

// Include helper function if not already loaded
if (!function_exists('getApiKey')) {
    require_once __DIR__ . '/api_keys.php';
}

// =====================================================
// STRIPE API KEYS
// Get your keys from: https://dashboard.stripe.com/apikeys
// =====================================================

// Secret key (server-side only - NEVER expose to frontend)
define('STRIPE_SECRET_KEY', getApiKey('STRIPE_SECRET_KEY', 'sk_test_demo_key'));

// Publishable key (safe for frontend)
define('STRIPE_PUBLISHABLE_KEY', getApiKey('STRIPE_PUBLISHABLE_KEY', 'pk_test_demo_key'));

// Webhook signing secret for verifying Stripe events
define('STRIPE_WEBHOOK_SECRET', getApiKey('STRIPE_WEBHOOK_SECRET', 'whsec_demo_key'));

// =====================================================
// STRIPE CONNECT (For space owner payouts)
// =====================================================
define('STRIPE_CONNECT_CLIENT_ID', getApiKey('STRIPE_CONNECT_CLIENT_ID', ''));

// =====================================================
// CONFIGURATION
// =====================================================

// Currency (ISO 4217 code)
define('STRIPE_CURRENCY', 'gbp');

// Platform fee percentage for customer space bookings
define('STRIPE_PLATFORM_FEE_PERCENT', 15);

// Minimum payout amount (in currency units, e.g., 10.00 GBP)
define('STRIPE_MIN_PAYOUT_AMOUNT', 10.00);

// Payment intent expiration (in seconds) - 24 hours
define('STRIPE_PAYMENT_INTENT_EXPIRY', 86400);

// Enable test mode indicator
define('STRIPE_TEST_MODE', strpos(STRIPE_SECRET_KEY, 'sk_test') === 0 || STRIPE_SECRET_KEY === 'sk_test_demo_key');

// =====================================================
// PAYMENT SETTINGS
// =====================================================

// Allowed payment methods
define('STRIPE_PAYMENT_METHODS', json_encode([
    'card' // Credit/debit cards
    // 'apple_pay', // Uncomment when configured
    // 'google_pay', // Uncomment when configured
]));

// Auto-capture payments or authorize only
define('STRIPE_AUTO_CAPTURE', true);

// Statement descriptor (appears on customer's bank statement, max 22 chars)
define('STRIPE_STATEMENT_DESCRIPTOR', 'PARKALOT PARKING');

// Refund policy
define('STRIPE_REFUND_POLICY', json_encode([
    'full_refund_hours' => 24,      // Full refund if cancelled 24+ hours before
    'partial_refund_hours' => 6,    // 50% refund if cancelled 6-24 hours before
    'no_refund_hours' => 6          // No refund if cancelled less than 6 hours before
]));

// =====================================================
// HELPER FUNCTIONS
// =====================================================

/**
 * Check if Stripe is properly configured
 *
 * @return bool True if API keys are set
 */
function isStripeConfigured(): bool {
    return !empty(STRIPE_SECRET_KEY) &&
           STRIPE_SECRET_KEY !== 'sk_test_demo_key' &&
           !empty(STRIPE_PUBLISHABLE_KEY) &&
           STRIPE_PUBLISHABLE_KEY !== 'pk_test_demo_key';
}

/**
 * Check if Stripe Connect is configured
 *
 * @return bool True if Connect client ID is set
 */
function isStripeConnectConfigured(): bool {
    return !empty(STRIPE_CONNECT_CLIENT_ID);
}

/**
 * Get Stripe configuration for frontend
 *
 * @return array Public configuration safe for frontend
 */
function getStripePublicConfig(): array {
    return [
        'publishableKey' => STRIPE_PUBLISHABLE_KEY,
        'currency' => STRIPE_CURRENCY,
        'testMode' => STRIPE_TEST_MODE,
        'configured' => isStripeConfigured()
    ];
}

/**
 * Calculate platform fee for a booking
 *
 * @param float $amount Total booking amount
 * @return float Platform fee amount
 */
function calculatePlatformFee(float $amount): float {
    return round($amount * (STRIPE_PLATFORM_FEE_PERCENT / 100), 2);
}

/**
 * Calculate owner payout amount
 *
 * @param float $amount Total booking amount
 * @return float Amount to pay to space owner
 */
function calculateOwnerPayout(float $amount): float {
    return round($amount - calculatePlatformFee($amount), 2);
}

/**
 * Get refund policy configuration
 *
 * @return array Refund policy settings
 */
function getRefundPolicy(): array {
    return json_decode(STRIPE_REFUND_POLICY, true);
}

/**
 * Calculate refund amount based on cancellation timing
 *
 * @param float $amount Original payment amount
 * @param DateTime $bookingStart Booking start time
 * @param DateTime $cancellationTime When the cancellation was requested
 * @return float Refund amount
 */
function calculateRefundAmount(float $amount, DateTime $bookingStart, DateTime $cancellationTime): float {
    $policy = getRefundPolicy();
    $hoursUntilBooking = ($bookingStart->getTimestamp() - $cancellationTime->getTimestamp()) / 3600;

    if ($hoursUntilBooking >= $policy['full_refund_hours']) {
        return $amount; // Full refund
    } elseif ($hoursUntilBooking >= $policy['partial_refund_hours']) {
        return round($amount * 0.5, 2); // 50% refund
    } else {
        return 0; // No refund
    }
}

/**
 * Convert amount to Stripe format (smallest currency unit)
 *
 * @param float $amount Amount in major currency units
 * @return int Amount in smallest currency unit (pence for GBP)
 */
function toStripeAmount(float $amount): int {
    return (int) round($amount * 100);
}

/**
 * Convert Stripe amount to regular format
 *
 * @param int $stripeAmount Amount in smallest currency unit
 * @return float Amount in major currency units
 */
function fromStripeAmount(int $stripeAmount): float {
    return round($stripeAmount / 100, 2);
}
