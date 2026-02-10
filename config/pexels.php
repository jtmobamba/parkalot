<?php
/**
 * Pexels API Configuration
 *
 * Configuration for Pexels image API integration.
 * API Documentation: https://www.pexels.com/api/documentation/
 */

require_once __DIR__ . '/api_keys.php';

// =====================================================
// PEXELS SETTINGS
// =====================================================

/**
 * Default images per page
 */
define('PEXELS_DEFAULT_PER_PAGE', 15);

/**
 * Maximum images per request
 */
define('PEXELS_MAX_PER_PAGE', 80);

/**
 * Image orientation options
 */
define('PEXELS_ORIENTATIONS', ['landscape', 'portrait', 'square']);

/**
 * Image size options for srcset
 */
define('PEXELS_SIZES', ['original', 'large2x', 'large', 'medium', 'small', 'portrait', 'landscape', 'tiny']);

/**
 * Category search queries for ParkaLot
 */
define('PEXELS_CATEGORIES', [
    'parking' => ['parking lot', 'car park', 'garage parking', 'parking garage'],
    'london' => ['london city', 'london skyline', 'london streets', 'big ben'],
    'airport' => ['airport', 'airport terminal', 'airplane', 'flight departure'],
    'city' => ['city traffic', 'urban driving', 'city streets', 'downtown'],
    'driving' => ['driving car', 'car interior', 'road trip', 'highway'],
    'hero' => ['parking lot aerial', 'city parking', 'modern parking', 'car park building']
]);

/**
 * Get Pexels API headers
 *
 * @return array HTTP headers for API requests
 */
function getPexelsHeaders(): array {
    $headers = [
        'Accept: application/json',
        'User-Agent: ParkaLot-System/1.0'
    ];

    if (!empty(PEXELS_API_KEY)) {
        $headers[] = 'Authorization: ' . PEXELS_API_KEY;
    }

    return $headers;
}

/**
 * Get demo images for development/testing
 * Uses real Pexels image URLs that are publicly accessible
 *
 * @param string $category Category name
 * @param int $limit Number of images to return
 * @return array Mock image data
 */
