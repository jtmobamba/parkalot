<?php
/**
 * ParkaLot System - Simple Test Runner
 *
 * A lightweight testing framework for demonstrating automated testing
 * without external dependencies like PHPUnit.
 *
 * Usage: php tests/TestRunner.php
 */

class TestRunner {
    private $passed = 0;
    private $failed = 0;
    private $tests = [];
    private $results = [];

    public function addTest($name, $callback) {
        $this->tests[$name] = $callback;
    }

    public function run() {
        echo "\n";
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║           ParkaLot System - Automated Test Suite             ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";

        $startTime = microtime(true);

        foreach ($this->tests as $name => $callback) {
            try {
                $result = $callback();
                if ($result === true) {
                    $this->passed++;
                    $this->results[] = ['name' => $name, 'status' => 'PASS', 'error' => null];
                    echo "  ✓ PASS: {$name}\n";
                } else {
                    $this->failed++;
                    $this->results[] = ['name' => $name, 'status' => 'FAIL', 'error' => 'Assertion failed'];
                    echo "  ✗ FAIL: {$name}\n";
                }
            } catch (Exception $e) {
                $this->failed++;
                $this->results[] = ['name' => $name, 'status' => 'FAIL', 'error' => $e->getMessage()];
                echo "  ✗ FAIL: {$name} - " . $e->getMessage() . "\n";
            }
        }

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        echo "\n";
        echo "──────────────────────────────────────────────────────────────────\n";
        echo "  Results: {$this->passed} passed, {$this->failed} failed\n";
        echo "  Duration: {$duration}ms\n";
        echo "──────────────────────────────────────────────────────────────────\n\n";

        return $this->failed === 0;
    }

    public function getResults() {
        return [
            'passed' => $this->passed,
            'failed' => $this->failed,
            'results' => $this->results
        ];
    }
}

// Assertion helper functions
function assertEquals($expected, $actual, $message = '') {
    if ($expected !== $actual) {
        throw new Exception($message ?: "Expected '{$expected}', got '{$actual}'");
    }
    return true;
}

function assertTrue($condition, $message = '') {
    if (!$condition) {
        throw new Exception($message ?: "Expected true, got false");
    }
    return true;
}

function assertFalse($condition, $message = '') {
    if ($condition) {
        throw new Exception($message ?: "Expected false, got true");
    }
    return true;
}

function assertNotNull($value, $message = '') {
    if ($value === null) {
        throw new Exception($message ?: "Expected non-null value");
    }
    return true;
}

function assertNull($value, $message = '') {
    if ($value !== null) {
        throw new Exception($message ?: "Expected null value");
    }
    return true;
}

function assertGreaterThan($expected, $actual, $message = '') {
    if ($actual <= $expected) {
        throw new Exception($message ?: "Expected value greater than {$expected}, got {$actual}");
    }
    return true;
}

function assertContains($needle, $haystack, $message = '') {
    if (strpos($haystack, $needle) === false) {
        throw new Exception($message ?: "Expected string to contain '{$needle}'");
    }
    return true;
}

function assertArrayHasKey($key, $array, $message = '') {
    if (!isset($array[$key])) {
        throw new Exception($message ?: "Expected array to have key '{$key}'");
    }
    return true;
}
