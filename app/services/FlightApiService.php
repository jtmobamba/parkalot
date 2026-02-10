<?php
/**
 * Flight API Service
 *
 * Integrates with FlightAPI.io for real-time flight tracking
 * at London airports (LHR, LGW, STN, LTN, LCY).
 *
 * API Documentation: https://www.flightapi.io/
 */

require_once __DIR__ . '/BaseApiService.php';
require_once __DIR__ . '/../../config/flightapi.php';

class FlightApiService extends BaseApiService
{
    /**
     * Constructor
     *
     * @param PDO|null $db Database connection
     */
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db, FLIGHTAPI_KEY, API_REQUEST_TIMEOUT);
        $this->baseUrl = FLIGHTAPI_URL;
        $this->apiSource = 'flightapi';
        $this->cacheExpiry = FLIGHTAPI_CACHE_TTL;
    }

    /**
     * Get flight status by flight number
     *
     * @param string $flightNumber Flight number (e.g., 'BA123')
     * @return array|null Flight data or null if not found
     */
    public function getFlightStatus(string $flightNumber): ?array
    {
        $flightNumber = strtoupper(trim($flightNumber));

        // Validate flight number format
        if (!preg_match('/^[A-Z]{2}\d{1,4}[A-Z]?$/i', $flightNumber)) {
            return null;
        }

        // Check if we should use mock data
        if ($this->useMockData || empty($this->apiKey)) {
            return $this->getMockFlightStatus($flightNumber);
        }

        // Check cache first
        $cacheKey = "flight_{$flightNumber}_" . date('Ymd');
        $cached = $this->cacheGet($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Fetch from API
        $endpoint = "/flight/status/{$flightNumber}";
        $data = $this->makeRequest($endpoint, ['api_key' => $this->apiKey]);

        if (!$data) {
            return $this->getMockFlightStatus($flightNumber);
        }

        $formatted = $this->formatFlightData($data);

        // Cache the result
        $this->cacheSet($cacheKey, $formatted);

        // Save to database
        $this->saveFlightToDb($formatted);

        return $formatted;
    }

    /**
     * Get departures from a London airport
     *
     * @param string $airportCode IATA airport code
     * @param string|null $date Date (Y-m-d format)
     * @param int $limit Number of flights
     * @return array Departures list
     */
    public function getAirportDepartures(string $airportCode, ?string $date = null, int $limit = 20): array
    {
        $airportCode = strtoupper(trim($airportCode));

        // Validate airport code
        if (!isset(LONDON_AIRPORTS[$airportCode])) {
            return ['error' => 'Invalid London airport code', 'validCodes' => array_keys(LONDON_AIRPORTS)];
        }

        $date = $date ?? date('Y-m-d');

        // Check if we should use mock data
        if ($this->useMockData || empty($this->apiKey)) {
            return $this->getMockFlights($airportCode, 'departures', $limit);
        }

        // Check cache first
        $cacheKey = "departures_{$airportCode}_{$date}_{$limit}";
        $cached = $this->cacheGet($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Fetch from API
        $endpoint = "/airport/departures/{$airportCode}";
        $params = [
            'api_key' => $this->apiKey,
            'date' => $date,
            'limit' => $limit
        ];

        $data = $this->makeRequest($endpoint, $params);

        if (!$data) {
            return $this->getMockFlights($airportCode, 'departures', $limit);
        }

        $formatted = [
            'airport' => LONDON_AIRPORTS[$airportCode],
            'airportCode' => $airportCode,
            'type' => 'departures',
            'date' => $date,
            'flights' => array_map([$this, 'formatFlightData'], $data['data'] ?? []),
            'total' => count($data['data'] ?? [])
        ];

        // Cache the result
        $this->cacheSet($cacheKey, $formatted);

        return $formatted;
    }

    /**
     * Get arrivals at a London airport
     *
     * @param string $airportCode IATA airport code
     * @param string|null $date Date (Y-m-d format)
     * @param int $limit Number of flights
     * @return array Arrivals list
     */
    public function getAirportArrivals(string $airportCode, ?string $date = null, int $limit = 20): array
    {
        $airportCode = strtoupper(trim($airportCode));

        // Validate airport code
        if (!isset(LONDON_AIRPORTS[$airportCode])) {
            return ['error' => 'Invalid London airport code', 'validCodes' => array_keys(LONDON_AIRPORTS)];
        }

        $date = $date ?? date('Y-m-d');

        // Check if we should use mock data
        if ($this->useMockData || empty($this->apiKey)) {
            return $this->getMockFlights($airportCode, 'arrivals', $limit);
        }

        // Check cache first
        $cacheKey = "arrivals_{$airportCode}_{$date}_{$limit}";
        $cached = $this->cacheGet($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Fetch from API
        $endpoint = "/airport/arrivals/{$airportCode}";
        $params = [
            'api_key' => $this->apiKey,
            'date' => $date,
            'limit' => $limit
        ];

        $data = $this->makeRequest($endpoint, $params);

        if (!$data) {
            return $this->getMockFlights($airportCode, 'arrivals', $limit);
        }

        $formatted = [
            'airport' => LONDON_AIRPORTS[$airportCode],
            'airportCode' => $airportCode,
            'type' => 'arrivals',
            'date' => $date,
            'flights' => array_map([$this, 'formatFlightData'], $data['data'] ?? []),
            'total' => count($data['data'] ?? [])
        ];

        // Cache the result
        $this->cacheSet($cacheKey, $formatted);

        return $formatted;
    }

    /**
     * Search for flights by query
     *
     * @param string $query Search query (flight number, airline, route)
     * @return array Search results
     */
    public function searchFlights(string $query): array
    {
        $query = trim($query);

        if (empty($query)) {
            return ['error' => 'Search query is required'];
        }

        // Check if it looks like a flight number
        if (preg_match('/^[A-Z]{2}\d{1,4}[A-Z]?$/i', $query)) {
            $flight = $this->getFlightStatus($query);
            if ($flight) {
                return ['results' => [$flight], 'total' => 1];
            }
        }

        // Check if we should use mock data
        if ($this->useMockData || empty($this->apiKey)) {
            return $this->getMockSearchResults($query);
        }

        // Fetch from API
        $endpoint = "/flights/search";
        $data = $this->makeRequest($endpoint, [
            'api_key' => $this->apiKey,
            'query' => $query
        ]);

        if (!$data) {
            return $this->getMockSearchResults($query);
        }

        return [
            'results' => array_map([$this, 'formatFlightData'], $data['data'] ?? []),
            'total' => count($data['data'] ?? []),
            'query' => $query
        ];
    }

    /**
     * Link a parking booking to a flight
     *
     * @param int $bookingId Parking booking ID
     * @param int $flightId Flight ID
     * @return bool Success status
     */
    public function linkParkingToFlight(int $bookingId, int $flightId): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE parking_bookings_live
                SET flight_id = :flightId, updated_at = NOW()
                WHERE booking_id = :bookingId
            ");

            return $stmt->execute([
                ':flightId' => $flightId,
                ':bookingId' => $bookingId
            ]);
        } catch (PDOException $e) {
            $this->logError("Failed to link parking to flight: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all London airports
     *
     * @return array Airport data
     */
    public function getLondonAirports(): array
    {
        return LONDON_AIRPORTS;
    }

    /**
     * Get flight status styling
     *
     * @param string $status Flight status
     * @return array Status styling (label, color, icon)
     */
    public function getStatusStyle(string $status): array
    {
        return FLIGHT_STATUS_MAP[$status] ?? FLIGHT_STATUS_MAP['scheduled'];
    }

    /**
     * Get mock flight status
     *
     * @param string $flightNumber Flight number
     * @return array|null Mock flight data
     */
    protected function getMockFlightStatus(string $flightNumber): ?array
    {
        return getFlightApiDemoFlightStatus($flightNumber);
    }

    /**
     * Get mock flights for an airport
     *
     * @param string $airportCode Airport code
     * @param string $type 'departures' or 'arrivals'
     * @param int $limit Number of flights
     * @return array Mock flights
     */
    protected function getMockFlights(string $airportCode, string $type, int $limit): array
    {
        $flights = getFlightApiDemoData($airportCode, $type, $limit);

        return [
            'airport' => LONDON_AIRPORTS[$airportCode],
            'airportCode' => $airportCode,
            'type' => $type,
            'date' => date('Y-m-d'),
            'flights' => $flights,
            'total' => count($flights),
            'isMockData' => true
        ];
    }

    /**
     * Get mock search results
     *
     * @param string $query Search query
     * @return array Mock search results
     */
    protected function getMockSearchResults(string $query): array
    {
        // Generate some mock results based on query
        $flights = getFlightApiDemoData('LHR', 'departures', 5);

        return [
            'results' => array_slice($flights, 0, 3),
            'total' => 3,
            'query' => $query,
            'isMockData' => true
        ];
    }

    /**
     * Save flight data to database
     *
     * @param array $flight Flight data
     */
    protected function saveFlightToDb(array $flight): void
    {
        if (!$this->db || empty($flight)) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO flight_data
                    (flight_number, flight_iata, airline_code, airline_name, airline_logo,
                     departure_airport, departure_airport_name, departure_city, departure_terminal, departure_gate,
                     arrival_airport, arrival_airport_name, arrival_city, arrival_terminal, arrival_gate,
                     scheduled_departure, estimated_departure, flight_status, delay_minutes, flight_date, expires_at)
                VALUES
                    (:number, :iata, :airline_code, :airline_name, :airline_logo,
                     :dep_airport, :dep_name, :dep_city, :dep_terminal, :dep_gate,
                     :arr_airport, :arr_name, :arr_city, :arr_terminal, :arr_gate,
                     :scheduled, :estimated, :status, :delay, :flight_date, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
                ON DUPLICATE KEY UPDATE
                    flight_status = VALUES(flight_status),
                    delay_minutes = VALUES(delay_minutes),
                    estimated_departure = VALUES(estimated_departure),
                    cached_at = NOW(),
                    expires_at = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
            ");

            $stmt->execute([
                ':number' => $flight['flightNumber'],
                ':iata' => $flight['flightIata'] ?? $flight['flightNumber'],
                ':airline_code' => $flight['airline']['code'] ?? null,
                ':airline_name' => $flight['airline']['name'] ?? null,
                ':airline_logo' => $flight['airline']['logo'] ?? null,
                ':dep_airport' => $flight['departure']['airport'] ?? null,
                ':dep_name' => $flight['departure']['airportName'] ?? null,
                ':dep_city' => $flight['departure']['city'] ?? null,
                ':dep_terminal' => $flight['departure']['terminal'] ?? $flight['terminal'] ?? null,
                ':dep_gate' => $flight['departure']['gate'] ?? $flight['gate'] ?? null,
                ':arr_airport' => $flight['arrival']['airport'] ?? null,
                ':arr_name' => $flight['arrival']['airportName'] ?? null,
                ':arr_city' => $flight['arrival']['city'] ?? null,
                ':arr_terminal' => $flight['arrival']['terminal'] ?? null,
                ':arr_gate' => $flight['arrival']['gate'] ?? null,
                ':scheduled' => date('Y-m-d H:i:s', strtotime($flight['scheduledTime'] ?? 'now')),
                ':estimated' => date('Y-m-d H:i:s', strtotime($flight['estimatedTime'] ?? $flight['scheduledTime'] ?? 'now')),
                ':status' => $flight['status'] ?? 'scheduled',
                ':delay' => $flight['delayMinutes'] ?? 0,
                ':flight_date' => $flight['flightDate'] ?? date('Y-m-d')
            ]);
        } catch (PDOException $e) {
            $this->logError("Failed to save flight: " . $e->getMessage());
        }
    }

    /**
     * Get HTTP headers for Flight API
     *
     * @return array Headers
     */
    protected function getHeaders(): array
    {
        return getFlightApiHeaders();
    }

    /**
     * Format flight data from API response
     *
     * @param array $data Raw flight data
     * @return array Formatted flight data
     */
    protected function formatFlightData(array $data): array
    {
        $status = $data['status'] ?? 'scheduled';
        $statusStyle = $this->getStatusStyle($status);

        return [
            'flightNumber' => $data['flightNumber'] ?? $data['flight_number'] ?? '',
            'flightIata' => $data['flightIata'] ?? $data['flight_iata'] ?? $data['flightNumber'] ?? '',
            'airline' => [
                'code' => $data['airline']['code'] ?? $data['airline_code'] ?? '',
                'name' => $data['airline']['name'] ?? $data['airline_name'] ?? '',
                'logo' => $data['airline']['logo'] ?? $data['airline_logo'] ?? ''
            ],
            'departure' => [
                'airport' => $data['departure']['airport'] ?? $data['departure_airport'] ?? '',
                'airportName' => $data['departure']['airportName'] ?? $data['departure_airport_name'] ?? '',
                'city' => $data['departure']['city'] ?? $data['departure_city'] ?? '',
                'terminal' => $data['departure']['terminal'] ?? $data['terminal'] ?? '',
                'gate' => $data['departure']['gate'] ?? $data['gate'] ?? ''
            ],
            'arrival' => [
                'airport' => $data['arrival']['airport'] ?? $data['arrival_airport'] ?? '',
                'airportName' => $data['arrival']['airportName'] ?? $data['arrival_airport_name'] ?? '',
                'city' => $data['arrival']['city'] ?? $data['arrival_city'] ?? '',
                'terminal' => $data['arrival']['terminal'] ?? '',
                'gate' => $data['arrival']['gate'] ?? ''
            ],
            'status' => $status,
            'statusLabel' => $statusStyle['label'],
            'statusColor' => $statusStyle['color'],
            'statusIcon' => $statusStyle['icon'],
            'delayMinutes' => $data['delayMinutes'] ?? $data['delay_minutes'] ?? 0,
            'scheduledTime' => $data['scheduledTime'] ?? $data['scheduled_time'] ?? '',
            'estimatedTime' => $data['estimatedTime'] ?? $data['estimated_time'] ?? '',
            'flightDate' => $data['flightDate'] ?? $data['flight_date'] ?? date('Y-m-d')
        ];
    }

    /**
     * Format response (inherited abstract method)
     *
     * @param array $data Raw API response
     * @return array Formatted data
     */
    protected function formatResponse(array $data): array
    {
        return $this->formatFlightData($data);
    }

    /**
     * Get fallback data
     *
     * @return array Fallback data
     */
    protected function getFallbackData(): array
    {
        return $this->getMockFlights('LHR', 'departures', 10);
    }
}
