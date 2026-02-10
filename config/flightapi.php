<?php
/**
 * Flight API Configuration
 *
 * Configuration for FlightAPI.io integration for real-time flight tracking.
 * API Documentation: https://www.flightapi.io/
 */

require_once __DIR__ . '/api_keys.php';

// =====================================================
// FLIGHT API SETTINGS
// =====================================================

/**
 * London Airport IATA codes
 */
define('LONDON_AIRPORTS', [
    'LHR' => [
        'name' => 'London Heathrow Airport',
        'city' => 'London',
        'terminals' => ['T2', 'T3', 'T4', 'T5'],
        'lat' => 51.4700223,
        'lon' => -0.4542955
    ],
    'LGW' => [
        'name' => 'London Gatwick Airport',
        'city' => 'Crawley',
        'terminals' => ['North', 'South'],
        'lat' => 51.1536621,
        'lon' => -0.1820629
    ],
    'STN' => [
        'name' => 'London Stansted Airport',
        'city' => 'Stansted Mountfitchet',
        'terminals' => ['Main'],
        'lat' => 51.8860181,
        'lon' => 0.2388890
    ],
    'LTN' => [
        'name' => 'London Luton Airport',
        'city' => 'Luton',
        'terminals' => ['Main'],
        'lat' => 51.8746290,
        'lon' => -0.3683260
    ],
    'LCY' => [
        'name' => 'London City Airport',
        'city' => 'London',
        'terminals' => ['Main'],
        'lat' => 51.5048000,
        'lon' => 0.0495000
    ]
]);

/**
 * Flight status mapping
 */
define('FLIGHT_STATUS_MAP', [
    'scheduled' => ['label' => 'Scheduled', 'color' => '#6b7280', 'icon' => 'clock'],
    'boarding' => ['label' => 'Boarding', 'color' => '#f59e0b', 'icon' => 'users'],
    'departed' => ['label' => 'Departed', 'color' => '#3b82f6', 'icon' => 'plane-departure'],
    'in_air' => ['label' => 'In Flight', 'color' => '#8b5cf6', 'icon' => 'plane'],
    'landed' => ['label' => 'Landed', 'color' => '#10b981', 'icon' => 'plane-arrival'],
    'arrived' => ['label' => 'Arrived', 'color' => '#10b981', 'icon' => 'check-circle'],
    'cancelled' => ['label' => 'Cancelled', 'color' => '#ef4444', 'icon' => 'x-circle'],
    'delayed' => ['label' => 'Delayed', 'color' => '#f97316', 'icon' => 'alert-triangle'],
    'diverted' => ['label' => 'Diverted', 'color' => '#ec4899', 'icon' => 'arrow-right']
]);

/**
 * Default number of flights to fetch
 */
define('FLIGHTAPI_DEFAULT_LIMIT', 20);

/**
 * Get Flight API headers
 *
 * @return array HTTP headers for API requests
 */
function getFlightApiHeaders(): array {
    return [
        'Accept: application/json',
        'Content-Type: application/json',
        'User-Agent: ParkaLot-System/1.0'
    ];
}

/**
 * Get demo flight data for development/testing
 *
 * @param string $airportCode IATA airport code
 * @param string $type 'departures' or 'arrivals'
 * @param int $limit Number of flights to return
 * @return array Mock flight data
 */
