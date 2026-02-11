<?php
// Suppress all PHP errors and warnings from being output (but still log them)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Configure session cookie to be available site-wide
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,  // Set to true in production with HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Load security configuration
require_once '../config/security.php';

// Configure error handling based on environment
configureErrorHandling();

// Set security headers
setSecurityHeaders();

// Enforce HTTPS in production
enforceHTTPS();

header("Content-Type: application/json");

require '../config/database.php';
require '../app/models/UserDAO.php';
require '../app/controllers/InvoiceController.php';
require '../app/models/GarageDAO.php';
require '../app/models/ReservationDAO.php';
require '../app/factories/DAOFactory.php';
require '../app/controllers/AuthController.php';
require '../app/controllers/ReservationController.php';
require '../app/controllers/EmployeeController.php';
require '../app/controllers/RecommendationController.php';
require '../app/controllers/JobApplicationController.php';
require '../app/services/NotificationService.php';
require '../app/services/EmailVerificationService.php';
require '../app/models/ActivityLogDAO.php';
require '../app/utils/SimplePDF.php';
require '../app/utils/CSRFProtection.php';
require '../config/vehicle_api.php';
require '../app/services/TfLParkingService.php';

$db = Database::connect();

// Support both styles:
// - /api/index.php/login (PATH_INFO)
// - /api/index.php?route=login (query param)  <-- used by current frontend
$route = $_GET['route'] ?? null;
$path = $route ? ('/' . ltrim((string)$route, '/')) : ($_SERVER['PATH_INFO'] ?? '/');

// Define routes that don't require authentication or session check
$publicRoutes = [
    '/login', '/register', '/garages', '/recommendations', '/job_application',
    '/csrf-token', '/password/request-reset', '/password/reset', '/vehicles/public-check',
    '/london-parking', '/london-parking/search', '/london-parking/nearby', '/london-parking/ev-chargers',
    '/parking/live-activity',
    // New API endpoints
    '/trustpilot/reviews', '/trustpilot/stats', '/trustpilot/widget',
    '/flights/status', '/flights/search', '/flights/airport', '/flights/airports',
    '/pexels/images', '/pexels/hero',
    '/api/status',
    // Customer spaces and payments (search is public)
    '/spaces', '/spaces/detail', '/spaces/calculate-price',
    '/payment/config', '/payment/webhook',
    '/airport-booking/calculate'
];

// Check session timeout only for authenticated routes
if (!in_array($path, $publicRoutes) && isset($_SESSION['user_id'])) {
    if (!checkSessionTimeout()) {
        http_response_code(401);
        echo json_encode([
            "error" => "Session expired. Please log in again.",
            "code" => "session_expired"
        ]);
        exit;
    }
}
$data = json_decode(file_get_contents("php://input"));

function respond($payload) {
    // Map known error codes to HTTP status codes so `fetch().ok` behaves correctly.
    if (is_array($payload) && isset($payload['code'])) {
        switch ($payload['code']) {
            case 'validation':
            case 'bad_json':
                http_response_code(400);
                break;
            case 'invalid_credentials':
            case 'email_not_verified':
                http_response_code(401);
                break;
            case 'account_exists':
                http_response_code(409);
                break;
            case 'verification_required':
                http_response_code(200); // Success but requires verification
                break;
            case 'rate_limited':
                http_response_code(429);
                break;
            default:
                http_response_code(400);
                break;
        }
    }
    
    // Ensure we only output JSON
    echo json_encode($payload);
}

