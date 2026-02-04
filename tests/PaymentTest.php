<?php
/**
 * Payment Tests
 *
 * Tests for payment processing logic including calculations,
 * validation, and transaction handling.
 */

require_once __DIR__ . '/TestRunner.php';

class PaymentTest {
    private $runner;

    public function __construct() {
        $this->runner = new TestRunner();
        $this->registerTests();
    }

    private function registerTests() {
        // Test 1: Invoice Total Calculation
        $this->runner->addTest('Invoice total calculation is accurate', function() {
            $calculateTotal = function($items) {
                return array_reduce($items, function($sum, $item) {
                    return $sum + (float)$item['price'];
                }, 0);
            };

            $items = [
                ['price' => 10.00],
                ['price' => 25.50],
                ['price' => 15.75]
            ];

            $total = $calculateTotal($items);
            assertEquals(51.25, $total, 'Total should be 51.25');

            // Empty items
            assertEquals(0, $calculateTotal([]), 'Empty items should total 0');

            return true;
        });

        // Test 2: Currency Formatting
        $this->runner->addTest('Currency formatting displays correctly', function() {
            $formatCurrency = function($amount, $currency = 'GBP') {
                $symbols = ['GBP' => '£', 'USD' => '$', 'EUR' => '€'];
                $symbol = $symbols[$currency] ?? $currency . ' ';
                return $symbol . number_format((float)$amount, 2);
            };

            assertEquals('£10.00', $formatCurrency(10), 'GBP formatting');
            assertEquals('£1,234.56', $formatCurrency(1234.56), 'Large GBP amount');
            assertEquals('$99.99', $formatCurrency(99.99, 'USD'), 'USD formatting');
            assertEquals('€50.00', $formatCurrency(50, 'EUR'), 'EUR formatting');

            return true;
        });

        // Test 3: Payment Amount Validation
        $this->runner->addTest('Payment amount validation rejects invalid amounts', function() {
            $isValidAmount = function($amount) {
                if (!is_numeric($amount)) return false;
                $amount = (float)$amount;
                if ($amount <= 0) return false;
                if ($amount > 10000) return false; // Max transaction limit
                return true;
            };

            assertTrue($isValidAmount(10.00), '£10 should be valid');
            assertTrue($isValidAmount(0.01), '£0.01 should be valid');
            assertTrue($isValidAmount(9999.99), '£9999.99 should be valid');

            assertFalse($isValidAmount(0), '£0 should be invalid');
            assertFalse($isValidAmount(-5), 'Negative amount should be invalid');
            assertFalse($isValidAmount(15000), 'Amount over limit should be invalid');
            assertFalse($isValidAmount('abc'), 'Non-numeric should be invalid');

            return true;
        });

        // Test 4: Transaction ID Generation
        $this->runner->addTest('Transaction ID generation produces unique valid IDs', function() {
            $generateTransactionId = function() {
                return 'TXN-' . time() . '-' . rand(1000, 9999);
            };

            $txn1 = $generateTransactionId();
            $txn2 = $generateTransactionId();

            // Should start with TXN-
            assertContains('TXN-', $txn1, 'Transaction ID should start with TXN-');

            // Should be unique
            assertTrue($txn1 !== $txn2, 'Transaction IDs should be unique');

            // Should have correct format
            assertTrue(
                preg_match('/^TXN-\d+-\d{4}$/', $txn1) === 1,
                'Transaction ID should match format TXN-timestamp-random'
            );

            return true;
        });

        // Test 5: Discount Calculation
        $this->runner->addTest('Discount calculation applies correctly', function() {
            $applyDiscount = function($amount, $discountPercent) {
                if ($discountPercent < 0 || $discountPercent > 100) {
                    return $amount;
                }
                $discount = $amount * ($discountPercent / 100);
                return round($amount - $discount, 2);
            };

            assertEquals(90.00, $applyDiscount(100, 10), '10% off £100 = £90');
            assertEquals(50.00, $applyDiscount(100, 50), '50% off £100 = £50');
            assertEquals(0.00, $applyDiscount(100, 100), '100% off £100 = £0');
            assertEquals(100.00, $applyDiscount(100, 0), '0% off £100 = £100');

            // Invalid discounts should not apply
            assertEquals(100.00, $applyDiscount(100, -10), 'Negative discount should not apply');
            assertEquals(100.00, $applyDiscount(100, 150), 'Over 100% discount should not apply');

            return true;
        });

        // Test 6: Payment Status Validation
        $this->runner->addTest('Payment status validation accepts only valid statuses', function() {
            $validStatuses = ['pending', 'processing', 'succeeded', 'failed', 'refunded'];

            $isValidStatus = function($status) use ($validStatuses) {
                return in_array($status, $validStatuses);
            };

            assertTrue($isValidStatus('pending'), 'pending is valid');
            assertTrue($isValidStatus('succeeded'), 'succeeded is valid');
            assertTrue($isValidStatus('failed'), 'failed is valid');
            assertTrue($isValidStatus('refunded'), 'refunded is valid');

            assertFalse($isValidStatus('complete'), 'complete is not valid');
            assertFalse($isValidStatus('PENDING'), 'uppercase PENDING is not valid');
            assertFalse($isValidStatus(''), 'empty string is not valid');

            return true;
        });

        // Test 7: Refund Eligibility Check
        $this->runner->addTest('Refund eligibility correctly checks conditions', function() {
            $isRefundEligible = function($paymentStatus, $reservationStatus, $hoursUntilStart) {
                // Can only refund successful payments
                if ($paymentStatus !== 'succeeded') return false;

                // Can only refund if reservation is active or pending
                if (!in_array($reservationStatus, ['active', 'pending'])) return false;

                // Must be at least 24 hours before start
                if ($hoursUntilStart < 24) return false;

                return true;
            };

            assertTrue(
                $isRefundEligible('succeeded', 'active', 48),
                'Should be refundable: succeeded payment, active reservation, 48 hours before'
            );

            assertFalse(
                $isRefundEligible('pending', 'active', 48),
                'Should not be refundable: pending payment'
            );

            assertFalse(
                $isRefundEligible('succeeded', 'completed', 48),
                'Should not be refundable: completed reservation'
            );

            assertFalse(
                $isRefundEligible('succeeded', 'active', 12),
                'Should not be refundable: less than 24 hours before'
            );

            return true;
        });

        // Test 8: VAT Calculation
        $this->runner->addTest('VAT calculation is accurate', function() {
            $calculateVAT = function($amount, $vatRate = 20) {
                $vat = $amount * ($vatRate / 100);
                return [
                    'net' => round($amount, 2),
                    'vat' => round($vat, 2),
                    'gross' => round($amount + $vat, 2)
                ];
            };

            $result = $calculateVAT(100);
            assertEquals(100.00, $result['net'], 'Net should be £100');
            assertEquals(20.00, $result['vat'], 'VAT should be £20 at 20%');
            assertEquals(120.00, $result['gross'], 'Gross should be £120');

            // Different VAT rate
            $result = $calculateVAT(50, 5);
            assertEquals(50.00, $result['net'], 'Net should be £50');
            assertEquals(2.50, $result['vat'], 'VAT should be £2.50 at 5%');
            assertEquals(52.50, $result['gross'], 'Gross should be £52.50');

            return true;
        });
    }

    public function run() {
        echo "\n=== PAYMENT TESTS ===\n";
        return $this->runner->run();
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new PaymentTest();
    exit($test->run() ? 0 : 1);
}