function getFlightApiDemoData(string $airportCode = 'LHR', string $type = 'departures', int $limit = 10): array {
    $airlines = [
        ['code' => 'BA', 'name' => 'British Airways', 'logo' => 'https://logos.skyscnr.com/images/airlines/BA.png'],
        ['code' => 'VS', 'name' => 'Virgin Atlantic', 'logo' => 'https://logos.skyscnr.com/images/airlines/VS.png'],
        ['code' => 'EK', 'name' => 'Emirates', 'logo' => 'https://logos.skyscnr.com/images/airlines/EK.png'],
        ['code' => 'AA', 'name' => 'American Airlines', 'logo' => 'https://logos.skyscnr.com/images/airlines/AA.png'],
        ['code' => 'LH', 'name' => 'Lufthansa', 'logo' => 'https://logos.skyscnr.com/images/airlines/LH.png'],
        ['code' => 'AF', 'name' => 'Air France', 'logo' => 'https://logos.skyscnr.com/images/airlines/AF.png'],
        ['code' => 'KL', 'name' => 'KLM', 'logo' => 'https://logos.skyscnr.com/images/airlines/KL.png'],
        ['code' => 'QF', 'name' => 'Qantas', 'logo' => 'https://logos.skyscnr.com/images/airlines/QF.png'],
        ['code' => 'SQ', 'name' => 'Singapore Airlines', 'logo' => 'https://logos.skyscnr.com/images/airlines/SQ.png'],
        ['code' => 'UA', 'name' => 'United Airlines', 'logo' => 'https://logos.skyscnr.com/images/airlines/UA.png']
    ];

    $destinations = [
        ['code' => 'JFK', 'name' => 'John F. Kennedy International', 'city' => 'New York'],
        ['code' => 'LAX', 'name' => 'Los Angeles International', 'city' => 'Los Angeles'],
        ['code' => 'DXB', 'name' => 'Dubai International', 'city' => 'Dubai'],
        ['code' => 'CDG', 'name' => 'Charles de Gaulle', 'city' => 'Paris'],
        ['code' => 'FRA', 'name' => 'Frankfurt Airport', 'city' => 'Frankfurt'],
        ['code' => 'AMS', 'name' => 'Amsterdam Schiphol', 'city' => 'Amsterdam'],
        ['code' => 'SIN', 'name' => 'Singapore Changi', 'city' => 'Singapore'],
        ['code' => 'SYD', 'name' => 'Sydney Airport', 'city' => 'Sydney'],
        ['code' => 'HKG', 'name' => 'Hong Kong International', 'city' => 'Hong Kong'],
        ['code' => 'NRT', 'name' => 'Narita International', 'city' => 'Tokyo']
    ];

    $statuses = ['scheduled', 'boarding', 'departed', 'delayed', 'in_air', 'landed'];
    $terminals = LONDON_AIRPORTS[$airportCode]['terminals'] ?? ['T1'];

    $flights = [];
    $baseTime = time();

    for ($i = 0; $i < $limit; $i++) {
        $airline = $airlines[array_rand($airlines)];
        $destination = $destinations[array_rand($destinations)];
        $terminal = $terminals[array_rand($terminals)];
        $status = $statuses[array_rand($statuses)];
        $flightNumber = rand(100, 999);
        $scheduledTime = $baseTime + ($i * 1800) + rand(-900, 900); // Every 30 mins with variance
        $delayMinutes = $status === 'delayed' ? rand(15, 120) : 0;

        $flight = [
            'flightNumber' => $airline['code'] . $flightNumber,
            'flightIata' => $airline['code'] . $flightNumber,
            'airline' => [
                'code' => $airline['code'],
                'name' => $airline['name'],
                'logo' => $airline['logo']
            ],
            'status' => $status,
            'delayMinutes' => $delayMinutes,
            'terminal' => $terminal,
            'gate' => chr(rand(65, 72)) . rand(1, 50),
            'scheduledTime' => date('c', $scheduledTime),
            'estimatedTime' => date('c', $scheduledTime + ($delayMinutes * 60)),
            'flightDate' => date('Y-m-d', $scheduledTime)
        ];

        if ($type === 'departures') {
            $flight['departure'] = [
                'airport' => $airportCode,
                'airportName' => LONDON_AIRPORTS[$airportCode]['name'],
                'city' => LONDON_AIRPORTS[$airportCode]['city']
            ];
            $flight['arrival'] = [
                'airport' => $destination['code'],
                'airportName' => $destination['name'],
                'city' => $destination['city']
            ];
        } else {
            $flight['departure'] = [
                'airport' => $destination['code'],
                'airportName' => $destination['name'],
                'city' => $destination['city']
            ];
            $flight['arrival'] = [
                'airport' => $airportCode,
                'airportName' => LONDON_AIRPORTS[$airportCode]['name'],
                'city' => LONDON_AIRPORTS[$airportCode]['city']
            ];
        }

        $flights[] = $flight;
    }

    // Sort by scheduled time
    usort($flights, fn($a, $b) => strtotime($a['scheduledTime']) - strtotime($b['scheduledTime']));

    return $flights;
}

/**
 * Get demo flight status for a specific flight
 *
 * @param string $flightNumber Flight number (e.g., 'BA123')
 * @return array|null Mock flight data or null if not found
 */
function getFlightApiDemoFlightStatus(string $flightNumber): ?array {
    // Parse airline code and number
    preg_match('/^([A-Z]{2})(\d+)$/i', strtoupper($flightNumber), $matches);

    if (empty($matches)) {
        return null;
    }

    $airlineCode = $matches[1];
    $number = $matches[2];

    $airlines = [
        'BA' => 'British Airways',
        'VS' => 'Virgin Atlantic',
        'EK' => 'Emirates',
        'AA' => 'American Airlines',
        'LH' => 'Lufthansa',
        'AF' => 'Air France',
        'KL' => 'KLM',
        'QF' => 'Qantas',
        'SQ' => 'Singapore Airlines',
        'UA' => 'United Airlines'
    ];

    if (!isset($airlines[$airlineCode])) {
        return null;
    }

    $destinations = ['JFK', 'LAX', 'DXB', 'CDG', 'FRA'];
    $destination = $destinations[array_rand($destinations)];
    $statuses = ['scheduled', 'boarding', 'departed', 'in_air'];
    $status = $statuses[array_rand($statuses)];

    $scheduledTime = strtotime('+' . rand(1, 6) . ' hours');

    return [
        'flightNumber' => $flightNumber,
        'flightIata' => $flightNumber,
        'airline' => [
            'code' => $airlineCode,
            'name' => $airlines[$airlineCode],
            'logo' => "https://logos.skyscnr.com/images/airlines/{$airlineCode}.png"
        ],
        'departure' => [
            'airport' => 'LHR',
            'airportName' => 'London Heathrow Airport',
            'city' => 'London',
            'terminal' => 'T5',
            'gate' => 'A' . rand(1, 30)
        ],
        'arrival' => [
            'airport' => $destination,
            'airportName' => 'International Airport',
            'city' => $destination
        ],
        'status' => $status,
        'delayMinutes' => $status === 'delayed' ? rand(15, 60) : 0,
        'scheduledTime' => date('c', $scheduledTime),
        'estimatedTime' => date('c', $scheduledTime),
        'flightDate' => date('Y-m-d')
    ];
}