function getPexelsDemoImages(string $category = 'parking', int $limit = 6): array {
    // Use local images from /images directory
    $demoImages = [
        'parking' => [
            [
                'id' => 1,
                'photographer' => 'ParkaLot',
                'src' => [
                    'original' => '/images/IMG_0073.jpeg',
                    'large' => '/images/IMG_0073.jpeg',
                    'medium' => '/images/IMG_0073.jpeg',
                    'small' => '/images/IMG_0073.jpeg'
                ],
                'alt' => 'Parking facility'
            ],
            [
                'id' => 2,
                'photographer' => 'ParkaLot',
                'src' => [
                    'original' => '/images/IMG_0074.jpeg',
                    'large' => '/images/IMG_0074.jpeg',
                    'medium' => '/images/IMG_0074.jpeg',
                    'small' => '/images/IMG_0074.jpeg'
                ],
                'alt' => 'Car park entrance'
            ],
            [
                'id' => 3,
                'photographer' => 'ParkaLot',
                'src' => [
                    'original' => '/images/IMG_0076.jpeg',
                    'large' => '/images/IMG_0076.jpeg',
                    'medium' => '/images/IMG_0076.jpeg',
                    'small' => '/images/IMG_0076.jpeg'
                ],
                'alt' => 'Parking spaces'
            ]
        ],
        'london' => [
            [
                'id' => 4,
                'photographer' => 'ParkaLot',
                'src' => [
                    'original' => '/images/IMG_0037.jpeg',
                    'large' => '/images/IMG_0037.jpeg',
                    'medium' => '/images/IMG_0037.jpeg',
                    'small' => '/images/IMG_0037.jpeg'
                ],
                'alt' => 'London street'
            ],
            [
                'id' => 5,
                'photographer' => 'ParkaLot',
                'src' => [
                    'original' => '/images/IMG_0038.jpeg',
                    'large' => '/images/IMG_0038.jpeg',
                    'medium' => '/images/IMG_0038.jpeg',
                    'small' => '/images/IMG_0038.jpeg'
                ],
                'alt' => 'City view'
            ],
            [
                'id' => 6,
                'photographer' => 'ParkaLot',
                'src' => [
                    'original' => '/images/IMG_0039.jpeg',
                    'large' => '/images/IMG_0039.jpeg',
                    'medium' => '/images/IMG_0039.jpeg',
                    'small' => '/images/IMG_0039.jpeg'
                ],
                'alt' => 'Urban landscape'
            ]
        ],
        'airport' => [
            [
                'id' => 7,
                'photographer' => 'ParkaLot',
                'src' => [
                    'original' => '/images/IMG_0046.jpeg',
                    'large' => '/images/IMG_0046.jpeg',
                    'medium' => '/images/IMG_0046.jpeg',
                    'small' => '/images/IMG_0046.jpeg'
                ],
                'alt' => 'Airport parking'
            ],
            [
                'id' => 8,
                'photographer' => 'ParkaLot',
                'src' => [
                    'original' => '/images/IMG_0048.jpeg',
                    'large' => '/images/IMG_0048.jpeg',
                    'medium' => '/images/IMG_0048.jpeg',
                    'small' => '/images/IMG_0048.jpeg'
                ],
                'alt' => 'Travel parking'
            ],
            [
                'id' => 9,
                'photographer' => 'ParkaLot',
                'src' => [
                    'original' => '/images/IMG_0041.jpeg',
                    'large' => '/images/IMG_0041.jpeg',
                    'medium' => '/images/IMG_0041.jpeg',
                    'small' => '/images/IMG_0041.jpeg'
                ],
                'alt' => 'Parking lot'
            ]
        ],
        'hero' => [
            [
                'id' => 10,
                'photographer' => 'ParkaLot',
                'src' => [
                    'original' => '/images/IMG_0070.jpeg',
                    'large' => '/images/IMG_0070.jpeg',
                    'medium' => '/images/IMG_0070.jpeg',
                    'small' => '/images/IMG_0070.jpeg'
                ],
                'alt' => 'Parking facility'
            ],
            [
                'id' => 11,
                'photographer' => 'ParkaLot',
                'src' => [
                    'original' => '/images/IMG_0071.jpeg',
                    'large' => '/images/IMG_0071.jpeg',
                    'medium' => '/images/IMG_0071.jpeg',
                    'small' => '/images/IMG_0071.jpeg'
                ],
                'alt' => 'Car park'
            ]
        ]
    ];

    $images = $demoImages[$category] ?? $demoImages['parking'];

    // Cycle through images if limit exceeds available
    $result = [];
    for ($i = 0; $i < $limit; $i++) {
        $result[] = $images[$i % count($images)];
    }

    return $result;
}

/**
 * Get random hero image
 *
 * @return array Random hero image data
 */
function getPexelsRandomHeroImage(): array {
    $heroImages = [
        [
            'id' => 1,
            'photographer' => 'ParkaLot',
            'src' => [
                'original' => '/images/IMG_0073.jpeg',
                'large' => '/images/IMG_0073.jpeg',
                'medium' => '/images/IMG_0073.jpeg',
                'small' => '/images/IMG_0073.jpeg'
            ],
            'alt' => 'Parking facility'
        ],
        [
            'id' => 2,
            'photographer' => 'ParkaLot',
            'src' => [
                'original' => '/images/IMG_0070.jpeg',
                'large' => '/images/IMG_0070.jpeg',
                'medium' => '/images/IMG_0070.jpeg',
                'small' => '/images/IMG_0070.jpeg'
            ],
            'alt' => 'Car park'
        ]
    ];
    return $heroImages[array_rand($heroImages)];
}
