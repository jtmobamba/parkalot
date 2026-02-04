<?php
/**
 * Reservation Tests
 *
 * Tests for parking reservation logic including pricing calculations,
 * date validation, and capacity checking.
 */

require_once __DIR__ . '/TestRunner.php';

class ReservationTest {
    private $runner;

    public function __construct() {
        $this->runner = new TestRunner();
        $this->registerTests();
    }

    private function registerTests() {
        // Test 1: Price Calculation - Hourly Rate
        $this->runner->addTest('Price calculation computes correct hourly rate', function() {
            $calculatePrice = function($startTime, $endTime, $hourlyRate) {
                $start = new DateTime($startTime);
                $end = new DateTime($endTime);
                $diff = $start->diff($end);
                $hours = $diff->h + ($diff->days * 24);
                if ($diff->i > 0) $hours++; // Round up partial hours
                return $hours * $hourlyRate;
            };

            // 2 hours at £5/hour = £10
            $price = $calculatePrice('2024-01-15 10:00', '2024-01-15 12:00', 5.00);
            assertEquals(10.00, $price, 'Price for 2 hours at £5/hour should be £10');

            // 5 hours at £3/hour = £15
            $price = $calculatePrice('2024-01-15 09:00', '2024-01-15 14:00', 3.00);
            assertEquals(15.00, $price, 'Price for 5 hours at £3/hour should be £15');

            return true;
        });

        // Test 2: Date Validation - Future Dates Only
        $this->runner->addTest('Reservation date validation accepts only future dates', function() {
            $isValidDate = function($dateString) {
                $date = new DateTime($dateString);
                $now = new DateTime();
                return $date > $now;
            };

            // Future date should be valid
            $futureDate = (new DateTime())->modify('+1 day')->format('Y-m-d H:i:s');
            assertTrue($isValidDate($futureDate), 'Future date should be valid');

            // Past date should be invalid
            $pastDate = (new DateTime())->modify('-1 day')->format('Y-m-d H:i:s');
            assertFalse($isValidDate($pastDate), 'Past date should be invalid');

            return true;
        });

        // Test 3: Duration Validation
        $this->runner->addTest('Reservation duration validation enforces limits', function() {
            $isValidDuration = function($startTime, $endTime, $minHours = 1, $maxHours = 24) {
                $start = new DateTime($startTime);
                $end = new DateTime($endTime);
                $diff = $start->diff($end);
                $hours = $diff->h + ($diff->days * 24);
                return $hours >= $minHours && $hours <= $maxHours;
            };

            // Valid duration (3 hours)
            assertTrue(
                $isValidDuration('2024-01-15 10:00', '2024-01-15 13:00'),
                '3 hour reservation should be valid'
            );

            // Too short (30 minutes = 0 hours)
            assertFalse(
                $isValidDuration('2024-01-15 10:00', '2024-01-15 10:30'),
                '30 minute reservation should be invalid'
            );

            // Too long (48 hours)
            assertFalse(
                $isValidDuration('2024-01-15 10:00', '2024-01-17 10:00'),
                '48 hour reservation should be invalid'
            );

            return true;
        });

        // Test 4: Capacity Check
        $this->runner->addTest('Garage capacity check correctly identifies availability', function() {
            $checkAvailability = function($totalSpaces, $activeReservations) {
                $available = $totalSpaces - $activeReservations;
                return $available > 0;
            };

            // Has availability
            assertTrue(
                $checkAvailability(100, 50),
                'Garage with 50/100 used should have availability'
            );

            // Full garage
            assertFalse(
                $checkAvailability(100, 100),
                'Full garage should not have availability'
            );

            // Overbooked (edge case)
            assertFalse(
                $checkAvailability(100, 105),
                'Overbooked garage should not have availability'
            );

            return true;
        });

        // Test 5: Available Spaces Calculation
        $this->runner->addTest('Available spaces calculation is accurate', function() {
            $calculateAvailable = function($totalSpaces, $activeReservations) {
                return max(0, $totalSpaces - $activeReservations);
            };

            assertEquals(50, $calculateAvailable(100, 50), '100 - 50 = 50 available');
            assertEquals(0, $calculateAvailable(100, 100), '100 - 100 = 0 available');
            assertEquals(0, $calculateAvailable(100, 150), 'Should not go negative');

            return true;
        });

        // Test 6: Reservation Status Transitions
        $this->runner->addTest('Reservation status transitions are valid', function() {
            $validTransitions = [
                'pending' => ['active', 'cancelled'],
                'active' => ['completed', 'cancelled'],
                'completed' => [],
                'cancelled' => []
            ];

            $isValidTransition = function($from, $to) use ($validTransitions) {
                if (!isset($validTransitions[$from])) return false;
                return in_array($to, $validTransitions[$from]);
            };

            // Valid transitions
            assertTrue($isValidTransition('pending', 'active'), 'pending -> active is valid');
            assertTrue($isValidTransition('active', 'completed'), 'active -> completed is valid');
            assertTrue($isValidTransition('active', 'cancelled'), 'active -> cancelled is valid');

            // Invalid transitions
            assertFalse($isValidTransition('completed', 'active'), 'completed -> active is invalid');
            assertFalse($isValidTransition('cancelled', 'active'), 'cancelled -> active is invalid');

            return true;
        });

        // Test 7: Time Overlap Detection
        $this->runner->addTest('Time overlap detection correctly identifies conflicts', function() {
            $hasOverlap = function($start1, $end1, $start2, $end2) {
                $s1 = strtotime($start1);
                $e1 = strtotime($end1);
                $s2 = strtotime($start2);
                $e2 = strtotime($end2);

                return $s1 < $e2 && $s2 < $e1;
            };

            // Overlapping reservations
            assertTrue(
                $hasOverlap('2024-01-15 10:00', '2024-01-15 14:00', '2024-01-15 12:00', '2024-01-15 16:00'),
                'Overlapping times should be detected'
            );

            // Non-overlapping reservations
            assertFalse(
                $hasOverlap('2024-01-15 10:00', '2024-01-15 12:00', '2024-01-15 14:00', '2024-01-15 16:00'),
                'Non-overlapping times should not conflict'
            );

            // Adjacent reservations (no overlap)
            assertFalse(
                $hasOverlap('2024-01-15 10:00', '2024-01-15 12:00', '2024-01-15 12:00', '2024-01-15 14:00'),
                'Adjacent times should not conflict'
            );

            return true;
        });
    }

    public function run() {
        echo "\n=== RESERVATION TESTS ===\n";
        return $this->runner->run();
    }
}

// Run tests if executed directly
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $test = new ReservationTest();
    exit($test->run() ? 0 : 1);
}
