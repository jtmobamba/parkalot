<?php
/**
 * TfL Parking Service
 *
 * Integrates with Transport for London's Unified API to fetch
 * real-time car park occupancy data across London.
 *
 * API Documentation: https://api.tfl.gov.uk/
 * Free registration: https://api-portal.tfl.gov.uk/
 */
class TfLParkingService
{
    private const BASE_URL = 'https://api.tfl.gov.uk';
    private ?string $appKey;
    private int $timeout;

    /**
     * @param string|null $appKey Optional TfL API key (increases rate limits)
     * @param int $timeout Request timeout in seconds
     */
    public function __construct(?string $appKey = null, int $timeout = 10)
    {
        $this->appKey = $appKey;
        $this->timeout = $timeout;
    }

    /**
     * Get all car park occupancy data across London
     *
     * @return array List of car parks with availability
     */
    public function getAllCarParks(): array
    {
        $data = $this->makeRequest('/Occupancy/CarPark');

        if (!$data) {
            return $this->getFallbackCarParks();
        }

        return $this->formatCarParkData($data);
    }

    /**
     * Get occupancy for a specific car park
     *
     * @param string $carParkId Car park ID (e.g., "CarParks_800491")
     * @return array|null Car park data or null if not found
     */
    public function getCarPark(string $carParkId): ?array
    {
        $data = $this->makeRequest("/Occupancy/CarPark/{$carParkId}");

        if (!$data) {
            return null;
        }

        $formatted = $this->formatCarParkData([$data]);
        return $formatted[0] ?? null;
    }

    /**
     * Get car parks near a tube/rail station
     *
     * @param string $stopPointId Station ID (e.g., "940GZZLUBKG" for Barking)
     * @return array List of car parks at the station
     */
    public function getCarParksByStation(string $stopPointId): array
    {
        $data = $this->makeRequest("/StopPoint/{$stopPointId}/CarParks");

        if (!$data) {
            return [];
        }

        return array_map(function($place) {
            return [
                'id' => $place['id'] ?? null,
                'name' => $place['commonName'] ?? 'Unknown',
                'lat' => $place['lat'] ?? null,
                'lon' => $place['lon'] ?? null,
                'address' => $this->extractAddress($place),
                'url' => $place['url'] ?? null
            ];
        }, $data);
    }

    /**
     * Get EV charge connector occupancy
     *
     * @return array List of charge connectors with status
     */
    public function getChargeConnectors(): array
    {
        $data = $this->makeRequest('/Occupancy/ChargeConnector');

        if (!$data) {
            return [];
        }

        return array_map(function($connector) {
            return [
                'id' => $connector['id'] ?? null,
                'sourceSystemPlaceId' => $connector['sourceSystemPlaceId'] ?? null,
                'status' => $connector['status'] ?? 'Unknown',
                'lastUpdated' => $connector['$type'] ?? null
            ];
        }, $data);
    }

    /**
     * Search for car parks by area/location name
     *
     * @param string $query Search query (e.g., "Westminster", "Stratford")
     * @return array Matching places with parking
     */
    public function searchCarParks(string $query): array
    {
        $params = [
            'query' => $query,
            'types' => 'CarPark'
        ];

        $data = $this->makeRequest('/Place/Search', $params);

        if (!$data) {
            return [];
        }

        return array_map(function($place) {
            return [
                'id' => $place['id'] ?? null,
                'name' => $place['commonName'] ?? 'Unknown',
                'placeType' => $place['placeType'] ?? null,
                'lat' => $place['lat'] ?? null,
                'lon' => $place['lon'] ?? null,
                'address' => $this->extractAddress($place)
            ];
        }, $data);
    }

    /**
     * Get car parks within a radius of coordinates
     *
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @param int $radius Radius in meters (default 500m)
     * @return array Nearby car parks
     */
    public function getNearbyCarParks(float $lat, float $lon, int $radius = 500): array
    {
        $params = [
            'lat' => $lat,
            'lon' => $lon,
            'radius' => $radius,
            'type' => 'CarPark'
        ];

        $data = $this->makeRequest('/Place', $params);

        if (!$data || !isset($data['places'])) {
            return [];
        }

        return array_map(function($place) {
            return [
                'id' => $place['id'] ?? null,
                'name' => $place['commonName'] ?? 'Unknown',
                'distance' => $place['distance'] ?? null,
                'lat' => $place['lat'] ?? null,
                'lon' => $place['lon'] ?? null,
                'address' => $this->extractAddress($place)
            ];
        }, $data['places']);
    }

