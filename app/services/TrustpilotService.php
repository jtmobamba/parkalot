<?php
/**
 * Trustpilot Service
 *
 * Integrates with Trustpilot Product Reviews API to fetch
 * business reviews and ratings for display in footer widget.
 *
 * API Documentation: https://developers.trustpilot.com/product-reviews-api/
 */

require_once __DIR__ . '/BaseApiService.php';
require_once __DIR__ . '/../../config/trustpilot.php';

class TrustpilotService extends BaseApiService
{
    private string $businessUnitId;

    /**
     * Constructor
     *
     * @param PDO|null $db Database connection
     */
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db, TRUSTPILOT_API_KEY, API_REQUEST_TIMEOUT);
        $this->baseUrl = TRUSTPILOT_API_URL;
        $this->apiSource = 'trustpilot';
        $this->cacheExpiry = TRUSTPILOT_CACHE_TTL;
        $this->businessUnitId = TRUSTPILOT_BUSINESS_UNIT_ID;
    }

    /**
     * Get reviews from Trustpilot
     *
     * @param int $limit Number of reviews to fetch
     * @param int $page Page number
     * @return array Reviews data
     */
    public function getReviews(int $limit = 10, int $page = 1): array
    {
        // Check if we should use mock data
        if ($this->useMockData || empty($this->apiKey)) {
            return $this->getMockReviews($limit);
        }

        // Check cache first
        $cacheKey = "reviews_{$limit}_{$page}";
        $cached = $this->cacheGet($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Fetch from API
        $endpoint = "/business-units/{$this->businessUnitId}/reviews";
        $params = [
            'perPage' => min($limit, TRUSTPILOT_MAX_LIMIT),
            'page' => $page,
            'language' => implode(',', TRUSTPILOT_LANGUAGES)
        ];

        $data = $this->makeRequest($endpoint, $params);

        if (!$data) {
            return $this->getMockReviews($limit);
        }

        $formatted = $this->formatResponse($data);

        // Cache the result
        $this->cacheSet($cacheKey, $formatted);

        // Save to database
        $this->saveReviewsToDb($formatted['reviews'] ?? []);

        return $formatted;
    }

    /**
     * Get business statistics from Trustpilot
     *
     * @return array Business stats (rating, review count, etc.)
     */
    public function getBusinessStats(): array
    {
        // Check if we should use mock data
        if ($this->useMockData || empty($this->apiKey)) {
            return $this->getMockStats();
        }

        // Check cache first
        $cacheKey = "business_stats";
        $cached = $this->cacheGet($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Fetch from API
        $endpoint = "/business-units/{$this->businessUnitId}";
        $data = $this->makeRequest($endpoint);

        if (!$data) {
            return $this->getMockStats();
        }

        $stats = $this->formatBusinessStats($data);

        // Cache the result
        $this->cacheSet($cacheKey, $stats, 21600); // 6 hours

        // Save to database
        $this->saveStatsToDb($stats);

        return $stats;
    }

    /**
     * Get combined reviews and stats for widget
     *
     * @param int $reviewLimit Number of reviews
     * @return array Widget data
     */
    public function getWidgetData(int $reviewLimit = 5): array
    {
        return [
            'reviews' => $this->getReviews($reviewLimit),
            'stats' => $this->getBusinessStats(),
            'settings' => TRUSTPILOT_WIDGET_SETTINGS,
            'isMockData' => $this->useMockData
        ];
    }

    /**
     * Get mock reviews for development
     *
     * @param int $limit Number of reviews
     * @return array Mock review data
     */
    protected function getMockReviews(int $limit = 5): array
    {
        $demoReviews = getTrustpilotDemoReviews($limit);

        return [
            'reviews' => array_map(function($review) {
                return [
                    'id' => $review['id'],
                    'rating' => $review['stars'],
                    'title' => $review['title'],
                    'text' => $review['text'],
                    'date' => $review['createdAt'],
                    'dateFormatted' => date('M j, Y', strtotime($review['createdAt'])),
                    'consumer' => [
                        'name' => $review['consumer']['displayName'],
                        'location' => $review['consumer']['displayLocation'] ?? null,
                        'avatar' => null
                    ],
                    'isVerified' => $review['isVerified']
                ];
            }, $demoReviews),
            'total' => count($demoReviews),
            'page' => 1,
            'perPage' => $limit
        ];
    }

    /**
     * Get mock business stats
     *
     * @return array Mock stats
     */
    protected function getMockStats(): array
    {
        $demoStats = getTrustpilotDemoStats();

        return [
            'businessName' => $demoStats['displayName'],
            'trustScore' => $demoStats['trustScore'],
            'starsAverage' => round($demoStats['trustScore']),
            'totalReviews' => $demoStats['numberOfReviews']['total'],
            'starDistribution' => $demoStats['starDistribution'],
            'percentageByStars' => $this->calculatePercentages($demoStats['starDistribution'])
        ];
    }

    /**
     * Calculate percentage distribution by stars
     *
     * @param array $distribution Star distribution counts
     * @return array Percentages
     */
    protected function calculatePercentages(array $distribution): array
    {
        $total = array_sum($distribution);
        if ($total === 0) {
            return array_fill_keys(array_keys($distribution), 0);
        }

        $percentages = [];
        foreach ($distribution as $stars => $count) {
            $percentages[$stars] = round(($count / $total) * 100, 1);
        }

        return $percentages;
    }

    /**
     * Save reviews to database
     *
     * @param array $reviews Reviews to save
     */
    protected function saveReviewsToDb(array $reviews): void
    {
        if (!$this->db || empty($reviews)) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO trustpilot_reviews
                    (trustpilot_id, reviewer_name, rating, review_title, review_text, review_date, is_verified, expires_at)
                VALUES
                    (:id, :name, :rating, :title, :text, :date, :verified, DATE_ADD(NOW(), INTERVAL 24 HOUR))
                ON DUPLICATE KEY UPDATE
                    reviewer_name = VALUES(reviewer_name),
                    rating = VALUES(rating),
                    review_title = VALUES(review_title),
                    review_text = VALUES(review_text),
                    cached_at = NOW(),
                    expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
            ");

            foreach ($reviews as $review) {
                $stmt->execute([
                    ':id' => $review['id'],
                    ':name' => $review['consumer']['name'] ?? 'Anonymous',
                    ':rating' => $review['rating'],
                    ':title' => $review['title'] ?? null,
                    ':text' => $review['text'] ?? null,
                    ':date' => date('Y-m-d H:i:s', strtotime($review['date'])),
                    ':verified' => $review['isVerified'] ? 1 : 0
                ]);
            }
        } catch (PDOException $e) {
            $this->logError("Failed to save reviews: " . $e->getMessage());
        }
    }

    /**
     * Save stats to database
     *
     * @param array $stats Stats to save
     */
    protected function saveStatsToDb(array $stats): void
    {
        if (!$this->db) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO trustpilot_stats
                    (business_unit_id, trust_score, total_reviews, stars_average,
                     stars_5, stars_4, stars_3, stars_2, stars_1, expires_at)
                VALUES
                    (:id, :score, :total, :avg, :s5, :s4, :s3, :s2, :s1, DATE_ADD(NOW(), INTERVAL 6 HOUR))
            ");

            $stmt->execute([
                ':id' => $this->businessUnitId,
                ':score' => $stats['trustScore'],
                ':total' => $stats['totalReviews'],
                ':avg' => $stats['starsAverage'],
                ':s5' => $stats['starDistribution']['5'] ?? 0,
                ':s4' => $stats['starDistribution']['4'] ?? 0,
                ':s3' => $stats['starDistribution']['3'] ?? 0,
                ':s2' => $stats['starDistribution']['2'] ?? 0,
                ':s1' => $stats['starDistribution']['1'] ?? 0
            ]);
        } catch (PDOException $e) {
            $this->logError("Failed to save stats: " . $e->getMessage());
        }
    }

    /**
     * Get reviews from database (fallback)
     *
     * @param int $limit Number of reviews
     * @return array Database reviews
     */
    protected function getReviewsFromDb(int $limit = 10): array
    {
        if (!$this->db) {
            return [];
        }

        try {
            $stmt = $this->db->prepare("
                SELECT * FROM trustpilot_reviews
                ORDER BY review_date DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $this->logError("Failed to get reviews from DB: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get HTTP headers for Trustpilot API
     *
     * @return array Headers
     */
    protected function getHeaders(): array
    {
        return getTrustpilotHeaders();
    }

    /**
     * Format API response
     *
     * @param array $data Raw API response
     * @return array Formatted data
     */
    protected function formatResponse(array $data): array
    {
        $reviews = [];

        foreach ($data['reviews'] ?? [] as $review) {
            $reviews[] = [
                'id' => $review['id'],
                'rating' => $review['stars'],
                'title' => $review['title'] ?? '',
                'text' => $review['text'] ?? '',
                'date' => $review['createdAt'],
                'dateFormatted' => date('M j, Y', strtotime($review['createdAt'])),
                'consumer' => [
                    'name' => $review['consumer']['displayName'] ?? 'Anonymous',
                    'location' => $review['consumer']['displayLocation'] ?? null,
                    'avatar' => null
                ],
                'isVerified' => $review['isVerified'] ?? false
            ];
        }

        return [
            'reviews' => $reviews,
            'total' => $data['total'] ?? count($reviews),
            'page' => $data['page'] ?? 1,
            'perPage' => $data['perPage'] ?? count($reviews)
        ];
    }

    /**
     * Format business stats response
     *
     * @param array $data Raw API response
     * @return array Formatted stats
     */
    protected function formatBusinessStats(array $data): array
    {
        return [
            'businessName' => $data['displayName'] ?? 'ParkaLot',
            'trustScore' => $data['trustScore'] ?? 0,
            'starsAverage' => round($data['trustScore'] ?? 0),
            'totalReviews' => $data['numberOfReviews']['total'] ?? 0,
            'starDistribution' => [
                '5' => $data['stars'][4]['count'] ?? 0,
                '4' => $data['stars'][3]['count'] ?? 0,
                '3' => $data['stars'][2]['count'] ?? 0,
                '2' => $data['stars'][1]['count'] ?? 0,
                '1' => $data['stars'][0]['count'] ?? 0
            ],
            'percentageByStars' => []
        ];
    }

    /**
     * Get fallback data
     *
     * @return array Fallback data
     */
    protected function getFallbackData(): array
    {
        // Try database first
        $dbReviews = $this->getReviewsFromDb(10);

        if (!empty($dbReviews)) {
            return [
                'reviews' => $dbReviews,
                'total' => count($dbReviews),
                'fromCache' => true
            ];
        }

        // Return mock data
        return $this->getMockReviews(10);
    }

    /**
     * Render stars as HTML
     *
     * @param int $rating Rating (1-5)
     * @param string $size Size class (sm, md, lg)
     * @return string HTML string
     */
    public static function renderStars(int $rating, string $size = 'md'): string
    {
        $sizes = [
            'sm' => 14,
            'md' => 20,
            'lg' => 28
        ];

        $starSize = $sizes[$size] ?? $sizes['md'];
        $html = '<div class="trustpilot-stars" style="display: inline-flex; gap: 2px;">';

        for ($i = 1; $i <= 5; $i++) {
            $filled = $i <= $rating;
            $color = $filled ? '#00b67a' : '#dcdce6';
            $html .= '<svg width="' . $starSize . '" height="' . $starSize . '" viewBox="0 0 24 24" fill="' . $color . '">';
            $html .= '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>';
            $html .= '</svg>';
        }

        $html .= '</div>';

        return $html;
    }
}
