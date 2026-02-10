<?php
/**
 * API Keys Configuration
 *
 * Centralized API key management for all external services.
 * Uses environment variables with fallbacks for development.
 *
 * SECURITY NOTE: Never commit real API keys to version control.
 * Set these values in your .env file or server environment variables.
 */

// Prevent direct access
if (!defined('PARKALOT_INIT') && !isset($GLOBALS['api_init'])) {
    $GLOBALS['api_init'] = true;
}

/**
 * Get API key with environment variable support
 *
 * @param string $key Environment variable name
 * @param string $default Default value if not set
 * @return string API key value
 */
function getApiKey(string $key, string $default = ''): string {
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

// =====================================================
// TRUSTPILOT API
// Register at: https://developers.trustpilot.com/
// =====================================================
define('TRUSTPILOT_API_KEY', getApiKey('TRUSTPILOT_API_KEY', ''));
define('TRUSTPILOT_API_SECRET', getApiKey('TRUSTPILOT_API_SECRET', ''));
define('TRUSTPILOT_BUSINESS_UNIT_ID', getApiKey('TRUSTPILOT_BUSINESS_ID', 'demo-business-unit'));
define('TRUSTPILOT_API_URL', 'https://api.trustpilot.com/v1');
define('TRUSTPILOT_CACHE_TTL', 3600); // 1 hour

// =====================================================
// FLIGHT API (flightapi.io)
// Register at: https://www.flightapi.io/
// =====================================================
define('FLIGHTAPI_KEY', getApiKey('FLIGHTAPI_KEY', '6989126d424b8b224918ee5a'));
define('FLIGHTAPI_URL', 'https://api.flightapi.io');
define('FLIGHTAPI_CACHE_TTL', 900); // 15 minutes

// =====================================================
// PEXELS API
// Register at: https://www.pexels.com/api/
// =====================================================
define('PEXELS_API_KEY', getApiKey('PEXELS_API_KEY', ''));
define('PEXELS_API_URL', 'https://api.pexels.com/v1');
define('PEXELS_CACHE_TTL', 86400); // 24 hours

// =====================================================
// STREAMLINE ICONS API
// Register at: https://home.streamlinehq.com/api
// =====================================================
define('STREAMLINE_API_KEY', getApiKey('STREAMLINE_API_KEY', 'wbGRffzZEiPw9Vun.4d26ca7abb25c59f9c5b5cbfa60b608e'));
define('STREAMLINE_API_URL', 'https://api.streamlinehq.com');
define('STREAMLINE_CACHE_TTL', 604800); // 7 days

// =====================================================
// TFL API (Transport for London)
// Register at: https://api-portal.tfl.gov.uk/
// =====================================================
define('TFL_API_KEY', getApiKey('TFL_API_KEY', ''));
define('TFL_API_URL', 'https://api.tfl.gov.uk');
define('TFL_CACHE_TTL', 300); // 5 minutes

// =====================================================
// API STATUS FLAGS
// Used to determine if APIs should use mock data
// =====================================================
define('USE_MOCK_DATA', getApiKey('USE_MOCK_DATA', 'false') === 'true');
define('API_DEBUG_MODE', getApiKey('API_DEBUG_MODE', 'false') === 'true');
define('API_REQUEST_TIMEOUT', (int)getApiKey('API_REQUEST_TIMEOUT', '10'));

/**
 * Check if a specific API is configured
 *
 * @param string $api API name (trustpilot, flightapi, pexels, streamline, tfl)
 * @return bool True if API key is set
 */
function isApiConfigured(string $api): bool {
    switch (strtolower($api)) {
        case 'trustpilot':
            return !empty(TRUSTPILOT_API_KEY);
        case 'flightapi':
        case 'flight':
            return !empty(FLIGHTAPI_KEY);
        case 'pexels':
            return !empty(PEXELS_API_KEY);
        case 'streamline':
            return !empty(STREAMLINE_API_KEY);
        case 'tfl':
            return !empty(TFL_API_KEY);
        default:
            return false;
    }
}

/**
 * Get API configuration summary
 *
 * @return array API configuration status
 */
function getApiStatus(): array {
    return [
        'trustpilot' => [
            'configured' => isApiConfigured('trustpilot'),
            'using_mock' => USE_MOCK_DATA || !isApiConfigured('trustpilot')
        ],
        'flightapi' => [
            'configured' => isApiConfigured('flightapi'),
            'using_mock' => USE_MOCK_DATA || !isApiConfigured('flightapi')
        ],
        'pexels' => [
            'configured' => isApiConfigured('pexels'),
            'using_mock' => USE_MOCK_DATA || !isApiConfigured('pexels')
        ],
        'streamline' => [
            'configured' => isApiConfigured('streamline'),
            'using_mock' => USE_MOCK_DATA || !isApiConfigured('streamline')
        ],
        'tfl' => [
            'configured' => isApiConfigured('tfl'),
            'using_mock' => false // TfL API works without key (rate limited)
        ]
    ];
}