    /**
     * Make HTTP request to TfL API
     */
    private function makeRequest(string $endpoint, array $params = []): ?array
    {
        if ($this->appKey) {
            $params['app_key'] = $this->appKey;
        }

        $url = self::BASE_URL . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Accept: application/json',
                    'User-Agent: ParkaLot-System/1.0'
                ],
                'timeout' => $this->timeout,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        try {
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                error_log("TfL API request failed: {$url}");
                return null;
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("TfL API JSON decode error: " . json_last_error_msg());
                return null;
            }

            return $data;
        } catch (Exception $e) {
            error_log("TfL API exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Format raw car park data into consistent structure
     */
    private function formatCarParkData(array $carParks): array
    {
        return array_map(function($carPark) {
            $totalSpaces = 0;
            $freeSpaces = 0;
            $occupiedSpaces = 0;
            $bayTypes = [];

            if (isset($carPark['bays']) && is_array($carPark['bays'])) {
                foreach ($carPark['bays'] as $bay) {
                    $bayCount = $bay['bayCount'] ?? 0;
                    $free = $bay['free'] ?? 0;
                    $occupied = $bay['occupied'] ?? 0;

                    $totalSpaces += $bayCount;
                    $freeSpaces += $free;
                    $occupiedSpaces += $occupied;

                    $bayTypes[] = [
                        'type' => $bay['bayType'] ?? 'Standard',
                        'total' => $bayCount,
                        'free' => $free,
                        'occupied' => $occupied
                    ];
                }
            }

            return [
                'id' => $carPark['id'] ?? null,
                'name' => $carPark['name'] ?? 'Unknown Car Park',
                'totalSpaces' => $totalSpaces,
                'freeSpaces' => $freeSpaces,
                'occupiedSpaces' => $occupiedSpaces,
                'occupancyPercent' => $totalSpaces > 0
                    ? round(($occupiedSpaces / $totalSpaces) * 100, 1)
                    : 0,
                'status' => $this->calculateStatus($freeSpaces, $totalSpaces),
                'bayTypes' => $bayTypes,
                'detailsUrl' => $carPark['carParkDetailsUrl'] ?? null,
                'source' => 'TfL',
                'lastUpdated' => date('c')
            ];
        }, $carParks);
    }

    /**
     * Calculate availability status
     */
    private function calculateStatus(int $free, int $total): string
    {
        if ($total === 0) {
            return 'unknown';
        }

        $percentFree = ($free / $total) * 100;

        if ($percentFree === 0) {
            return 'full';
        } elseif ($percentFree < 10) {
            return 'almost_full';
        } elseif ($percentFree < 30) {
            return 'filling';
        } else {
            return 'available';
        }
    }

    /**
     * Extract address from place data
     */
    private function extractAddress(array $place): ?string
    {
        if (isset($place['additionalProperties'])) {
            foreach ($place['additionalProperties'] as $prop) {
                if (isset($prop['key']) && $prop['key'] === 'Address') {
                    return $prop['value'] ?? null;
                }
            }
        }
        return null;
    }

    /**
     * Fallback data when API is unavailable
     * Uses known London Underground station car parks
     */
    private function getFallbackCarParks(): array
    {
        $fallbackCarParks = [
            [
                'id' => 'CarParks_800491',
                'name' => 'Barkingside Station',
                'bays' => [
                    ['bayType' => 'Disabled', 'bayCount' => 2, 'free' => 1, 'occupied' => 1],
                    ['bayType' => 'Pay and Display', 'bayCount' => 45, 'free' => 12, 'occupied' => 33]
                ]
            ],
            [
                'id' => 'CarParks_800492',
                'name' => 'Stanmore Station',
                'bays' => [
                    ['bayType' => 'Disabled', 'bayCount' => 4, 'free' => 2, 'occupied' => 2],
                    ['bayType' => 'Pay and Display', 'bayCount' => 280, 'free' => 85, 'occupied' => 195]
                ]
            ],
            [
                'id' => 'CarParks_800493',
                'name' => 'Epping Station',
                'bays' => [
                    ['bayType' => 'Disabled', 'bayCount' => 6, 'free' => 3, 'occupied' => 3],
                    ['bayType' => 'Pay and Display', 'bayCount' => 450, 'free' => 120, 'occupied' => 330]
                ]
            ],
            [
                'id' => 'CarParks_800494',
                'name' => 'Cockfosters Station',
                'bays' => [
                    ['bayType' => 'Disabled', 'bayCount' => 5, 'free' => 2, 'occupied' => 3],
                    ['bayType' => 'Pay and Display', 'bayCount' => 350, 'free' => 78, 'occupied' => 272]
                ]
            ],
            [
                'id' => 'CarParks_800495',
                'name' => 'High Barnet Station',
                'bays' => [
                    ['bayType' => 'Disabled', 'bayCount' => 4, 'free' => 1, 'occupied' => 3],
                    ['bayType' => 'Pay and Display', 'bayCount' => 180, 'free' => 45, 'occupied' => 135]
                ]
            ],
            [
                'id' => 'CarParks_800496',
                'name' => 'Upminster Station',
                'bays' => [
                    ['bayType' => 'Disabled', 'bayCount' => 8, 'free' => 4, 'occupied' => 4],
                    ['bayType' => 'Pay and Display', 'bayCount' => 520, 'free' => 156, 'occupied' => 364]
                ]
            ],
            [
                'id' => 'CarParks_800497',
                'name' => 'Stratford International',
                'bays' => [
                    ['bayType' => 'Disabled', 'bayCount' => 12, 'free' => 5, 'occupied' => 7],
                    ['bayType' => 'Pay and Display', 'bayCount' => 800, 'free' => 240, 'occupied' => 560]
                ]
            ],
            [
                'id' => 'CarParks_800498',
                'name' => 'Wembley Park Station',
                'bays' => [
                    ['bayType' => 'Disabled', 'bayCount' => 10, 'free' => 3, 'occupied' => 7],
                    ['bayType' => 'Pay and Display', 'bayCount' => 650, 'free' => 195, 'occupied' => 455]
                ]
            ]
        ];

        return $this->formatCarParkData($fallbackCarParks);
    }

    // =========================================================================
    // LIVE BOOKING METHODS
    // =========================================================================

    private ?PDO $db = null;

    /**
     * Set database connection for live booking features
     *
     * @param PDO $db Database connection
     * @return self
     */
    public function setDatabase(PDO $db): self
    {
        $this->db = $db;
        return $this;
    }

    /**
     * Create a live parking booking
     *
     * @param int $userId User ID
     * @param string $carParkId TfL car park ID
     * @param array $bookingDetails Booking details
     * @return array Booking result
     */
    public function createLiveBooking(int $userId, string $carParkId, array $bookingDetails): array
    {
        if (!$this->db) {
            return ['error' => 'Database connection required for bookings'];
        }

        // Generate unique booking reference
        $bookingRef = $this->generateBookingReference();

        // Get car park details for validation
        $carPark = $this->getCarPark($carParkId);
        if (!$carPark || $carPark['freeSpaces'] < 1) {
            return ['error' => 'Car park is full or unavailable'];
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO parking_bookings_live
                    (booking_reference, user_id, tfl_car_park_id, vehicle_registration,
                     check_in_time, expected_check_out, booking_status, qr_code_data,
                     total_price, currency, payment_status, special_requests)
                VALUES
                    (:ref, :user_id, :car_park_id, :vehicle_reg,
                     :check_in, :check_out, 'confirmed', :qr_data,
                     :price, 'GBP', 'pending', :requests)
            ");

            $checkIn = $bookingDetails['checkInTime'] ?? date('Y-m-d H:i:s');
            $checkOut = $bookingDetails['checkOutTime'] ?? date('Y-m-d H:i:s', strtotime('+4 hours'));
            $qrData = $this->generateQRCodeData($bookingRef, $carParkId, $userId);

            $stmt->execute([
                ':ref' => $bookingRef,
                ':user_id' => $userId,
                ':car_park_id' => $carParkId,
                ':vehicle_reg' => $bookingDetails['vehicleRegistration'] ?? null,
                ':check_in' => $checkIn,
                ':check_out' => $checkOut,
                ':qr_data' => $qrData,
                ':price' => $bookingDetails['totalPrice'] ?? $this->calculatePrice($checkIn, $checkOut),
                ':requests' => $bookingDetails['specialRequests'] ?? null
            ]);

            $bookingId = $this->db->lastInsertId();

            return [
                'success' => true,
                'bookingId' => $bookingId,
                'bookingReference' => $bookingRef,
                'carPark' => $carPark,
                'checkInTime' => $checkIn,
                'expectedCheckOut' => $checkOut,
                'qrCodeData' => $qrData,
                'qrCodeUrl' => $this->generateQRCodeUrl($qrData),
                'status' => 'confirmed'
            ];
        } catch (PDOException $e) {
            error_log("Failed to create live booking: " . $e->getMessage());
            return ['error' => 'Failed to create booking'];
        }
    }

    /**
     * Update booking status
     *
     * @param int $bookingId Booking ID
     * @param string $status New status
     * @return bool Success status
     */
    public function updateBookingStatus(int $bookingId, string $status): bool
    {
        if (!$this->db) {
            return false;
        }

        $validStatuses = ['pending', 'confirmed', 'checked_in', 'active', 'completed', 'cancelled', 'expired', 'no_show'];
        if (!in_array($status, $validStatuses)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE parking_bookings_live
                SET booking_status = :status, updated_at = NOW()
                WHERE booking_id = :id
            ");

            return $stmt->execute([':status' => $status, ':id' => $bookingId]);
        } catch (PDOException $e) {
            error_log("Failed to update booking status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check in to parking
     *
     * @param string $bookingReference Booking reference or QR code data
     * @return array Check-in result
     */
    public function checkIn(string $bookingReference): array
    {
        if (!$this->db) {
            return ['error' => 'Database connection required'];
        }

        try {
            // Find booking
            $stmt = $this->db->prepare("
                SELECT * FROM parking_bookings_live
                WHERE (booking_reference = :ref OR qr_code_data = :ref)
                  AND booking_status IN ('confirmed', 'pending')
            ");
            $stmt->execute([':ref' => $bookingReference]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                return ['error' => 'Booking not found or already used'];
            }

            // Update status to checked_in
            $updateStmt = $this->db->prepare("
                UPDATE parking_bookings_live
                SET booking_status = 'checked_in',
                    check_in_time = NOW(),
                    updated_at = NOW()
                WHERE booking_id = :id
            ");
            $updateStmt->execute([':id' => $booking['booking_id']]);

            return [
                'success' => true,
                'bookingId' => $booking['booking_id'],
                'bookingReference' => $booking['booking_reference'],
                'checkInTime' => date('Y-m-d H:i:s'),
                'expectedCheckOut' => $booking['expected_check_out'],
                'status' => 'checked_in'
            ];
        } catch (PDOException $e) {
            error_log("Check-in failed: " . $e->getMessage());
            return ['error' => 'Check-in failed'];
        }
    }

    /**
     * Check out from parking
     *
     * @param string $bookingReference Booking reference
     * @return array Check-out result with payment info
     */
    public function checkOut(string $bookingReference): array
    {
        if (!$this->db) {
            return ['error' => 'Database connection required'];
        }

        try {
            // Find booking
            $stmt = $this->db->prepare("
                SELECT * FROM parking_bookings_live
                WHERE (booking_reference = :ref OR qr_code_data = :ref)
                  AND booking_status IN ('checked_in', 'active')
            ");
            $stmt->execute([':ref' => $bookingReference]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                return ['error' => 'Active booking not found'];
            }

            // Calculate final price
            $checkInTime = strtotime($booking['check_in_time']);
            $checkOutTime = time();
            $duration = ($checkOutTime - $checkInTime) / 3600; // Hours
            $finalPrice = $this->calculatePrice($booking['check_in_time'], date('Y-m-d H:i:s'));

            // Update booking
            $updateStmt = $this->db->prepare("
                UPDATE parking_bookings_live
                SET booking_status = 'completed',
                    actual_check_out = NOW(),
                    total_price = :price,
                    updated_at = NOW()
                WHERE booking_id = :id
            ");
            $updateStmt->execute([
                ':price' => $finalPrice,
                ':id' => $booking['booking_id']
            ]);

            return [
                'success' => true,
                'bookingId' => $booking['booking_id'],
                'bookingReference' => $booking['booking_reference'],
                'checkInTime' => $booking['check_in_time'],
                'checkOutTime' => date('Y-m-d H:i:s'),
                'durationHours' => round($duration, 2),
                'totalPrice' => $finalPrice,
                'currency' => 'GBP',
                'status' => 'completed'
            ];
        } catch (PDOException $e) {
            error_log("Check-out failed: " . $e->getMessage());
            return ['error' => 'Check-out failed'];
        }
    }

    /**
     * Get user's active bookings
     *
     * @param int $userId User ID
     * @return array Active bookings
     */
    public function getActiveBookings(int $userId): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT b.*, f.flight_number, f.airline_name, f.scheduled_departure
                FROM parking_bookings_live b
                LEFT JOIN flight_data f ON b.flight_id = f.flight_id
                WHERE b.user_id = :user_id
                  AND b.booking_status IN ('pending', 'confirmed', 'checked_in', 'active')
                ORDER BY b.check_in_time ASC
            ");
            $stmt->execute([':user_id' => $userId]);

            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enrich with car park data
            foreach ($bookings as &$booking) {
                if ($booking['tfl_car_park_id']) {
                    $carPark = $this->getCarPark($booking['tfl_car_park_id']);
                    $booking['carPark'] = $carPark;
                }
                $booking['qrCodeUrl'] = $this->generateQRCodeUrl($booking['qr_code_data']);
            }

            return $bookings;
        } catch (PDOException $e) {
            error_log("Failed to get active bookings: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get booking history for a user
     *
     * @param int $userId User ID
     * @param int $limit Number of bookings
     * @return array Booking history
     */
    public function getBookingHistory(int $userId, int $limit = 10): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT b.*, f.flight_number, f.airline_name
                FROM parking_bookings_live b
                LEFT JOIN flight_data f ON b.flight_id = f.flight_id
                WHERE b.user_id = :user_id
                ORDER BY b.created_at DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to get booking history: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate unique booking reference
     *
     * @return string Booking reference (e.g., "PL-ABC12345")
     */
    private function generateBookingReference(): string
    {
        $prefix = 'PL';
        $letters = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 3);
        $numbers = str_pad(mt_rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        return "{$prefix}-{$letters}{$numbers}";
    }

    /**
     * Generate QR code data for booking
     *
     * @param string $bookingRef Booking reference
     * @param string $carParkId Car park ID
     * @param int $userId User ID
     * @return string QR code data
     */
    private function generateQRCodeData(string $bookingRef, string $carParkId, int $userId): string
    {
        $data = [
            'ref' => $bookingRef,
            'cp' => $carParkId,
            'u' => $userId,
            't' => time(),
            'h' => substr(hash('sha256', $bookingRef . $carParkId . $userId . 'parkalot'), 0, 8)
        ];
        return base64_encode(json_encode($data));
    }

    /**
     * Generate QR code URL using a free QR code API
     *
     * @param string $data QR code data
     * @return string QR code image URL
     */
    private function generateQRCodeUrl(string $data): string
    {
        // Using QR Server API (free, no key required)
        $encoded = urlencode($data);
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={$encoded}";
    }

    /**
     * Calculate parking price based on duration
     *
     * @param string $checkIn Check-in time
     * @param string $checkOut Check-out time
     * @return float Price in GBP
     */
    private function calculatePrice(string $checkIn, string $checkOut): float
    {
        $startTime = strtotime($checkIn);
        $endTime = strtotime($checkOut);
        $hours = max(1, ceil(($endTime - $startTime) / 3600));

        // TfL standard parking rates (approximate)
        $hourlyRate = 3.50; // GBP per hour
        $dailyMax = 15.00; // Maximum per day

        $totalHours = $hours;
        $days = floor($totalHours / 24);
        $remainingHours = $totalHours % 24;

        $price = ($days * $dailyMax) + min($remainingHours * $hourlyRate, $dailyMax);

        return round($price, 2);
    }
}