switch ($path) {
    case '/login':
        respond((new AuthController($db))->login($data));
        break;

    case '/register':
        respond((new AuthController($db))->register($data));
        break;

    case '/reserve':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Get user's reservations
            $controller = new ReservationController($db);
            echo json_encode($controller->getUserReservations($_SESSION['user_id']));
        } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Create new reservation
            echo json_encode(
                (new ReservationController($db))
                    ->create($data, $_SESSION['user_id'])
            );
        }
        break;

    case '/garages':
        // Public list (used for dropdown); no auth needed.
        try {
            $garages = DAOFactory::garageDAO($db)->listGarages();
            echo json_encode(["garages" => $garages]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(["error" => "Unable to load garages"]);
        }
        break;

    // =====================================================
    // TfL London Parking API Routes (Real-time availability)
    // =====================================================
    case '/london-parking':
        // Get all London car parks with real-time availability
        try {
            $tflService = new TfLParkingService();

            // Check if specific car park ID requested
            $carParkId = $_GET['id'] ?? null;

            if ($carParkId) {
                $carPark = $tflService->getCarPark($carParkId);
                if ($carPark) {
                    echo json_encode([
                        'success' => true,
                        'carPark' => $carPark
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode([
                        'success' => false,
                        'error' => 'Car park not found'
                    ]);
                }
            } else {
                $carParks = $tflService->getAllCarParks();
                echo json_encode([
                    'success' => true,
                    'count' => count($carParks),
                    'carParks' => $carParks,
                    'source' => 'Transport for London Unified API',
                    'documentation' => 'https://api.tfl.gov.uk/'
                ]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch London parking data'
            ]);
        }
        break;

    case '/london-parking/search':
        // Search car parks by location name
        try {
            $query = $_GET['q'] ?? $_GET['query'] ?? null;

            if (!$query) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Search query required. Use ?q=location'
                ]);
                break;
            }

            $tflService = new TfLParkingService();
            $results = $tflService->searchCarParks($query);

            echo json_encode([
                'success' => true,
                'query' => $query,
                'count' => count($results),
                'carParks' => $results
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Search failed'
            ]);
        }
        break;

    case '/london-parking/nearby':
        // Find car parks near coordinates
        try {
            $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
            $lon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;
            $radius = isset($_GET['radius']) ? intval($_GET['radius']) : 500;

            if ($lat === null || $lon === null) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Latitude and longitude required. Use ?lat=51.5&lon=-0.1'
                ]);
                break;
            }

            // Validate London area (roughly)
            if ($lat < 51.2 || $lat > 51.8 || $lon < -0.6 || $lon > 0.4) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Coordinates must be within Greater London area'
                ]);
                break;
            }

            $tflService = new TfLParkingService();
            $results = $tflService->getNearbyCarParks($lat, $lon, $radius);

            echo json_encode([
                'success' => true,
                'location' => ['lat' => $lat, 'lon' => $lon],
                'radius' => $radius,
                'count' => count($results),
                'carParks' => $results
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Nearby search failed'
            ]);
        }
        break;

    case '/london-parking/ev-chargers':
        // Get EV charge connector availability
        try {
            $tflService = new TfLParkingService();
            $connectors = $tflService->getChargeConnectors();

            echo json_encode([
                'success' => true,
                'count' => count($connectors),
                'chargeConnectors' => $connectors
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch EV charger data'
            ]);
        }
        break;

    // =====================================================
    // TRUSTPILOT API Routes (Reviews & Ratings)
    // =====================================================
    case '/trustpilot/reviews':
        // Get Trustpilot reviews (public)
        try {
            require_once '../app/services/TrustpilotService.php';
            $trustpilotService = new TrustpilotService($db);
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $reviews = $trustpilotService->getReviews($limit);

            echo json_encode([
                'success' => true,
                'reviews' => $reviews['reviews'] ?? [],
                'total' => $reviews['total'] ?? 0,
                'isMockData' => $trustpilotService->isUsingMockData()
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch reviews'
            ]);
        }
        break;

    case '/trustpilot/stats':
        // Get Trustpilot business statistics (public)
        try {
            require_once '../app/services/TrustpilotService.php';
            $trustpilotService = new TrustpilotService($db);
            $stats = $trustpilotService->getBusinessStats();

            echo json_encode([
                'success' => true,
                'stats' => $stats,
                'isMockData' => $trustpilotService->isUsingMockData()
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch business stats'
            ]);
        }
        break;

    case '/trustpilot/widget':
        // Get combined widget data (reviews + stats) (public)
        try {
            require_once '../app/services/TrustpilotService.php';
            $trustpilotService = new TrustpilotService($db);
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
            $widgetData = $trustpilotService->getWidgetData($limit);

            echo json_encode([
                'success' => true,
                'data' => $widgetData
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch widget data'
            ]);
        }
        break;

    // =====================================================
    // FLIGHT API Routes (Airport Flight Tracking)
    // =====================================================
    case '/flights/status':
        // Get status for a specific flight (public)
        try {
            require_once '../app/services/FlightApiService.php';
            $flightService = new FlightApiService($db);
            $flightNumber = $_GET['flight'] ?? $_GET['number'] ?? null;

            if (!$flightNumber) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Flight number is required'
                ]);
                break;
            }

            $flight = $flightService->getFlightStatus($flightNumber);

            if ($flight) {
                echo json_encode([
                    'success' => true,
                    'flight' => $flight,
                    'isMockData' => $flightService->isUsingMockData()
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Flight not found'
                ]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch flight status'
            ]);
        }
        break;

    case '/flights/search':
        // Search for flights (public)
        try {
            require_once '../app/services/FlightApiService.php';
            $flightService = new FlightApiService($db);
            $query = $_GET['q'] ?? $_GET['query'] ?? null;

            if (!$query) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Search query is required'
                ]);
                break;
            }

            $results = $flightService->searchFlights($query);

            echo json_encode([
                'success' => true,
                'results' => $results['results'] ?? [],
                'total' => $results['total'] ?? 0,
                'query' => $query,
                'isMockData' => $flightService->isUsingMockData()
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Flight search failed'
            ]);
        }
        break;

    case '/flights/airport':
        // Get departures/arrivals for an airport (public)
        try {
            require_once '../app/services/FlightApiService.php';
            $flightService = new FlightApiService($db);

            $airportCode = $_GET['code'] ?? 'LHR';
            $type = $_GET['type'] ?? 'departures';
            $date = $_GET['date'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

            if ($type === 'arrivals') {
                $flights = $flightService->getAirportArrivals($airportCode, $date, $limit);
            } else {
                $flights = $flightService->getAirportDepartures($airportCode, $date, $limit);
            }

            if (isset($flights['error'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $flights['error'],
                    'validCodes' => $flights['validCodes'] ?? null
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'data' => $flights,
                    'isMockData' => $flights['isMockData'] ?? $flightService->isUsingMockData()
                ]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch airport flights'
            ]);
        }
        break;

    case '/flights/airports':
        // Get list of London airports (public)
        try {
            require_once '../app/services/FlightApiService.php';
            $flightService = new FlightApiService($db);
            $airports = $flightService->getLondonAirports();

            echo json_encode([
                'success' => true,
                'airports' => $airports
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch airports'
            ]);
        }
        break;

    // =====================================================
    // LIVE PARKING BOOKING Routes
    // =====================================================
    case '/parking/live-booking':
        // Create a live parking booking (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        try {
            $tflService = new TfLParkingService();
            $tflService->setDatabase($db);

            $carParkId = $data->carParkId ?? $data->car_park_id ?? null;
            if (!$carParkId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Car park ID is required'
                ]);
                break;
            }

            $bookingDetails = [
                'vehicleRegistration' => $data->vehicleRegistration ?? $data->vehicle_registration ?? null,
                'checkInTime' => $data->checkInTime ?? $data->check_in_time ?? null,
                'checkOutTime' => $data->checkOutTime ?? $data->check_out_time ?? null,
                'totalPrice' => $data->totalPrice ?? $data->total_price ?? null,
                'specialRequests' => $data->specialRequests ?? $data->special_requests ?? null,
                'flightNumber' => $data->flightNumber ?? $data->flight_number ?? null
            ];

            $result = $tflService->createLiveBooking($_SESSION['user_id'], $carParkId, $bookingDetails);

            // Link to flight if provided
            if (!isset($result['error']) && !empty($bookingDetails['flightNumber'])) {
                require_once '../app/services/FlightApiService.php';
                $flightService = new FlightApiService($db);
                $flight = $flightService->getFlightStatus($bookingDetails['flightNumber']);
                if ($flight) {
                    $result['linkedFlight'] = $flight;
                }
            }

            if (isset($result['error'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $result['error']]);
            } else {
                echo json_encode(['success' => true, 'booking' => $result]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to create booking'
            ]);
        }
        break;

    case '/parking/live-activity':
        // Get anonymized live booking activity (public)
        // Shows recent booking activity without revealing personal details
        try {
            $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 20) : 10;
            $airport = isset($_GET['airport']) ? strtoupper($_GET['airport']) : null;

            // Query recent bookings with anonymized data
            $sql = "
                SELECT
                    b.booking_id,
                    b.booking_reference,
                    b.tfl_car_park_id,
                    b.booking_status,
                    b.check_in_time,
                    b.expected_check_out,
                    b.created_at,
                    CONCAT(SUBSTRING(b.vehicle_registration, 1, 2), '** ***') as vehicle_hint,
                    f.flight_number,
                    f.airline_name,
                    f.departure_airport,
                    f.arrival_airport,
                    f.departure_city,
                    f.arrival_city,
                    f.scheduled_departure
                FROM parking_bookings_live b
                LEFT JOIN flight_data f ON b.flight_id = f.flight_id
                WHERE b.booking_status IN ('confirmed', 'checked_in', 'active', 'completed')
                  AND b.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ";

            if ($airport) {
                $sql .= " AND (f.departure_airport = :airport OR f.arrival_airport = :airport)";
            }

            $sql .= " ORDER BY b.created_at DESC LIMIT :limit";

            $stmt = $db->prepare($sql);
            if ($airport) {
                $stmt->bindValue(':airport', $airport, PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get statistics
            $statsStmt = $db->query("
                SELECT
                    COUNT(*) as total_today,
                    SUM(CASE WHEN booking_status IN ('checked_in', 'active') THEN 1 ELSE 0 END) as currently_parked,
                    SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as upcoming
                FROM parking_bookings_live
                WHERE created_at >= CURDATE()
            ");
            $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'bookings' => $bookings,
                'stats' => [
                    'totalToday' => (int)($stats['total_today'] ?? 0),
                    'currentlyParked' => (int)($stats['currently_parked'] ?? 0),
                    'upcoming' => (int)($stats['upcoming'] ?? 0)
                ],
                'count' => count($bookings)
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch live activity'
            ]);
        }
        break;

    case '/parking/active':
        // Get user's active parking bookings (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        try {
            $tflService = new TfLParkingService();
            $tflService->setDatabase($db);
            $bookings = $tflService->getActiveBookings($_SESSION['user_id']);

            echo json_encode([
                'success' => true,
                'bookings' => $bookings,
                'count' => count($bookings)
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch active bookings'
            ]);
        }
        break;

    case '/parking/history':
        // Get user's booking history (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        try {
            $tflService = new TfLParkingService();
            $tflService->setDatabase($db);
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $history = $tflService->getBookingHistory($_SESSION['user_id'], $limit);

            echo json_encode([
                'success' => true,
                'bookings' => $history,
                'count' => count($history)
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch booking history'
            ]);
        }
        break;

    case '/parking/checkin':
        // Check in to parking (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        try {
            $tflService = new TfLParkingService();
            $tflService->setDatabase($db);

            $bookingRef = $data->bookingReference ?? $data->booking_reference ?? $data->qrCode ?? null;
            if (!$bookingRef) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Booking reference or QR code is required'
                ]);
                break;
            }

            $result = $tflService->checkIn($bookingRef);

            if (isset($result['error'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $result['error']]);
            } else {
                echo json_encode(['success' => true, 'checkIn' => $result]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Check-in failed'
            ]);
        }
        break;

    case '/parking/checkout':
        // Check out from parking (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        try {
            $tflService = new TfLParkingService();
            $tflService->setDatabase($db);

            $bookingRef = $data->bookingReference ?? $data->booking_reference ?? null;
            if (!$bookingRef) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Booking reference is required'
                ]);
                break;
            }

            $result = $tflService->checkOut($bookingRef);

            if (isset($result['error'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $result['error']]);
            } else {
                echo json_encode(['success' => true, 'checkOut' => $result]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Check-out failed'
            ]);
        }
        break;

    // =====================================================
    // PEXELS Images API Routes
    // =====================================================
    case '/pexels/images':
        // Get images from Pexels (public)
        try {
            require_once '../app/services/PexelsService.php';
            $pexelsService = new PexelsService($db);

            $query = $_GET['query'] ?? $_GET['q'] ?? null;
            $category = $_GET['category'] ?? null;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;

            if ($category) {
                $images = $pexelsService->getImagesByCategory($category, $limit);
            } elseif ($query) {
                $images = $pexelsService->searchImages($query, $limit);
            } else {
                $images = $pexelsService->getCuratedImages($limit);
            }

            echo json_encode([
                'success' => true,
                'data' => $images,
                'isMockData' => $pexelsService->isUsingMockData()
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch images'
            ]);
        }
        break;

    case '/pexels/hero':
        // Get hero image for a specific page (public)
        try {
            require_once '../app/services/PexelsService.php';
            $pexelsService = new PexelsService($db);
            $page = $_GET['page'] ?? 'home';
            $image = $pexelsService->getHeroImage($page);

            echo json_encode([
                'success' => true,
                'image' => $image,
                'isMockData' => $pexelsService->isUsingMockData()
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to fetch hero image'
            ]);
        }
        break;

    // =====================================================
    // API Status Route
    // =====================================================
    case '/api/status':
        // Get API configuration status (public)
        try {
            require_once '../config/api_keys.php';

            echo json_encode([
                'success' => true,
                'apis' => getApiStatus(),
                'timestamp' => date('c')
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to get API status'
            ]);
        }
        break;

    case '/invoice':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["error" => "Not authenticated"]);
            break;
        }
        echo json_encode(
            (new InvoiceController($db))
                ->getUserInvoice($_SESSION['user_id'])
        );
        break;

    case '/invoice/pdf':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["error" => "Not authenticated"]);
            break;
        }

        $invoice = (new InvoiceController($db))->getUserInvoice($_SESSION['user_id']);

        $pdf = new SimplePDF();
        $pdf->setFontSize(14);
        $pdf->addLine("ParkaLot - Invoice");
        $pdf->setFontSize(11);
        $pdf->addLine("User ID: " . (string)$_SESSION['user_id']);
        $pdf->addLine("Total reservations: " . (string)($invoice['count'] ?? 0));
        $pdf->addLine("Total cost (Ã‚Â£): " . number_format((float)($invoice['total'] ?? 0), 2));
        $pdf->addLine("");
        $pdf->addLine("Reservations:");
        $pdf->addLine("ID | Garage | Start -> End | Price (Ã‚Â£)");
        $pdf->addLine(str_repeat("-", 70));

        $rows = $invoice['reservations'] ?? [];
        foreach ($rows as $r) {
            $id = $r['reservation_id'] ?? '';
            $garage = $r['garage_name'] ?? ($r['garage_id'] ?? '');
            $start = $r['start_time'] ?? '';
            $end = $r['end_time'] ?? '';
            $price = isset($r['price']) ? number_format((float)$r['price'], 2) : '0.00';

            // Keep lines readable in a basic PDF (no wrapping in this minimal generator).
            $line = "{$id} | {$garage} | {$start} -> {$end} | {$price}";
            $pdf->addLine($line);
        }

        $bytes = $pdf->output();
        header_remove("Content-Type");
        header("Content-Type: application/pdf");
        header('Content-Disposition: attachment; filename="parkalot-invoice.pdf"');
        header("Content-Length: " . strlen($bytes));
        echo $bytes;
        break;

    case '/me':
        // Get current user info
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["error" => "Not authenticated"]);
            break;
        }
        $currentUser = (new AuthController($db))->getCurrentUser();
        // Add CSRF token for frontend
        $currentUser['csrf_token'] = CSRFProtection::getToken();
        echo json_encode($currentUser);
        break;

    case '/profile':
        // User profile management
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["error" => "Not authenticated"]);
            break;
        }

        $userId = $_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Get full profile
            try {
                $stmt = $db->prepare("
                    SELECT u.user_id, u.full_name, u.email, u.role, u.email_verified, u.created_at,
                           u.phone, u.address, u.city, u.postal_code,
                           u.stripe_customer_id, u.stripe_connect_id,
                           (SELECT COUNT(*) FROM customer_spaces WHERE owner_id = u.user_id) as space_count
                    FROM users u WHERE u.user_id = ?
                ");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    echo json_encode([
                        'success' => true,
                        'profile' => [
                            'user_id' => $user['user_id'],
                            'full_name' => $user['full_name'],
                            'email' => $user['email'],
                            'phone' => $user['phone'] ?? '',
                            'address' => $user['address'] ?? '',
                            'city' => $user['city'] ?? '',
                            'postal_code' => $user['postal_code'] ?? '',
                            'role' => $user['role'],
                            'email_verified' => (bool)$user['email_verified'],
                            'created_at' => $user['created_at'],
                            'is_space_owner' => $user['space_count'] > 0,
                            'has_stripe_connect' => !empty($user['stripe_connect_id'])
                        ]
                    ]);
                } else {
                    echo json_encode(['error' => 'Profile not found']);
                }
            } catch (PDOException $e) {
                echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            // Update profile
            $updates = [];
            $params = [];

            // Full name validation
            if (isset($data->full_name)) {
                $fullName = trim($data->full_name);
                if (strlen($fullName) < 2) {
                    echo json_encode(['error' => 'Full name must be at least 2 characters']);
                    break;
                }
                $updates[] = "full_name = ?";
                $params[] = $fullName;
            }

            // Phone validation (optional)
            if (isset($data->phone)) {
                $phone = trim($data->phone);
                if (!empty($phone) && !preg_match('/^[\+]?[\d\s\-\(\)]{7,20}$/', $phone)) {
                    echo json_encode(['error' => 'Invalid phone number format']);
                    break;
                }
                $updates[] = "phone = ?";
                $params[] = $phone ?: null;
            }

            // Address (optional)
            if (isset($data->address)) {
                $updates[] = "address = ?";
                $params[] = trim($data->address) ?: null;
            }

            // City (optional)
            if (isset($data->city)) {
                $updates[] = "city = ?";
                $params[] = trim($data->city) ?: null;
            }

            // Postal code (optional)
            if (isset($data->postal_code)) {
                $postalCode = trim($data->postal_code);
                if (!empty($postalCode) && !preg_match('/^[A-Za-z0-9\s\-]{3,10}$/', $postalCode)) {
                    echo json_encode(['error' => 'Invalid postal code format']);
                    break;
                }
                $updates[] = "postal_code = ?";
                $params[] = $postalCode ?: null;
            }

            if (empty($updates)) {
                echo json_encode(['error' => 'No fields to update']);
                break;
            }

            $params[] = $userId;

            try {
                $stmt = $db->prepare("UPDATE users SET " . implode(", ", $updates) . " WHERE user_id = ?");
                $stmt->execute($params);
                echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
            } catch (PDOException $e) {
                // Check if columns don't exist and provide helpful message
                if (strpos($e->getMessage(), 'Unknown column') !== false) {
                    echo json_encode(['error' => 'Profile fields not available. Please run database migration.']);
                } else {
                    echo json_encode(['error' => 'Failed to update profile']);
                }
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            // Delete account
            try {
                // Check for active bookings
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM customer_space_bookings
                    WHERE (renter_id = ? OR owner_id = ?) AND booking_status IN ('pending', 'confirmed', 'active')
                ");
                $stmt->execute([$userId, $userId]);
                if ($stmt->fetchColumn() > 0) {
                    echo json_encode(['error' => 'Cannot delete account with active bookings']);
                    break;
                }

                // Delete user (cascades will handle related data)
                $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                session_destroy();
                echo json_encode(['success' => true, 'message' => 'Account deleted']);
            } catch (PDOException $e) {
                echo json_encode(['error' => 'Failed to delete account']);
            }
        }
        break;

    case '/profile/password':
        // Change password
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["error" => "Not authenticated"]);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        if (empty($data->current_password) || empty($data->new_password)) {
            echo json_encode(['error' => 'Current and new password required']);
            break;
        }

        if (strlen($data->new_password) < 8) {
            echo json_encode(['error' => 'Password must be at least 8 characters']);
            break;
        }

        try {
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($data->current_password, $user['password_hash'])) {
                echo json_encode(['error' => 'Current password is incorrect']);
                break;
            }

            $newHash = password_hash($data->new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->execute([$newHash, $_SESSION['user_id']]);

            echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Failed to change password']);
        }
        break;

    case '/profile/stripe-status':
        // Get Stripe Connect status
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["error" => "Not authenticated"]);
            break;
        }

        try {
            $stmt = $db->prepare("SELECT stripe_connect_id FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || empty($user['stripe_connect_id'])) {
                echo json_encode(['success' => true, 'stripe_status' => null]);
                break;
            }

            // In production, would check Stripe API for account status
            // For now, assume complete if connect_id exists
            echo json_encode([
                'success' => true,
                'stripe_status' => 'complete',
                'bank_last4' => '4242' // Would come from Stripe API
            ]);
        } catch (PDOException $e) {
            echo json_encode(['error' => 'Failed to get status']);
        }
        break;

    case '/profile/stripe-onboard':
        // Start Stripe Connect onboarding
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(["error" => "Not authenticated"]);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        // In production, would create Stripe Connect account and return onboarding URL
        // For demo, return a placeholder
        echo json_encode([
            'success' => true,
            'message' => 'Stripe Connect onboarding would start here',
            'onboarding_url' => null // Would be Stripe URL in production
        ]);
        break;

    case '/csrf-token':
        // Get CSRF token (for AJAX requests)
        echo json_encode([
            'csrf_token' => CSRFProtection::getToken()
        ]);
        break;

    case '/recommendations':
        // AI-powered garage recommendations
        $userId = $_SESSION['user_id'] ?? null;
        $params = [];
        
        if (isset($_GET['location'])) {
            $params['preferred_location'] = $_GET['location'];
        }
        
        echo json_encode((new RecommendationController($db))->getRecommendations($userId, $params));
        break;

    case '/employees':
        // Employee management - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(["error" => "Access denied"]);
            break;
        }
        echo json_encode((new EmployeeController($db))->getAllEmployees());
        break;

    case '/employees/create':
        // Create employee contract - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(["error" => "Access denied"]);
            break;
        }
        echo json_encode((new EmployeeController($db))->createContract($data));
        break;

    case '/employees/update':
        // Update employee contract - Manager or Senior Employee
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['manager', 'senior_employee'])) {
            http_response_code(403);
            echo json_encode(["error" => "Access denied"]);
            break;
        }
        $contractId = $_GET['contract_id'] ?? null;
        if (!$contractId) {
            echo json_encode(["error" => "Contract ID required"]);
            break;
        }
        echo json_encode((new EmployeeController($db))->updateContract($contractId, $data));
        break;

    case '/job_application':
        // Submit job application
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(["error" => "Method not allowed"]);
            break;
        }
        
        $postData = $_POST;
        $fileData = $_FILES;
        
        echo json_encode((new JobApplicationController($db))->submitApplication($postData, $fileData));
        break;

    case '/job_applications':
        // Get all job applications - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(["error" => "Access denied"]);
            break;
        }
        echo json_encode((new JobApplicationController($db))->getAllApplications());
        break;

    case '/verify_email/send':
        // Send OTP for email verification
        // Support both authenticated users and pending verification users
        $userId = $_SESSION['user_id'] ?? $_SESSION['pending_user_id'] ?? null;
        
        if (!$userId) {
            http_response_code(401);
            echo json_encode(["error" => "Not authenticated or no pending verification"]);
            break;
        }
        
        // Get email from session or database
        $email = $_SESSION['pending_email'] ?? null;
        
        if (!$email) {
            $userDAO = DAOFactory::userDAO($db);
            $user = (new AuthController($db))->getCurrentUser();
            if (isset($user['error'])) {
                http_response_code(401);
                echo json_encode($user);
                break;
            }
            $userDetails = $userDAO->findByEmail($user['user_name']);
            $email = $userDetails['email'];
        }
        
        $emailService = new EmailVerificationService($db);
        echo json_encode($emailService->generateAndSendOTP($userId, $email));
        break;

    case '/verify_email/confirm':
        // Verify OTP code
        // Support both authenticated users and pending verification users
        $userId = $_SESSION['user_id'] ?? $_SESSION['pending_user_id'] ?? null;
        
        if (!$userId) {
            http_response_code(401);
            echo json_encode(["error" => "Not authenticated or no pending verification"]);
            break;
        }
        
        if (!isset($data->otp_code)) {
            echo json_encode(["error" => "OTP code required"]);
            break;
        }
        
        $emailService = new EmailVerificationService($db);
        $result = $emailService->verifyOTP($userId, $data->otp_code);
        
        // If verification successful and we have pending session, complete the login
        if (isset($result['success']) && $result['success'] && isset($_SESSION['pending_user_id'])) {
            $_SESSION['user_id'] = $_SESSION['pending_user_id'];
            $_SESSION['role'] = $_SESSION['pending_role'] ?? 'customer';
            $_SESSION['user_name'] = $_SESSION['pending_user_name'] ?? 'User';
            $_SESSION['last_activity'] = time();
            
            // Update last login
            $userDAO = DAOFactory::userDAO($db);
            $userDAO->updateLastLogin($_SESSION['user_id']);
            
            // Log activity
            $activityStmt = $db->prepare(
                "INSERT INTO activity_logs (user_id, role, action, description, ip_address)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $activityStmt->execute([
                $_SESSION['user_id'],
                $_SESSION['role'],
                'email_verified',
                'Email verified and logged in',
                $ipAddress
            ]);
            
            // Clear pending session variables
            unset($_SESSION['pending_user_id']);
            unset($_SESSION['pending_role']);
            unset($_SESSION['pending_user_name']);
            unset($_SESSION['pending_email']);
            
            $result['login_complete'] = true;
            $result['role'] = $_SESSION['role'];
        }
        
        echo json_encode($result);
        break;

    case '/activity_logs':
        // Get activity logs - Manager and Senior Employee only
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['manager', 'senior_employee'])) {
            http_response_code(403);
            echo json_encode(["error" => "Access denied"]);
            break;
        }
        
        $filters = [];
        if (isset($_GET['role'])) $filters['role'] = $_GET['role'];
        if (isset($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
        if (isset($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];
        
        $activityDAO = new ActivityLogDAO($db);
        $logs = $activityDAO->getActivityLogs($filters);
        
        echo json_encode(['success' => true, 'logs' => $logs, 'count' => count($logs)]);
        break;

    case '/activity_stats':
        // Get activity statistics by time interval - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(["error" => "Access denied"]);
            break;
        }
        
        $interval = $_GET['interval'] ?? 'day';
        $activityDAO = new ActivityLogDAO($db);
        $stats = $activityDAO->getActivityStatsByTimeInterval($interval);
        
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    case '/active_users':
        // Get real-time active users by role - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(["error" => "Access denied"]);
            break;
        }
        
        $timeWindow = $_GET['time_window'] ?? 30;
        $activityDAO = new ActivityLogDAO($db);
        $activeUsers = $activityDAO->getActiveUsersByRole($timeWindow);
        
        echo json_encode(['success' => true, 'active_users' => $activeUsers]);
        break;

    case '/reviews':
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Submit a new review
            if (empty($data->garage_id) || empty($data->rating) || empty($data->review_text)) {
                echo json_encode(['error' => 'Missing required fields']);
                break;
            }

            $rating = intval($data->rating);
            if ($rating < 1 || $rating > 5) {
                echo json_encode(['error' => 'Rating must be between 1 and 5']);
                break;
            }

            try {
                // Check if user already reviewed this garage
                $checkStmt = $db->prepare(
                    "SELECT review_id FROM garage_reviews WHERE user_id = ? AND garage_id = ?"
                );
                $checkStmt->execute([$_SESSION['user_id'], $data->garage_id]);

                if ($checkStmt->fetch()) {
                    // Update existing review
                    $stmt = $db->prepare(
                        "UPDATE garage_reviews SET rating = ?, review_text = ?, created_at = NOW()
                         WHERE user_id = ? AND garage_id = ?"
                    );
                    $stmt->execute([$rating, $data->review_text, $_SESSION['user_id'], $data->garage_id]);
                } else {
                    // Insert new review
                    $stmt = $db->prepare(
                        "INSERT INTO garage_reviews (user_id, garage_id, rating, review_text)
                         VALUES (?, ?, ?, ?)"
                    );
                    $stmt->execute([$_SESSION['user_id'], $data->garage_id, $rating, $data->review_text]);
                }

                // Update garage average rating
                $avgStmt = $db->prepare(
                    "UPDATE garages SET rating = (
                        SELECT AVG(rating) FROM garage_reviews WHERE garage_id = ?
                     ) WHERE garage_id = ?"
                );
                $avgStmt->execute([$data->garage_id, $data->garage_id]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Review submitted successfully'
                ]);
            } catch (Exception $e) {
                error_log("Review submission error: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to submit review']);
            }
        } else {
            // GET - Fetch user's reviews
            try {
                $stmt = $db->prepare(
                    "SELECT r.*, g.garage_name, g.location
                     FROM garage_reviews r
                     JOIN garages g ON r.garage_id = g.garage_id
                     WHERE r.user_id = ?
                     ORDER BY r.created_at DESC"
                );
                $stmt->execute([$_SESSION['user_id']]);
                $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'reviews' => $reviews,
                    'count' => count($reviews)
                ]);
            } catch (Exception $e) {
                error_log("Fetch reviews error: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to fetch reviews']);
            }
        }
        break;

    case '/reviews/garage':
        // Get all reviews for a specific garage (public endpoint)
        $garageId = $_GET['garage_id'] ?? null;

        if (!$garageId) {
            echo json_encode(['error' => 'Garage ID required']);
            break;
        }

        try {
            $stmt = $db->prepare(
                "SELECT r.rating, r.review_text, r.created_at, u.full_name as reviewer_name
                 FROM garage_reviews r
                 JOIN users u ON r.user_id = u.user_id
                 WHERE r.garage_id = ?
                 ORDER BY r.created_at DESC
                 LIMIT 20"
            );
            $stmt->execute([$garageId]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get average rating
            $avgStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as total FROM garage_reviews WHERE garage_id = ?");
            $avgStmt->execute([$garageId]);
            $stats = $avgStmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'reviews' => $reviews,
                'average_rating' => round($stats['avg_rating'] ?? 0, 1),
                'total_reviews' => $stats['total'] ?? 0
            ]);
        } catch (Exception $e) {
            error_log("Fetch garage reviews error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to fetch reviews']);
        }
        break;

    // ============================================
    // PAYMENT ENDPOINTS
    // ============================================

    case '/payments/create':
        // Record a payment
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if (empty($data->amount) || empty($data->reservation_ids)) {
            echo json_encode(['error' => 'Amount and reservation IDs required']);
            break;
        }

        try {
            $db->beginTransaction();

            // Generate transaction ID
            $transactionId = 'TXN-' . time() . '-' . rand(1000, 9999);

            // Insert payment for each reservation
            $reservationIds = is_array($data->reservation_ids) ? $data->reservation_ids : [$data->reservation_ids];

            foreach ($reservationIds as $reservationId) {
                // Get reservation amount
                $resStmt = $db->prepare("SELECT price FROM reservations WHERE reservation_id = ? AND user_id = ?");
                $resStmt->execute([$reservationId, $_SESSION['user_id']]);
                $reservation = $resStmt->fetch(PDO::FETCH_ASSOC);

                if ($reservation) {
                    $stmt = $db->prepare(
                        "INSERT INTO payments (reservation_id, user_id, amount, currency, stripe_payment_intent_id, payment_status, payment_method)
                         VALUES (?, ?, ?, 'GBP', ?, 'succeeded', ?)"
                    );
                    $stmt->execute([
                        $reservationId,
                        $_SESSION['user_id'],
                        $reservation['price'],
                        $transactionId,
                        $data->payment_method ?? 'card'
                    ]);

                    // Update reservation status to completed
                    $updateStmt = $db->prepare("UPDATE reservations SET status = 'completed' WHERE reservation_id = ?");
                    $updateStmt->execute([$reservationId]);
                }
            }

            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Payment recorded successfully',
                'transaction_id' => $transactionId,
                'items_paid' => count($reservationIds),
                'total_amount' => $data->amount
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Payment error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to record payment']);
        }
        break;

    case '/payments/history':
        // Get user's payment history
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        try {
            $stmt = $db->prepare(
                "SELECT p.*, r.start_time, r.end_time, g.garage_name
                 FROM payments p
                 JOIN reservations r ON p.reservation_id = r.reservation_id
                 JOIN garages g ON r.garage_id = g.garage_id
                 WHERE p.user_id = ?
                 ORDER BY p.created_at DESC"
            );
            $stmt->execute([$_SESSION['user_id']]);
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'payments' => $payments,
                'count' => count($payments)
            ]);
        } catch (Exception $e) {
            error_log("Payment history error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to fetch payment history']);
        }
        break;

    // ============================================
    // VEHICLE INSPECTOR ENDPOINTS
    // ============================================

    case '/vehicles/search':
        // Search vehicle by license plate
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vehicle_inspector') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. Vehicle inspector role required.']);
            break;
        }

        $plate = $_GET['plate'] ?? '';
        $plate = strtoupper(trim(str_replace(' ', '', $plate)));

        if (empty($plate)) {
            echo json_encode(['error' => 'License plate required']);
            break;
        }

        try {
            $stmt = $db->prepare(
                "SELECT v.*, u.full_name as inspector_name
                 FROM vehicles v
                 LEFT JOIN users u ON v.inspected_by = u.user_id
                 WHERE REPLACE(v.license_plate, ' ', '') = ?"
            );
            $stmt->execute([$plate]);
            $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($vehicle) {
                echo json_encode(['success' => true, 'vehicle' => $vehicle]);
            } else {
                echo json_encode(['error' => 'Vehicle not found', 'plate' => $plate]);
            }
        } catch (Exception $e) {
            error_log("Vehicle search error: " . $e->getMessage());
            echo json_encode(['error' => 'Search failed']);
        }
        break;

    case '/vehicles/fetch-dvla':
        // Fetch vehicle data from Check Car Details API and save to database
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vehicle_inspector') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied. Vehicle inspector role required.']);
            break;
        }

        $plate = $_GET['plate'] ?? '';
        $plate = strtoupper(trim(str_replace(' ', '', $plate)));

        if (empty($plate)) {
            echo json_encode(['error' => 'License plate required']);
            break;
        }

        try {
            // Check Car Details API configuration
            // Configure your API key in config/vehicle_api.php
            $apiKey = getVehicleApiKey();

            if (!$apiKey) {
                echo json_encode([
                    'error' => 'Vehicle API not configured. Please add your API key to config/vehicle_api.php',
                    'help' => 'Get an API key from https://api.checkcardetails.co.uk/'
                ]);
                break;
            }

            $apiUrl = VEHICLE_API_URL . "?apikey={$apiKey}&vrm={$plate}";

            // Initialize cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("DVLA API cURL error: " . $curlError);
                echo json_encode(['error' => 'Failed to connect to DVLA API: ' . $curlError]);
                break;
            }

            $apiData = json_decode($response, true);

            // Check if API returned valid data
            if ($httpCode !== 200 || !$apiData) {
                error_log("DVLA API error: HTTP {$httpCode}, Response: " . $response);
                echo json_encode(['error' => 'Vehicle not found in DVLA database or API error']);
                break;
            }

            // Check for API-specific error messages
            if (isset($apiData['error']) || isset($apiData['Error'])) {
                echo json_encode(['error' => $apiData['error'] ?? $apiData['Error'] ?? 'API returned an error']);
                break;
            }

            // Extract vehicle data from API response
            // The API may return data in different formats, handle common ones
            $vehicleData = $apiData['vehicle'] ?? $apiData['Vehicle'] ?? $apiData;

            // Map API response to database fields
            $make = $vehicleData['make'] ?? $vehicleData['Make'] ?? null;
            $model = $vehicleData['model'] ?? $vehicleData['Model'] ?? null;
            $colour = $vehicleData['colour'] ?? $vehicleData['Colour'] ?? $vehicleData['color'] ?? null;
            $year = $vehicleData['yearOfManufacture'] ?? $vehicleData['YearOfManufacture'] ?? $vehicleData['year'] ?? null;
            $fuelType = $vehicleData['fuelType'] ?? $vehicleData['FuelType'] ?? null;
            $engineCapacity = $vehicleData['engineCapacity'] ?? $vehicleData['EngineCapacity'] ?? null;
            $co2Emissions = $vehicleData['co2Emissions'] ?? $vehicleData['Co2Emissions'] ?? null;

            // MOT and Tax status
            $motStatus = $vehicleData['motStatus'] ?? $vehicleData['MotStatus'] ?? 'unknown';
            $motExpiry = $vehicleData['motExpiryDate'] ?? $vehicleData['MotExpiryDate'] ?? null;
            $taxStatus = $vehicleData['taxStatus'] ?? $vehicleData['TaxStatus'] ?? 'unknown';
            $taxDue = $vehicleData['taxDueDate'] ?? $vehicleData['TaxDueDate'] ?? null;

            // Normalize status values
            $motStatusNorm = 'unknown';
            if (stripos($motStatus, 'valid') !== false) $motStatusNorm = 'valid';
            else if (stripos($motStatus, 'no ') !== false || stripos($motStatus, 'expired') !== false) $motStatusNorm = 'expired';

            $taxStatusNorm = 'unknown';
            if (stripos($taxStatus, 'taxed') !== false) $taxStatusNorm = 'valid';
            else if (stripos($taxStatus, 'untaxed') !== false || stripos($taxStatus, 'sorn') !== false) $taxStatusNorm = 'expired';

            // Determine vehicle type from fuel type or body type
            $vehicleType = 'car';
            $bodyType = $vehicleData['bodyType'] ?? $vehicleData['BodyType'] ?? '';
            if (stripos($bodyType, 'motorcycle') !== false) $vehicleType = 'motorcycle';
            else if (stripos($bodyType, 'van') !== false) $vehicleType = 'van';
            else if (stripos($bodyType, 'truck') !== false || stripos($bodyType, 'lorry') !== false) $vehicleType = 'truck';

            // Format the license plate nicely (e.g., AB12CDE -> AB12 CDE)
            $formattedPlate = $plate;
            if (strlen($plate) >= 7) {
                $formattedPlate = substr($plate, 0, 4) . ' ' . substr($plate, 4);
            }

            // Check if vehicle already exists
            $checkStmt = $db->prepare("SELECT vehicle_id FROM vehicles WHERE REPLACE(license_plate, ' ', '') = ?");
            $checkStmt->execute([$plate]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                // Update existing vehicle with new API data
                $updateStmt = $db->prepare(
                    "UPDATE vehicles SET
                        make = COALESCE(?, make),
                        model = COALESCE(?, model),
                        color = COALESCE(?, color),
                        year = COALESCE(?, year),
                        vehicle_type = ?,
                        mot_status = ?,
                        mot_expiry_date = ?,
                        tax_status = ?,
                        tax_due_date = ?,
                        registration_status = 'valid',
                        last_inspection_date = NOW(),
                        inspected_by = ?,
                        notes = CONCAT(COALESCE(notes, ''), '\nUpdated from DVLA API on ', NOW())
                     WHERE vehicle_id = ?"
                );
                $updateStmt->execute([
                    $make, $model, $colour, $year, $vehicleType,
                    $motStatusNorm, $motExpiry, $taxStatusNorm, $taxDue,
                    $_SESSION['user_id'], $existing['vehicle_id']
                ]);
                $vehicleId = $existing['vehicle_id'];
            } else {
                // Insert new vehicle
                $insertStmt = $db->prepare(
                    "INSERT INTO vehicles (license_plate, make, model, color, year, vehicle_type,
                        registration_status, insurance_status, mot_status, mot_expiry_date,
                        tax_status, tax_due_date, notes, inspected_by, last_inspection_date)
                     VALUES (?, ?, ?, ?, ?, ?, 'valid', 'unknown', ?, ?, ?, ?, ?, ?, NOW())"
                );
                $insertStmt->execute([
                    $formattedPlate, $make, $model, $colour, $year, $vehicleType,
                    $motStatusNorm, $motExpiry, $taxStatusNorm, $taxDue,
                    "Imported from DVLA API. Fuel: {$fuelType}, Engine: {$engineCapacity}cc, CO2: {$co2Emissions}g/km",
                    $_SESSION['user_id']
                ]);
                $vehicleId = $db->lastInsertId();
            }

            // Fetch the saved vehicle to return
            $fetchStmt = $db->prepare(
                "SELECT v.*, u.full_name as inspector_name
                 FROM vehicles v
                 LEFT JOIN users u ON v.inspected_by = u.user_id
                 WHERE v.vehicle_id = ?"
            );
            $fetchStmt->execute([$vehicleId]);
            $savedVehicle = $fetchStmt->fetch(PDO::FETCH_ASSOC);

            // Log activity
            $logStmt = $db->prepare(
                "INSERT INTO activity_logs (user_id, role, action, description, ip_address)
                 VALUES (?, 'vehicle_inspector', 'dvla_api_fetch', ?, ?)"
            );
            $logStmt->execute([
                $_SESSION['user_id'],
                "Fetched vehicle data from DVLA API: {$formattedPlate} ({$make} {$model})",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Vehicle data fetched and saved',
                'vehicle' => $savedVehicle,
                'api_data' => $vehicleData
            ]);

        } catch (Exception $e) {
            error_log("DVLA API error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to fetch vehicle data: ' . $e->getMessage()]);
        }
        break;

    case '/vehicles/add':
        // Add new vehicle
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vehicle_inspector') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        if (empty($data->license_plate)) {
            echo json_encode(['error' => 'License plate required']);
            break;
        }

        $plate = strtoupper(trim($data->license_plate));

        try {
            // Check if vehicle already exists
            $checkStmt = $db->prepare("SELECT vehicle_id FROM vehicles WHERE license_plate = ?");
            $checkStmt->execute([$plate]);
            if ($checkStmt->fetch()) {
                echo json_encode(['error' => 'Vehicle with this license plate already exists']);
                break;
            }

            $stmt = $db->prepare(
                "INSERT INTO vehicles (license_plate, make, model, color, year, vehicle_type,
                 owner_name, owner_contact, registration_status, insurance_status,
                 mot_status, tax_status, notes, inspected_by, last_inspection_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );

            $stmt->execute([
                $plate,
                $data->make ?? null,
                $data->model ?? null,
                $data->color ?? null,
                $data->year ?? null,
                $data->vehicle_type ?? 'car',
                $data->owner_name ?? null,
                $data->owner_contact ?? null,
                $data->registration_status ?? 'unknown',
                $data->insurance_status ?? 'unknown',
                $data->mot_status ?? 'unknown',
                $data->tax_status ?? 'unknown',
                $data->notes ?? null,
                $_SESSION['user_id']
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Vehicle added successfully',
                'vehicle_id' => $db->lastInsertId()
            ]);
        } catch (Exception $e) {
            error_log("Add vehicle error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to add vehicle']);
        }
        break;

    case '/vehicles/inspect':
        // Record vehicle inspection
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vehicle_inspector') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        if (empty($data->vehicle_id)) {
            echo json_encode(['error' => 'Vehicle ID required']);
            break;
        }

        try {
            // Insert inspection record
            $stmt = $db->prepare(
                "INSERT INTO vehicle_inspections
                 (vehicle_id, inspector_id, inspection_type, inspection_result,
                  damage_detected, damage_description, location_spotted, inspection_notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $data->vehicle_id,
                $_SESSION['user_id'],
                $data->inspection_type ?? 'routine',
                $data->inspection_result ?? 'pending',
                $data->damage_detected ? 1 : 0,
                $data->damage_description ?? null,
                $data->location_spotted ?? null,
                $data->inspection_notes ?? null
            ]);

            // Update vehicle's last inspection date
            $updateStmt = $db->prepare(
                "UPDATE vehicles SET last_inspection_date = NOW(), inspected_by = ? WHERE vehicle_id = ?"
            );
            $updateStmt->execute([$_SESSION['user_id'], $data->vehicle_id]);

            echo json_encode([
                'success' => true,
                'message' => 'Inspection recorded',
                'inspection_id' => $db->lastInsertId()
            ]);
        } catch (Exception $e) {
            error_log("Inspection error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to record inspection']);
        }
        break;

    case '/vehicles/stats':
        // Get vehicle statistics
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vehicle_inspector') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            // Total vehicles
            $totalStmt = $db->query("SELECT COUNT(*) FROM vehicles");
            $total = $totalStmt->fetchColumn();

            // Valid vehicles (all statuses valid)
            $validStmt = $db->query(
                "SELECT COUNT(*) FROM vehicles
                 WHERE registration_status = 'valid'
                 AND insurance_status = 'valid'
                 AND mot_status IN ('valid', 'exempt')
                 AND tax_status IN ('valid', 'exempt')"
            );
            $valid = $validStmt->fetchColumn();

            // Expired (any status expired)
            $expiredStmt = $db->query(
                "SELECT COUNT(*) FROM vehicles
                 WHERE registration_status = 'expired'
                 OR insurance_status = 'expired'
                 OR mot_status = 'expired'
                 OR tax_status = 'expired'"
            );
            $expired = $expiredStmt->fetchColumn();

            // Expiring soon (MOT or tax due within 30 days)
            $expiringStmt = $db->query(
                "SELECT COUNT(*) FROM vehicles
                 WHERE (mot_expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                 OR (tax_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY))"
            );
            $expiring = $expiringStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'total' => (int)$total,
                'valid' => (int)$valid,
                'expired' => (int)$expired,
                'expiring' => (int)$expiring
            ]);
        } catch (Exception $e) {
            error_log("Stats error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load stats']);
        }
        break;

    case '/vehicles/inspections':
        // Get recent inspections
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vehicle_inspector') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            $stmt = $db->query(
                "SELECT vi.*, v.license_plate, v.make, v.model
                 FROM vehicle_inspections vi
                 JOIN vehicles v ON vi.vehicle_id = v.vehicle_id
                 ORDER BY vi.created_at DESC
                 LIMIT 50"
            );
            $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'inspections' => $inspections
            ]);
        } catch (Exception $e) {
            error_log("Inspections list error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load inspections']);
        }
        break;

    case '/vehicles/update':
        // Update vehicle data
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vehicle_inspector') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        if (empty($data->vehicle_id)) {
            echo json_encode(['error' => 'Vehicle ID required']);
            break;
        }

        try {
            $updates = [];
            $params = [];

            $allowedFields = ['make', 'model', 'color', 'year', 'vehicle_type',
                             'owner_name', 'owner_contact', 'registration_status',
                             'insurance_status', 'mot_status', 'mot_expiry_date',
                             'tax_status', 'tax_due_date', 'notes'];

            foreach ($allowedFields as $field) {
                if (isset($data->$field)) {
                    $updates[] = "$field = ?";
                    $params[] = $data->$field;
                }
            }

            if (empty($updates)) {
                echo json_encode(['error' => 'No fields to update']);
                break;
            }

            $params[] = $data->vehicle_id;
            $sql = "UPDATE vehicles SET " . implode(", ", $updates) . " WHERE vehicle_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => 'Vehicle updated']);
        } catch (Exception $e) {
            error_log("Update vehicle error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to update vehicle']);
        }
        break;

    case '/vehicles/link-user':
        // Link vehicle to user
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'vehicle_inspector') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        if (empty($data->user_id) || empty($data->vehicle_id)) {
            echo json_encode(['error' => 'User ID and Vehicle ID required']);
            break;
        }

        try {
            $stmt = $db->prepare(
                "INSERT INTO user_vehicles (user_id, vehicle_id, ownership_type, verified, verified_by, verified_at)
                 VALUES (?, ?, ?, TRUE, ?, NOW())
                 ON DUPLICATE KEY UPDATE ownership_type = ?, verified = TRUE, verified_by = ?, verified_at = NOW()"
            );

            $ownershipType = $data->ownership_type ?? 'owner';
            $stmt->execute([
                $data->user_id,
                $data->vehicle_id,
                $ownershipType,
                $_SESSION['user_id'],
                $ownershipType,
                $_SESSION['user_id']
            ]);

            echo json_encode(['success' => true, 'message' => 'Vehicle linked to user']);
        } catch (Exception $e) {
            error_log("Link vehicle error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to link vehicle']);
        }
        break;

    case '/employee/details':
        // Get employee's own details including current shift
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        try {
            $stmt = $db->prepare(
                "SELECT ec.department, ec.position, ec.contract_status,
                        up.current_shift, up.operation_status, up.max_hours
                 FROM users u
                 LEFT JOIN employee_contracts ec ON u.user_id = ec.user_id
                 LEFT JOIN user_preferences up ON u.user_id = up.user_id
                 WHERE u.user_id = ?
                 LIMIT 1"
            );
            $stmt->execute([$_SESSION['user_id']]);
            $details = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get active garages count
            $garagesStmt = $db->query("SELECT COUNT(*) FROM garages");
            $activeGarages = $garagesStmt->fetchColumn();

            // Get completed tasks count for this user
            $tasksStmt = $db->prepare(
                "SELECT COUNT(*) FROM user_preferences WHERE user_id = ? AND operation_status = 'completed'"
            );
            $tasksStmt->execute([$_SESSION['user_id']]);
            $tasksCompleted = $tasksStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'department' => $details['department'] ?? null,
                'position' => $details['position'] ?? null,
                'current_shift' => $details['current_shift'] ?? 'Day Shift',
                'operation_status' => $details['operation_status'] ?? 'incomplete',
                'max_hours' => $details['max_hours'] ?? null,
                'active_garages' => (int)$activeGarages,
                'tasks_completed' => (int)$tasksCompleted
            ]);
        } catch (Exception $e) {
            error_log("Employee details error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load details']);
        }
        break;

    case '/logout':
        session_destroy();
        echo json_encode(["success" => true, "message" => "Logged out successfully"]);
        break;

    // ============================================
    // MANAGER DASHBOARD ENDPOINTS
    // ============================================

    case '/manager/stats':
        // Get comprehensive dashboard statistics - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            // Total revenue from payments
            $revenueStmt = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE payment_status = 'succeeded'");
            $totalRevenue = $revenueStmt->fetchColumn();

            // Active employees (with active contracts)
            $activeEmpStmt = $db->query("SELECT COUNT(DISTINCT user_id) FROM employee_contracts WHERE contract_status = 'active'");
            $activeEmployees = $activeEmpStmt->fetchColumn();

            // Total reservations
            $reservationsStmt = $db->query("SELECT COUNT(*) FROM reservations");
            $totalReservations = $reservationsStmt->fetchColumn();

            // Total users (customers)
            $usersStmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'");
            $totalUsers = $usersStmt->fetchColumn();

            // Task counts from user_preferences
            $completedStmt = $db->query("SELECT COUNT(*) FROM user_preferences WHERE operation_status = 'completed'");
            $completedTasks = $completedStmt->fetchColumn();

            $incompleteStmt = $db->query("SELECT COUNT(*) FROM user_preferences WHERE operation_status = 'incomplete'");
            $incompleteTasks = $incompleteStmt->fetchColumn();

            $breakStmt = $db->query("SELECT COUNT(*) FROM user_preferences WHERE operation_status = 'breaktime'");
            $onBreak = $breakStmt->fetchColumn();

            // Vehicles inspected
            $vehiclesStmt = $db->query("SELECT COUNT(*) FROM vehicle_inspections");
            $vehiclesInspected = $vehiclesStmt->fetchColumn();

            // Pending job applications
            $appsStmt = $db->query("SELECT COUNT(*) FROM job_applications WHERE status = 'pending'");
            $pendingApplications = $appsStmt->fetchColumn();

            // Active garages
            $garagesStmt = $db->query("SELECT COUNT(*) FROM garages");
            $activeGarages = $garagesStmt->fetchColumn();

            echo json_encode([
                'success' => true,
                'total_revenue' => $totalRevenue,
                'active_employees' => $activeEmployees,
                'total_reservations' => $totalReservations,
                'total_users' => $totalUsers,
                'completed_tasks' => $completedTasks,
                'incomplete_tasks' => $incompleteTasks,
                'on_break' => $onBreak,
                'vehicles_inspected' => $vehiclesInspected,
                'pending_applications' => $pendingApplications,
                'active_garages' => $activeGarages
            ]);
        } catch (Exception $e) {
            error_log("Manager stats error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load statistics']);
        }
        break;

    case '/manager/employees':
        // Get all employees with contract details - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            $stmt = $db->query(
                "SELECT u.user_id, u.full_name, u.email, u.role, u.created_at,
                        ec.department, ec.position, ec.salary, ec.contract_status, ec.start_date, ec.end_date,
                        up.max_hours, up.operation_status, up.current_shift
                 FROM users u
                 LEFT JOIN employee_contracts ec ON u.user_id = ec.user_id
                 LEFT JOIN user_preferences up ON u.user_id = up.user_id
                 WHERE u.role IN ('employee', 'senior_employee')
                 ORDER BY u.created_at DESC"
            );
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'employees' => $employees]);
        } catch (Exception $e) {
            error_log("Load employees error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load employees']);
        }
        break;

    case '/manager/employees/add':
        // Add new employee - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        if (empty($data->full_name) || empty($data->email) || empty($data->password) || empty($data->role)) {
            echo json_encode(['error' => 'Missing required fields']);
            break;
        }

        try {
            $db->beginTransaction();

            // Check if email exists
            $checkStmt = $db->prepare("SELECT user_id FROM users WHERE email = ?");
            $checkStmt->execute([$data->email]);
            if ($checkStmt->fetch()) {
                echo json_encode(['error' => 'Email already exists']);
                $db->rollBack();
                break;
            }

            // Insert user
            $passwordHash = password_hash($data->password, PASSWORD_DEFAULT);
            $stmt = $db->prepare(
                "INSERT INTO users (full_name, email, password_hash, role, email_verified)
                 VALUES (?, ?, ?, ?, TRUE)"
            );
            $stmt->execute([$data->full_name, $data->email, $passwordHash, $data->role]);
            $userId = $db->lastInsertId();

            // Create contract if department and position provided
            if (!empty($data->department) || !empty($data->position)) {
                $contractStmt = $db->prepare(
                    "INSERT INTO employee_contracts (user_id, employee_type, department, position, contract_status, start_date, hire_date)
                     VALUES (?, ?, ?, ?, 'active', CURDATE(), CURDATE())"
                );
                $contractStmt->execute([
                    $userId,
                    $data->role, // employee or senior_employee
                    $data->department ?? null,
                    $data->position ?? null
                ]);
            }

            // Create user preferences - try with operation_status, fallback to basic insert
            try {
                $prefStmt = $db->prepare(
                    "INSERT INTO user_preferences (user_id, operation_status, current_shift) VALUES (?, 'incomplete', 'Day Shift')"
                );
                $prefStmt->execute([$userId]);
            } catch (PDOException $prefEx) {
                // If operation_status column doesn't exist, try basic insert
                error_log("Preference insert with operation_status failed, trying basic insert: " . $prefEx->getMessage());
                $prefStmt = $db->prepare(
                    "INSERT INTO user_preferences (user_id) VALUES (?)"
                );
                $prefStmt->execute([$userId]);
            }

            // Log activity
            $logStmt = $db->prepare(
                "INSERT INTO activity_logs (user_id, role, action, description, ip_address)
                 VALUES (?, 'manager', 'employee_created', ?, ?)"
            );
            $logStmt->execute([
                $_SESSION['user_id'],
                "Created employee: {$data->full_name}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Employee created', 'user_id' => $userId]);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Add employee error: " . $e->getMessage());
            error_log("Add employee stack trace: " . $e->getTraceAsString());
            // Return more detailed error in development
            $errorMsg = 'Failed to add employee';
            if (strpos($e->getMessage(), 'Unknown column') !== false) {
                $errorMsg = 'Database schema needs to be updated. Please run database_manager_schema.sql';
            } else if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errorMsg = 'An employee with this email already exists';
            }
            echo json_encode(['error' => $errorMsg, 'debug' => $e->getMessage()]);
        }
        break;

    case '/manager/employees/update':
        // Update employee - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        if (empty($data->user_id)) {
            echo json_encode(['error' => 'User ID required']);
            break;
        }

        try {
            $db->beginTransaction();

            // Update users table
            $userUpdates = [];
            $userParams = [];

            if (!empty($data->full_name)) {
                $userUpdates[] = "full_name = ?";
                $userParams[] = $data->full_name;
            }
            if (!empty($data->email)) {
                $userUpdates[] = "email = ?";
                $userParams[] = $data->email;
            }
            if (!empty($data->role)) {
                $userUpdates[] = "role = ?";
                $userParams[] = $data->role;
            }

            if (!empty($userUpdates)) {
                $userParams[] = $data->user_id;
                $sql = "UPDATE users SET " . implode(", ", $userUpdates) . " WHERE user_id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute($userParams);
            }

            // Update or insert employee_contracts
            $checkContract = $db->prepare("SELECT contract_id FROM employee_contracts WHERE user_id = ?");
            $checkContract->execute([$data->user_id]);

            if ($checkContract->fetch()) {
                // Update existing contract
                $contractUpdates = [];
                $contractParams = [];

                if (isset($data->department)) {
                    $contractUpdates[] = "department = ?";
                    $contractParams[] = $data->department;
                }
                if (isset($data->position)) {
                    $contractUpdates[] = "position = ?";
                    $contractParams[] = $data->position;
                }

                if (!empty($contractUpdates)) {
                    $contractParams[] = $data->user_id;
                    $sql = "UPDATE employee_contracts SET " . implode(", ", $contractUpdates) . " WHERE user_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute($contractParams);
                }
            } else if (!empty($data->department) || !empty($data->position)) {
                // Insert new contract - get role from users table
                $roleStmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
                $roleStmt->execute([$data->user_id]);
                $userRole = $roleStmt->fetchColumn() ?: 'employee';

                $stmt = $db->prepare(
                    "INSERT INTO employee_contracts (user_id, employee_type, department, position, contract_status, start_date)
                     VALUES (?, ?, ?, ?, 'active', CURDATE())"
                );
                $stmt->execute([$data->user_id, $userRole, $data->department ?? null, $data->position ?? null]);
            }

            // Update or insert user_preferences
            $checkPref = $db->prepare("SELECT preference_id FROM user_preferences WHERE user_id = ?");
            $checkPref->execute([$data->user_id]);

            if ($checkPref->fetch()) {
                $prefUpdates = [];
                $prefParams = [];

                if (isset($data->max_hours)) {
                    $prefUpdates[] = "max_hours = ?";
                    $prefParams[] = $data->max_hours;
                }
                if (isset($data->operation_status)) {
                    $prefUpdates[] = "operation_status = ?";
                    $prefParams[] = $data->operation_status;
                }
                if (isset($data->current_shift)) {
                    $prefUpdates[] = "current_shift = ?";
                    $prefParams[] = $data->current_shift;
                }

                if (!empty($prefUpdates)) {
                    $prefParams[] = $data->user_id;
                    $sql = "UPDATE user_preferences SET " . implode(", ", $prefUpdates) . " WHERE user_id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute($prefParams);
                }
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO user_preferences (user_id, max_hours, operation_status, current_shift) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([
                    $data->user_id,
                    $data->max_hours ?? null,
                    $data->operation_status ?? 'incomplete',
                    $data->current_shift ?? 'Day Shift'
                ]);
            }

            // Log activity
            $logStmt = $db->prepare(
                "INSERT INTO activity_logs (user_id, role, action, description, ip_address)
                 VALUES (?, 'manager', 'employee_updated', ?, ?)"
            );
            $logStmt->execute([
                $_SESSION['user_id'],
                "Updated employee ID: {$data->user_id}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Employee updated']);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Update employee error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to update employee']);
        }
        break;

    case '/manager/employees/delete':
        // Delete employee - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        if (empty($data->user_id)) {
            echo json_encode(['error' => 'User ID required']);
            break;
        }

        try {
            $db->beginTransaction();

            // Delete related records first
            $db->prepare("DELETE FROM user_preferences WHERE user_id = ?")->execute([$data->user_id]);
            $db->prepare("DELETE FROM employee_contracts WHERE user_id = ?")->execute([$data->user_id]);

            // Delete user
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ? AND role IN ('employee', 'senior_employee')");
            $stmt->execute([$data->user_id]);

            if ($stmt->rowCount() === 0) {
                $db->rollBack();
                echo json_encode(['error' => 'Employee not found or cannot be deleted']);
                break;
            }

            // Log activity
            $logStmt = $db->prepare(
                "INSERT INTO activity_logs (user_id, role, action, description, ip_address)
                 VALUES (?, 'manager', 'employee_deleted', ?, ?)"
            );
            $logStmt->execute([
                $_SESSION['user_id'],
                "Deleted employee ID: {$data->user_id}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            $db->commit();
            echo json_encode(['success' => true, 'message' => 'Employee deleted']);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Delete employee error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to delete employee']);
        }
        break;

    case '/manager/contracts':
        // Get all contracts - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            $stmt = $db->query(
                "SELECT ec.*, u.full_name, u.email, u.role
                 FROM employee_contracts ec
                 JOIN users u ON ec.user_id = u.user_id
                 ORDER BY ec.created_at DESC"
            );
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'contracts' => $contracts]);
        } catch (Exception $e) {
            error_log("Load contracts error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load contracts']);
        }
        break;

    case '/manager/analytics':
        // Get analytics data for charts - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            // Revenue by month (last 6 months)
            $revenueStmt = $db->query(
                "SELECT DATE_FORMAT(MIN(created_at), '%b') as month, SUM(amount) as total
                 FROM payments
                 WHERE payment_status = 'succeeded'
                 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                 GROUP BY YEAR(created_at), MONTH(created_at)
                 ORDER BY MIN(created_at)"
            );
            $revenueData = $revenueStmt->fetchAll(PDO::FETCH_ASSOC);
            $revenueLabelsRaw = array_column($revenueData, 'month');
            $revenueValuesRaw = array_column($revenueData, 'total');

            // Ensure we have valid arrays with numeric data
            $revenueLabels = !empty($revenueLabelsRaw) ? array_values($revenueLabelsRaw) : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
            $revenueValues = !empty($revenueValuesRaw) ? array_map('floatval', array_values($revenueValuesRaw)) : [0.0, 0.0, 0.0, 0.0, 0.0, 0.0];

            // Reservation status counts - ensure integers
            $activeRes = (int)$db->query("SELECT COUNT(*) FROM reservations WHERE status = 'active'")->fetchColumn();
            $completedRes = (int)$db->query("SELECT COUNT(*) FROM reservations WHERE status = 'completed'")->fetchColumn();
            $cancelledRes = (int)$db->query("SELECT COUNT(*) FROM reservations WHERE status = 'cancelled'")->fetchColumn();

            // Vehicle inspections by type
            $vehicleStmt = $db->query(
                "SELECT inspection_type, COUNT(*) as count
                 FROM vehicle_inspections
                 GROUP BY inspection_type"
            );
            $vehicleData = $vehicleStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $vehicleLabels = ['routine', 'entry', 'exit', 'damage', 'random'];
            $vehicleValues = array_map(function($type) use ($vehicleData) {
                return (int)($vehicleData[$type] ?? 0);
            }, $vehicleLabels);

            // Department distribution
            $deptStmt = $db->query(
                "SELECT department, COUNT(*) as count
                 FROM employee_contracts
                 WHERE department IS NOT NULL
                 GROUP BY department"
            );
            $deptData = $deptStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $deptLabels = ['management', 'operations', 'customer_service', 'software_systems'];
            $deptValues = array_map(function($dept) use ($deptData) {
                return (int)($deptData[$dept] ?? 0);
            }, $deptLabels);

            // Reports by type
            $reportsStmt = $db->query(
                "SELECT report_type, COUNT(*) as count
                 FROM reports
                 GROUP BY report_type"
            );
            $reportsData = $reportsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $reportLabels = ['daily', 'weekly', 'monthly'];
            $reportValues = array_map(function($type) use ($reportsData) {
                return (int)($reportsData[$type] ?? 0);
            }, $reportLabels);

            echo json_encode([
                'success' => true,
                'revenue_labels' => $revenueLabels,
                'revenue_data' => array_values($revenueValues),
                'reservation_status' => [$activeRes, $completedRes, $cancelledRes],
                'vehicle_labels' => ['Routine', 'Entry', 'Exit', 'Damage', 'Random'],
                'vehicle_data' => array_values($vehicleValues),
                'department_labels' => ['Management', 'Operations', 'Customer Service', 'Software Systems'],
                'department_data' => array_values($deptValues),
                'report_labels' => ['Daily', 'Weekly', 'Monthly'],
                'report_data' => array_values($reportValues)
            ]);
        } catch (Exception $e) {
            error_log("Analytics error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to load analytics: ' . $e->getMessage()]);
        }
        break;

    // ============================================
    // SENIOR EMPLOYEE DASHBOARD ENDPOINTS
    // ============================================

    case '/senior/info':
        // Get senior employee info including department
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_employee') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            $stmt = $db->prepare(
                "SELECT ec.department, ec.position
                 FROM employee_contracts ec
                 WHERE ec.user_id = ? AND ec.contract_status = 'active'
                 LIMIT 1"
            );
            $stmt->execute([$_SESSION['user_id']]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'department' => $info['department'] ?? null,
                'position' => $info['position'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Senior info error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load info']);
        }
        break;

    case '/senior/stats':
        // Get senior employee dashboard stats
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_employee') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            // Get department
            $deptStmt = $db->prepare(
                "SELECT department FROM employee_contracts WHERE user_id = ? AND contract_status = 'active' LIMIT 1"
            );
            $deptStmt->execute([$_SESSION['user_id']]);
            $dept = $deptStmt->fetchColumn();

            // Team members in same department
            $teamStmt = $db->prepare(
                "SELECT COUNT(*) FROM employee_contracts WHERE department = ? AND user_id != ? AND contract_status = 'active'"
            );
            $teamStmt->execute([$dept, $_SESSION['user_id']]);
            $teamMembers = $teamStmt->fetchColumn();

            // Task stats from user_preferences
            $completedStmt = $db->prepare(
                "SELECT COUNT(*) FROM user_preferences up
                 JOIN employee_contracts ec ON up.user_id = ec.user_id
                 WHERE ec.department = ? AND up.operation_status = 'completed'"
            );
            $completedStmt->execute([$dept]);
            $completedTasks = $completedStmt->fetchColumn();

            $incompleteStmt = $db->prepare(
                "SELECT COUNT(*) FROM user_preferences up
                 JOIN employee_contracts ec ON up.user_id = ec.user_id
                 WHERE ec.department = ? AND up.operation_status = 'incomplete'"
            );
            $incompleteStmt->execute([$dept]);
            $incompleteTasks = $incompleteStmt->fetchColumn();

            $breakStmt = $db->prepare(
                "SELECT COUNT(*) FROM user_preferences up
                 JOIN employee_contracts ec ON up.user_id = ec.user_id
                 WHERE ec.department = ? AND up.operation_status = 'breaktime'"
            );
            $breakStmt->execute([$dept]);
            $onBreak = $breakStmt->fetchColumn();

            $totalTasks = $completedTasks + $incompleteTasks + $onBreak;

            // Garage stats
            $garagesStmt = $db->query("SELECT COUNT(*) FROM garages");
            $garages = $garagesStmt->fetchColumn();

            // Average rating from garage_reviews
            $ratingStmt = $db->query("SELECT AVG(rating) FROM garage_reviews");
            $avgRating = $ratingStmt->fetchColumn();

            // Positive feedback (4+ stars)
            $posStmt = $db->query("SELECT COUNT(*) FROM garage_reviews WHERE rating >= 4");
            $posCount = $posStmt->fetchColumn();
            $totalReviews = $db->query("SELECT COUNT(*) FROM garage_reviews")->fetchColumn();
            $positiveFeedback = $totalReviews > 0 ? round(($posCount / $totalReviews) * 100) : 0;

            echo json_encode([
                'success' => true,
                'team_members' => (int)$teamMembers,
                'completed_tasks' => (int)$completedTasks,
                'incomplete_tasks' => (int)$incompleteTasks,
                'on_break' => (int)$onBreak,
                'total_tasks' => (int)$totalTasks,
                'garages_monitored' => (int)$garages,
                'occupancy' => 0,
                'issues_resolved' => 0,
                'avg_rating' => round((float)$avgRating, 1),
                'positive_feedback' => $positiveFeedback,
                'response_time' => 0
            ]);
        } catch (Exception $e) {
            error_log("Senior stats error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load stats']);
        }
        break;

    case '/senior/team':
        // Get team members in same department
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_employee') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            // Get department
            $deptStmt = $db->prepare(
                "SELECT department FROM employee_contracts WHERE user_id = ? AND contract_status = 'active' LIMIT 1"
            );
            $deptStmt->execute([$_SESSION['user_id']]);
            $dept = $deptStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT u.user_id, u.full_name, u.email,
                        ec.position, ec.contract_status, ec.salary,
                        up.operation_status, up.rating,
                        (SELECT COUNT(*) FROM user_preferences WHERE user_id = u.user_id AND operation_status = 'completed') as completed_tasks,
                        (SELECT COUNT(*) FROM user_preferences WHERE user_id = u.user_id) as total_tasks
                 FROM users u
                 JOIN employee_contracts ec ON u.user_id = ec.user_id
                 LEFT JOIN user_preferences up ON u.user_id = up.user_id
                 WHERE ec.department = ? AND u.user_id != ? AND u.role = 'employee'
                 ORDER BY u.full_name"
            );
            $stmt->execute([$dept, $_SESSION['user_id']]);
            $team = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate performance score
            foreach ($team as &$member) {
                $member['performance_score'] = $member['rating'] ? ($member['rating'] * 20) : 0;
            }

            echo json_encode(['success' => true, 'team' => $team]);
        } catch (Exception $e) {
            error_log("Senior team error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load team']);
        }
        break;

    case '/senior/contracts':
        // Get contracts for employees in same department
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_employee') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            // Get department
            $deptStmt = $db->prepare(
                "SELECT department FROM employee_contracts WHERE user_id = ? AND contract_status = 'active' LIMIT 1"
            );
            $deptStmt->execute([$_SESSION['user_id']]);
            $dept = $deptStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT ec.*, u.full_name, u.email
                 FROM employee_contracts ec
                 JOIN users u ON ec.user_id = u.user_id
                 WHERE ec.department = ?
                 ORDER BY ec.created_at DESC"
            );
            $stmt->execute([$dept]);
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'contracts' => $contracts]);
        } catch (Exception $e) {
            error_log("Senior contracts error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load contracts']);
        }
        break;

    case '/senior/rate':
        // Rate employee performance
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_employee') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        if (empty($data->user_id) || empty($data->rating)) {
            echo json_encode(['error' => 'User ID and rating required']);
            break;
        }

        $rating = intval($data->rating);
        if ($rating < 1 || $rating > 5) {
            echo json_encode(['error' => 'Rating must be between 1 and 5']);
            break;
        }

        try {
            // Check if user_preferences exists
            $checkStmt = $db->prepare("SELECT preference_id FROM user_preferences WHERE user_id = ?");
            $checkStmt->execute([$data->user_id]);

            if ($checkStmt->fetch()) {
                $stmt = $db->prepare("UPDATE user_preferences SET rating = ? WHERE user_id = ?");
                $stmt->execute([$rating, $data->user_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO user_preferences (user_id, rating) VALUES (?, ?)");
                $stmt->execute([$data->user_id, $rating]);
            }

            echo json_encode(['success' => true, 'message' => 'Rating updated']);
        } catch (Exception $e) {
            error_log("Rate employee error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to update rating']);
        }
        break;

    case '/senior/update-status':
        // Update employee operation status
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_employee') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        if (empty($data->user_id) || empty($data->operation_status)) {
            echo json_encode(['error' => 'User ID and operation status required']);
            break;
        }

        if (!in_array($data->operation_status, ['completed', 'incomplete', 'breaktime'])) {
            echo json_encode(['error' => 'Invalid operation status']);
            break;
        }

        try {
            // Check if user_preferences exists
            $checkStmt = $db->prepare("SELECT preference_id FROM user_preferences WHERE user_id = ?");
            $checkStmt->execute([$data->user_id]);

            if ($checkStmt->fetch()) {
                $stmt = $db->prepare("UPDATE user_preferences SET operation_status = ? WHERE user_id = ?");
                $stmt->execute([$data->operation_status, $data->user_id]);
            } else {
                $stmt = $db->prepare("INSERT INTO user_preferences (user_id, operation_status) VALUES (?, ?)");
                $stmt->execute([$data->user_id, $data->operation_status]);
            }

            echo json_encode(['success' => true, 'message' => 'Status updated']);
        } catch (Exception $e) {
            error_log("Update status error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to update status']);
        }
        break;

    case '/senior/reports':
        // Get or fetch reports
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_employee') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        try {
            if (isset($_GET['report_id'])) {
                // Get specific report
                $stmt = $db->prepare(
                    "SELECT * FROM reports WHERE report_id = ? AND author_id = ?"
                );
                $stmt->execute([$_GET['report_id'], $_SESSION['user_id']]);
                $report = $stmt->fetch(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'report' => $report]);
            } else {
                // Get all reports by this user
                $stmt = $db->prepare(
                    "SELECT report_id, title, report_type, created_at
                     FROM reports
                     WHERE author_id = ?
                     ORDER BY created_at DESC
                     LIMIT 50"
                );
                $stmt->execute([$_SESSION['user_id']]);
                $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'reports' => $reports]);
            }
        } catch (Exception $e) {
            error_log("Reports error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to load reports']);
        }
        break;

    case '/senior/reports/save':
        // Save a report
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'senior_employee') {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            break;
        }

        if (empty($data->title) || empty($data->content) || empty($data->report_type)) {
            echo json_encode(['error' => 'Title, content and report type required']);
            break;
        }

        try {
            // Get department
            $deptStmt = $db->prepare(
                "SELECT department FROM employee_contracts WHERE user_id = ? LIMIT 1"
            );
            $deptStmt->execute([$_SESSION['user_id']]);
            $dept = $deptStmt->fetchColumn();

            $stmt = $db->prepare(
                "INSERT INTO reports (author_id, department, employee_id, report_type, title, content)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $_SESSION['user_id'],
                $dept,
                $data->employee_id ?? null,
                $data->report_type,
                $data->title,
                $data->content
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Report saved',
                'report_id' => $db->lastInsertId()
            ]);
        } catch (Exception $e) {
            error_log("Save report error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to save report']);
        }
        break;

    case '/job_applications/update':
        // Update job application status - Manager only
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }

        // Validate input - use isset to handle 0 values correctly
        if (!isset($data->application_id) || !isset($data->status)) {
            echo json_encode(['success' => false, 'error' => 'Application ID and status required']);
            break;
        }

        $applicationId = (int)$data->application_id;
        $status = trim($data->status);

        if ($applicationId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid application ID']);
            break;
        }

        // Validate status matches ENUM values in database
        $validStatuses = ['pending', 'reviewing', 'interviewed', 'accepted', 'rejected'];
        if (!in_array($status, $validStatuses)) {
            echo json_encode(['success' => false, 'error' => 'Invalid status. Must be one of: ' . implode(', ', $validStatuses)]);
            break;
        }

        try {
            // Check if application exists
            $checkStmt = $db->prepare("SELECT application_id FROM job_applications WHERE application_id = ?");
            $checkStmt->execute([$applicationId]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Application not found']);
                break;
            }

            $stmt = $db->prepare("UPDATE job_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ?");
            $stmt->execute([$status, $_SESSION['user_id'], $applicationId]);

            // Log activity
            $logStmt = $db->prepare(
                "INSERT INTO activity_logs (user_id, role, action, description, ip_address)
                 VALUES (?, 'manager', 'application_status_updated', ?, ?)"
            );
            $logStmt->execute([
                $_SESSION['user_id'],
                "Updated application ID {$applicationId} to {$status}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            echo json_encode(['success' => true, 'message' => 'Application status updated']);
        } catch (Exception $e) {
            error_log("Update application error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Failed to update application: ' . $e->getMessage()]);
        }
        break;

    // ============================================
    // PUBLIC VEHICLE CHECK ENDPOINT
    // ============================================

    case '/vehicles/public-check':
        // Public endpoint to check vehicle details (no auth required)
        $plate = $_GET['plate'] ?? '';
        $plate = strtoupper(trim(str_replace(' ', '', $plate)));

        if (empty($plate) || strlen($plate) < 2) {
            echo json_encode(['error' => 'Please enter a valid license plate']);
            break;
        }

        try {
            // Check Car Details API configuration
            $apiKey = getVehicleApiKey();

            if (!$apiKey) {
                echo json_encode([
                    'error' => 'Vehicle lookup service is not configured.',
                    'help' => 'Please contact the administrator.'
                ]);
                break;
            }

            $apiUrl = VEHICLE_API_URL . "?apikey={$apiKey}&vrm={$plate}";

            // Initialize cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                error_log("Public vehicle check cURL error: " . $curlError);
                echo json_encode(['error' => 'Unable to connect to vehicle database']);
                break;
            }

            $apiData = json_decode($response, true);

            // Check if API returned valid data
            if ($httpCode !== 200 || !$apiData) {
                echo json_encode(['error' => 'Vehicle not found. Please check the plate number.']);
                break;
            }

            // Check for API-specific error messages
            if (isset($apiData['message']) && strpos($apiData['message'], 'not found') !== false) {
                echo json_encode(['error' => 'Vehicle not found in DVLA database']);
                break;
            }

            if (isset($apiData['error']) || isset($apiData['Error'])) {
                echo json_encode(['error' => 'Vehicle not found']);
                break;
            }

            // Return vehicle data
            echo json_encode([
                'success' => true,
                'vehicle' => $apiData
            ]);

        } catch (Exception $e) {
            error_log("Public vehicle check error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to check vehicle']);
        }
        break;

    // ============================================
    // PASSWORD RESET ENDPOINTS
    // ============================================

    case '/password/request-reset':
        // Request password reset - send OTP to email
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        if (empty($data->email)) {
            echo json_encode(['error' => 'Email is required']);
            break;
        }

        $email = strtolower(trim($data->email));

        try {
            // Check if user exists
            $userDAO = DAOFactory::userDAO($db);
            $user = $userDAO->findByEmail($email);

            if (!$user) {
                // Don't reveal if email exists for security
                echo json_encode([
                    'success' => true,
                    'message' => 'If an account with that email exists, a reset code has been sent.'
                ]);
                break;
            }

            // Check rate limiting (max 3 attempts in 5 minutes)
            $rateLimitStmt = $db->prepare("
                SELECT COUNT(*) as attempts
                FROM password_resets
                WHERE email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $rateLimitStmt->execute([$email]);
            $rateLimit = $rateLimitStmt->fetch(PDO::FETCH_ASSOC);

            if ($rateLimit && $rateLimit['attempts'] >= 3) {
                echo json_encode(['error' => 'Too many reset requests. Please try again in 5 minutes.']);
                break;
            }

            // Generate 6-digit OTP
            $otpCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            // Store password reset request
            $insertStmt = $db->prepare("
                INSERT INTO password_resets (user_id, email, otp_code, expires_at)
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->execute([$user['user_id'], $email, $otpCode, $expiresAt]);

            // Try to send email, but don't fail if it doesn't work
            $emailSent = false;

            try {
                // Check if PHPMailer exists
                $phpmailerPath = __DIR__ . '/../vendor/phpmailer/PHPMailer.php';
                $emailConfigPath = __DIR__ . '/../config/email_config.php';

                if (file_exists($phpmailerPath) && file_exists($emailConfigPath)) {
                    require_once $phpmailerPath;
                    require_once __DIR__ . '/../vendor/phpmailer/SMTP.php';
                    require_once __DIR__ . '/../vendor/phpmailer/Exception.php';
                    require_once $emailConfigPath;

                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

                    $mail->isSMTP();
                    $mail->Host = EmailConfig::SMTP_HOST;
                    $mail->SMTPAuth = true;
                    $mail->Username = EmailConfig::SMTP_USERNAME;
                    $mail->Password = EmailConfig::SMTP_PASSWORD;
                    $mail->SMTPSecure = EmailConfig::SMTP_SECURE;
                    $mail->Port = EmailConfig::SMTP_PORT;
                    $mail->CharSet = EmailConfig::CHARSET;

                    $mail->setFrom(EmailConfig::FROM_EMAIL, EmailConfig::FROM_NAME);
                    $mail->addAddress($email, $user['full_name']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset - ParkaLot';
                    $mail->Body = "
                    <html>
                    <body style='font-family: Arial, sans-serif;'>
                        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                            <h2 style='color: #e74c3c;'>Password Reset Request</h2>
                            <p>Hello {$user['full_name']},</p>
                            <p>We received a request to reset your password. Use the code below:</p>
                            <div style='font-size: 32px; font-weight: bold; color: #2c3e50; text-align: center; padding: 20px; background: #f9f9f9; border: 2px dashed #3498db; letter-spacing: 5px; margin: 20px 0;'>
                                {$otpCode}
                            </div>
                            <p><strong>This code expires in 10 minutes.</strong></p>
                            <p style='color: #999;'>If you didn't request this, please ignore this email.</p>
                            <p>Best regards,<br>ParkaLot Team</p>
                        </div>
                    </body>
                    </html>";
                    $mail->AltBody = "Your password reset code is: {$otpCode}. This code expires in 10 minutes.";

                    $mail->send();
                    $emailSent = true;
                } else {
                    error_log("PHPMailer not found - OTP for {$email}: {$otpCode}");
                }
            } catch (Exception $emailEx) {
                error_log("Password reset email failed: " . $emailEx->getMessage() . " - OTP: $otpCode");
            }

            // Log activity (skip if activity_logs table doesn't exist)
            try {
                $logStmt = $db->prepare(
                    "INSERT INTO activity_logs (user_id, role, action, description, ip_address)
                     VALUES (?, ?, 'password_reset_request', ?, ?)"
                );
                $logStmt->execute([
                    $user['user_id'],
                    $user['role'],
                    'Password reset requested',
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
            } catch (Exception $logEx) {
                // Ignore logging errors
            }

            // Always return success with OTP code for testing (remove otp_code in production)
            echo json_encode([
                'success' => true,
                'message' => $emailSent ? 'Reset code sent to your email.' : 'Reset code generated. Check console for code.',
                'otp_code' => $otpCode // Show OTP for testing
            ]);

        } catch (Exception $e) {
            error_log("Password reset request error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to process reset request: ' . $e->getMessage()]);
        }
        break;

    case '/password/reset':
        // Reset password with OTP
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        if (empty($data->email) || empty($data->otp_code) || empty($data->new_password)) {
            echo json_encode(['error' => 'Email, OTP code, and new password are required']);
            break;
        }

        $email = strtolower(trim($data->email));
        $otpCode = trim($data->otp_code);
        $newPassword = $data->new_password;

        // Validate password
        if (strlen($newPassword) < 8) {
            echo json_encode(['error' => 'Password must be at least 8 characters']);
            break;
        }
        if (!preg_match('/[A-Z]/', $newPassword)) {
            echo json_encode(['error' => 'Password must contain at least one uppercase letter']);
            break;
        }
        if (!preg_match('/[a-z]/', $newPassword)) {
            echo json_encode(['error' => 'Password must contain at least one lowercase letter']);
            break;
        }
        if (!preg_match('/[0-9]/', $newPassword)) {
            echo json_encode(['error' => 'Password must contain at least one number']);
            break;
        }

        try {
            // Find valid reset request
            $findStmt = $db->prepare("
                SELECT * FROM password_resets
                WHERE email = ? AND otp_code = ? AND expires_at > NOW() AND used = FALSE
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $findStmt->execute([$email, $otpCode]);
            $resetRequest = $findStmt->fetch(PDO::FETCH_ASSOC);

            if (!$resetRequest) {
                echo json_encode(['error' => 'Invalid or expired reset code']);
                break;
            }

            // Update password
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $updateStmt->execute([$passwordHash, $resetRequest['user_id']]);

            // Mark reset as used
            $markUsedStmt = $db->prepare("UPDATE password_resets SET used = TRUE WHERE reset_id = ?");
            $markUsedStmt->execute([$resetRequest['reset_id']]);

            // Invalidate all other reset requests for this user
            $invalidateStmt = $db->prepare("UPDATE password_resets SET used = TRUE WHERE user_id = ? AND reset_id != ?");
            $invalidateStmt->execute([$resetRequest['user_id'], $resetRequest['reset_id']]);

            // Log activity
            $logStmt = $db->prepare(
                "INSERT INTO activity_logs (user_id, role, action, description, ip_address)
                 VALUES (?, 'customer', 'password_reset', 'Password was reset', ?)"
            );
            $logStmt->execute([
                $resetRequest['user_id'],
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Password reset successful. You can now login with your new password.'
            ]);

        } catch (Exception $e) {
            error_log("Password reset error: " . $e->getMessage());
            echo json_encode(['error' => 'Failed to reset password']);
        }
        break;

    // =====================================================
    // CUSTOMER SPACE SHARING ENDPOINTS
    // =====================================================

    case '/spaces':
        // Search or create customer spaces
        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Public search for spaces
            $filters = [
                'city' => $_GET['city'] ?? null,
                'postcode' => $_GET['postcode'] ?? null,
                'max_price_hour' => $_GET['max_price'] ?? null,
                'space_type' => $_GET['type'] ?? null,
                'latitude' => $_GET['lat'] ?? null,
                'longitude' => $_GET['lng'] ?? null,
                'radius' => $_GET['radius'] ?? 10,
                'limit' => $_GET['limit'] ?? 20,
                'offset' => $_GET['offset'] ?? 0
            ];

            if (!empty($_GET['amenities'])) {
                $filters['amenities'] = explode(',', $_GET['amenities']);
            }

            echo json_encode($spaceController->search(array_filter($filters)));
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Create a new space listing (authenticated)
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            echo json_encode($spaceController->create($data, $_SESSION['user_id']));
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
        break;

    case '/spaces/detail':
        // Get single space details
        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);

        $spaceId = $_GET['id'] ?? null;
        if (!$spaceId) {
            echo json_encode(['error' => 'Space ID required']);
            break;
        }

        echo json_encode($spaceController->get((int)$spaceId));
        break;

    case '/my-spaces':
        // Get owner's spaces (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo json_encode($spaceController->getMySpaces($_SESSION['user_id']));
        } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Update a space
            $spaceId = $_GET['id'] ?? $data->space_id ?? null;
            if (!$spaceId) {
                echo json_encode(['error' => 'Space ID required']);
                break;
            }
            echo json_encode($spaceController->update((int)$spaceId, $data, $_SESSION['user_id']));
        } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            // Delete a space
            $spaceId = $_GET['id'] ?? $data->space_id ?? null;
            if (!$spaceId) {
                echo json_encode(['error' => 'Space ID required']);
                break;
            }
            echo json_encode($spaceController->delete((int)$spaceId, $_SESSION['user_id']));
        }
        break;

    case '/my-earnings':
        // Get owner's earnings summary (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        echo json_encode($spaceController->getEarnings($_SESSION['user_id']));
        break;

    case '/spaces/book':
        // Book a customer space (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        echo json_encode($spaceController->book($data, $_SESSION['user_id']));
        break;

    case '/spaces/calculate-price':
        // Calculate booking price for a space
        $spaceId = $_GET['space_id'] ?? null;
        $startTime = $_GET['start_time'] ?? null;
        $endTime = $_GET['end_time'] ?? null;

        if (!$spaceId || !$startTime || !$endTime) {
            echo json_encode(['error' => 'space_id, start_time, and end_time required']);
            break;
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        echo json_encode($spaceController->calculatePrice((int)$spaceId, $startTime, $endTime));
        break;

    case '/my-space-bookings':
        // Get bookings as renter or owner (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);

        $role = $_GET['role'] ?? 'renter'; // 'renter' or 'owner'
        $status = $_GET['status'] ?? null;

        if ($role === 'owner') {
            echo json_encode($spaceController->getSpaceBookings($_SESSION['user_id'], $status));
        } else {
            echo json_encode($spaceController->getMyBookings($_SESSION['user_id'], $status));
        }
        break;

    case '/spaces/cancel-booking':
        // Cancel a space booking (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        if (empty($data->booking_id)) {
            echo json_encode(['error' => 'Booking ID required']);
            break;
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        echo json_encode($spaceController->cancelBooking(
            (int)$data->booking_id,
            $_SESSION['user_id'],
            $data->reason ?? null
        ));
        break;

    case '/spaces/toggle-pause':
        // Pause/resume a space listing (authenticated owner)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        if (empty($data->space_id)) {
            echo json_encode(['error' => 'Space ID required']);
            break;
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        echo json_encode($spaceController->togglePause(
            (int)$data->space_id,
            $_SESSION['user_id'],
            $data->pause ?? true
        ));
        break;

    case '/spaces/upload-photos':
        // Upload photos for a space listing (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        if (empty($_FILES['photos'])) {
            echo json_encode(['error' => 'No photos provided']);
            break;
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        $spaceId = isset($_POST['space_id']) ? (int)$_POST['space_id'] : null;
        echo json_encode($spaceController->uploadPhotos(
            $_FILES['photos'],
            $_SESSION['user_id'],
            $spaceId
        ));
        break;

    case '/spaces/delete-photo':
        // Delete a photo from a space (authenticated owner)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        if (empty($data->space_id) || empty($data->photo_url)) {
            echo json_encode(['error' => 'Space ID and photo URL required']);
            break;
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        echo json_encode($spaceController->deletePhoto(
            (int)$data->space_id,
            $_SESSION['user_id'],
            $data->photo_url
        ));
        break;

    case '/spaces/gallery':
        // Get public gallery of spaces with photos
        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $since = $_GET['since'] ?? null;

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        $spaces = $spaceController->getGallerySpaces($limit, $offset, $since);

        echo json_encode([
            'success' => true,
            'spaces' => $spaces,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        break;

    case '/earnings/by-space':
        // Get earnings breakdown by space (authenticated owner)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        echo json_encode([
            'success' => true,
            'spaces' => $spaceController->getEarningsBySpace($_SESSION['user_id'])
        ]);
        break;

    case '/earnings/by-period':
        // Get earnings by time period (authenticated owner)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        $period = $_GET['period'] ?? 'month';
        if (!in_array($period, ['week', 'month', 'year'])) {
            $period = 'month';
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        echo json_encode([
            'success' => true,
            'period' => $period,
            'data' => $spaceController->getEarningsByPeriod($_SESSION['user_id'], $period)
        ]);
        break;

    case '/earnings/payouts':
        // Get payout history (authenticated owner)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        $status = $_GET['status'] ?? null;
        if ($status && !in_array($status, ['pending', 'processing', 'completed', 'failed'])) {
            $status = null;
        }

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        echo json_encode([
            'success' => true,
            'payouts' => $spaceController->getPayoutHistory($_SESSION['user_id'], $status)
        ]);
        break;

    case '/earnings/bookings':
        // Get owner's booking history (authenticated owner)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 20;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        require_once '../app/controllers/CustomerSpaceController.php';
        $spaceController = new CustomerSpaceController($db);
        echo json_encode([
            'success' => true,
            'bookings' => $spaceController->getOwnerBookingHistory($_SESSION['user_id'], $limit, $offset)
        ]);
        break;

    // =====================================================
    // STRIPE PAYMENT ENDPOINTS
    // =====================================================

    case '/payment/config':
        // Get public Stripe configuration (no auth required)
        require_once '../app/controllers/PaymentController.php';
        $paymentController = new PaymentController($db);
        echo json_encode($paymentController->getConfig());
        break;

    case '/payment/create-intent':
        // Create a payment intent (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        require_once '../app/controllers/PaymentController.php';
        $paymentController = new PaymentController($db);

        // Use space payment method if booking a customer space
        if (($data->booking_type ?? '') === 'customer_space' && !empty($data->space_id)) {
            echo json_encode($paymentController->createSpacePayment($data, $_SESSION['user_id']));
        } else {
            echo json_encode($paymentController->createIntent($data, $_SESSION['user_id']));
        }
        break;

    case '/payment/confirm':
        // Confirm a payment (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        require_once '../app/controllers/PaymentController.php';
        $paymentController = new PaymentController($db);
        echo json_encode($paymentController->confirmPayment($data, $_SESSION['user_id']));
        break;

    case '/payment/webhook':
        // Stripe webhook handler (no auth - verified by signature)
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

        require_once '../app/controllers/PaymentController.php';
        $paymentController = new PaymentController($db);
        $result = $paymentController->handleWebhook($payload, $signature);

        if (isset($result['error'])) {
            http_response_code(400);
        }
        echo json_encode($result);
        break;

    case '/payment/refund':
        // Request a refund (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
        }

        require_once '../app/controllers/PaymentController.php';
        $paymentController = new PaymentController($db);
        echo json_encode($paymentController->refund($data, $_SESSION['user_id']));
        break;

    case '/payment/history':
        // Get payment history (authenticated)
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        require_once '../app/controllers/PaymentController.php';
        $paymentController = new PaymentController($db);
        echo json_encode($paymentController->getHistory(
            $_SESSION['user_id'],
            (int)($_GET['limit'] ?? 20),
            (int)($_GET['offset'] ?? 0)
        ));
        break;

    // =====================================================
    // AIRPORT BOOKING ENDPOINTS
    // =====================================================

    case '/airport-booking':
        // Airport parking booking
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            break;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Get user's airport bookings
            try {
                $stmt = $db->prepare("
                    SELECT pb.*, g.garage_name, g.location
                    FROM parking_bookings_live pb
                    LEFT JOIN garages g ON pb.garage_id = g.garage_id
                    WHERE pb.user_id = ? AND pb.airport_code IS NOT NULL
                    ORDER BY pb.created_at DESC
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'bookings' => $bookings,
                    'count' => count($bookings)
                ]);
            } catch (Exception $e) {
                error_log("Airport booking fetch error: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to fetch bookings']);
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Create airport booking
            $required = ['airport_code', 'start_time', 'end_time'];
            foreach ($required as $field) {
                if (empty($data->$field)) {
                    echo json_encode(['error' => "Missing required field: {$field}"]);
                    break 2;
                }
            }

            try {
                // Calculate price
                $start = new DateTime($data->start_time);
                $end = new DateTime($data->end_time);
                $days = max(1, $end->diff($start)->days);
                $dailyRate = $data->daily_rate ?? 15.00;
                $totalPrice = $days * $dailyRate;

                $stmt = $db->prepare("
                    INSERT INTO parking_bookings_live (
                        user_id, garage_id, booking_reference, vehicle_reg, start_time, end_time,
                        total_price, booking_status, payment_status,
                        airport_code, terminal, outbound_flight_number, return_flight_number, return_date,
                        vehicle_make, vehicle_model, vehicle_color, stripe_payment_intent_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $bookingRef = 'AP' . strtoupper(substr($data->airport_code, 0, 3)) . '-' . time() . '-' . rand(1000, 9999);

                $stmt->execute([
                    $_SESSION['user_id'],
                    $data->garage_id ?? null,
                    $bookingRef,
                    $data->vehicle_reg ?? null,
                    $data->start_time,
                    $data->end_time,
                    $totalPrice,
                    strtoupper($data->airport_code),
                    $data->terminal ?? null,
                    $data->outbound_flight ?? null,
                    $data->return_flight ?? null,
                    $data->return_date ?? null,
                    $data->vehicle_make ?? null,
                    $data->vehicle_model ?? null,
                    $data->vehicle_color ?? null,
                    $data->stripe_payment_intent_id ?? null
                ]);

                $bookingId = $db->lastInsertId();

                echo json_encode([
                    'success' => true,
                    'booking_id' => $bookingId,
                    'booking_reference' => $bookingRef,
                    'total_price' => $totalPrice,
                    'days' => $days,
                    'message' => 'Airport parking booking created'
                ]);
            } catch (Exception $e) {
                error_log("Airport booking create error: " . $e->getMessage());
                echo json_encode(['error' => 'Failed to create booking']);
            }
        }
        break;

    case '/airport-booking/calculate':
        // Calculate airport parking price
        $startTime = $_GET['start_time'] ?? null;
        $endTime = $_GET['end_time'] ?? null;
        $airportCode = $_GET['airport_code'] ?? 'LHR';

        if (!$startTime || !$endTime) {
            echo json_encode(['error' => 'start_time and end_time required']);
            break;
        }

        try {
            $start = new DateTime($startTime);
            $end = new DateTime($endTime);
            $days = max(1, $end->diff($start)->days);

            // Airport-specific rates
            $rates = [
                'LHR' => 18.00,
                'LGW' => 15.00,
                'STN' => 12.00,
                'LTN' => 10.00,
                'LCY' => 20.00
            ];

            $dailyRate = $rates[strtoupper($airportCode)] ?? 15.00;
            $subtotal = $days * $dailyRate;
            $serviceFee = round($subtotal * 0.10, 2); // 10% service fee
            $total = $subtotal + $serviceFee;

            echo json_encode([
                'success' => true,
                'price' => [
                    'days' => $days,
                    'daily_rate' => $dailyRate,
                    'subtotal' => $subtotal,
                    'service_fee' => $serviceFee,
                    'total' => $total,
                    'currency' => 'GBP'
                ]
            ]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Invalid date format']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Endpoint not found"]);
}
?>
