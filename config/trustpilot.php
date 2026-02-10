<?php
/**
 * Trustpilot API Configuration
 *
 * Configuration for Trustpilot Product Reviews API integration.
 * API Documentation: https://developers.trustpilot.com/product-reviews-api/
 */

require_once __DIR__ . '/api_keys.php';

// =====================================================
// TRUSTPILOT SETTINGS
// =====================================================

/**
 * Default number of reviews to fetch
 */
define('TRUSTPILOT_DEFAULT_LIMIT', 10);

/**
 * Maximum reviews per request
 */
define('TRUSTPILOT_MAX_LIMIT', 100);

/**
 * Review languages to fetch
 */
define('TRUSTPILOT_LANGUAGES', ['en', 'en-GB']);

/**
 * Minimum rating to display (1-5)
 */
define('TRUSTPILOT_MIN_RATING', 1);

/**
 * Enable review filtering for inappropriate content
 */
define('TRUSTPILOT_FILTER_CONTENT', true);

/**
 * Widget display settings
 */
define('TRUSTPILOT_WIDGET_SETTINGS', [
    'show_stars' => true,
    'show_date' => true,
    'show_avatar' => true,
    'show_verified_badge' => true,
    'max_text_length' => 200,
    'animation_enabled' => true,
    'auto_scroll' => true,
    'scroll_interval' => 5000, // 5 seconds
]);

/**
 * Get Trustpilot API headers
 *
 * @return array HTTP headers for API requests
 */
function getTrustpilotHeaders(): array {
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'User-Agent: ParkaLot-System/1.0'
    ];

    if (!empty(TRUSTPILOT_API_KEY)) {
        $headers[] = 'apikey: ' . TRUSTPILOT_API_KEY;
    }

    return $headers;
}

/**
 * Get demo reviews for development/testing
 *
 * @param int $limit Number of reviews to return
 * @return array Mock review data
 */
function getTrustpilotDemoReviews(int $limit = 5): array {
    $demoReviews = [
        [
            'id' => 'demo-review-1',
            'stars' => 5,
            'title' => 'Excellent parking service!',
            'text' => 'Found a great parking spot near the station. The booking was easy and the price was very reasonable. Will definitely use ParkaLot again!',
            'createdAt' => date('c', strtotime('-2 days')),
            'consumer' => [
                'displayName' => 'Sarah M.',
                'displayLocation' => 'London, UK'
            ],
            'isVerified' => true
        ],
        [
            'id' => 'demo-review-2',
            'stars' => 5,
            'title' => 'Perfect for airport parking',
            'text' => 'Used ParkaLot for my Heathrow trip. Easy to find, secure parking and shuttle service was quick. Highly recommend!',
            'createdAt' => date('c', strtotime('-5 days')),
            'consumer' => [
                'displayName' => 'James T.',
                'displayLocation' => 'Surrey, UK'
            ],
            'isVerified' => true
        ],
        [
            'id' => 'demo-review-3',
            'stars' => 4,
            'title' => 'Good value for money',
            'text' => 'Decent parking experience. Location was a bit further than expected but staff were helpful and the price was competitive.',
            'createdAt' => date('c', strtotime('-1 week')),
            'consumer' => [
                'displayName' => 'Emma W.',
                'displayLocation' => 'Kent, UK'
            ],
            'isVerified' => true
        ],
        [
            'id' => 'demo-review-4',
            'stars' => 5,
            'title' => 'Saved me so much time',
            'text' => 'The real-time availability feature is brilliant. Found parking instantly near Kings Cross. No more circling the block!',
            'createdAt' => date('c', strtotime('-10 days')),
            'consumer' => [
                'displayName' => 'Michael R.',
                'displayLocation' => 'Essex, UK'
            ],
            'isVerified' => true
        ],
        [
            'id' => 'demo-review-5',
            'stars' => 5,
            'title' => 'Great experience overall',
            'text' => 'First time using the app and it worked perfectly. Clear directions, fair pricing, and the QR code entry was seamless.',
            'createdAt' => date('c', strtotime('-2 weeks')),
            'consumer' => [
                'displayName' => 'Lisa P.',
                'displayLocation' => 'Hertfordshire, UK'
            ],
            'isVerified' => true
        ],
        [
            'id' => 'demo-review-6',
            'stars' => 4,
            'title' => 'Reliable parking solution',
            'text' => 'Use ParkaLot regularly for work trips. Consistent quality and the price comparison feature helps find the best deals.',
            'createdAt' => date('c', strtotime('-3 weeks')),
            'consumer' => [
                'displayName' => 'David K.',
                'displayLocation' => 'London, UK'
            ],
            'isVerified' => false
        ],
        [
            'id' => 'demo-review-7',
            'stars' => 5,
            'title' => 'Amazing customer service',
            'text' => 'Had an issue with my booking and the support team resolved it within minutes. Very impressed with the level of service.',
            'createdAt' => date('c', strtotime('-1 month')),
            'consumer' => [
                'displayName' => 'Rachel B.',
                'displayLocation' => 'Buckinghamshire, UK'
            ],
            'isVerified' => true
        ],
        [
            'id' => 'demo-review-8',
            'stars' => 5,
            'title' => 'Best parking app in London',
            'text' => 'Tried several parking apps but ParkaLot is by far the best. Real-time updates, easy booking, great prices. 10/10!',
            'createdAt' => date('c', strtotime('-1 month')),
            'consumer' => [
                'displayName' => 'Tom H.',
                'displayLocation' => 'London, UK'
            ],
            'isVerified' => true
        ]
    ];

    return array_slice($demoReviews, 0, $limit);
}

/**
 * Get demo business statistics
 *
 * @return array Mock business stats
 */
function getTrustpilotDemoStats(): array {
    return [
        'businessUnitId' => TRUSTPILOT_BUSINESS_UNIT_ID,
        'displayName' => 'ParkaLot',
        'trustScore' => 4.7,
        'stars' => 5,
        'numberOfReviews' => [
            'total' => 1247,
            'usedForTrustScoreCalculation' => 1200
        ],
        'starDistribution' => [
            '5' => 892,
            '4' => 245,
            '3' => 67,
            '2' => 28,
            '1' => 15
        ]
    ];
}
