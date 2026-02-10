<?php
/**
 * Pexels Image Service
 *
 * Integrates with Pexels API for high-quality stock images
 * for hero sections, backgrounds, and feature cards.
 *
 * API Documentation: https://www.pexels.com/api/documentation/
 */

require_once __DIR__ . '/BaseApiService.php';
require_once __DIR__ . '/../../config/pexels.php';

class PexelsService extends BaseApiService
{
    /**
     * Constructor
     *
     * @param PDO|null $db Database connection
     */
    public function __construct(?PDO $db = null)
    {
        parent::__construct($db, PEXELS_API_KEY, API_REQUEST_TIMEOUT);
        $this->baseUrl = PEXELS_API_URL;
        $this->apiSource = 'pexels';
        $this->cacheExpiry = PEXELS_CACHE_TTL;
    }

    /**
     * Search for images by query
     *
     * @param string $query Search query
     * @param int $perPage Images per page
     * @param int $page Page number
     * @param string $orientation Image orientation
     * @return array Search results
     */
    public function searchImages(
        string $query,
        int $perPage = 15,
        int $page = 1,
        string $orientation = 'landscape'
    ): array {
        $query = trim($query);

        if (empty($query)) {
            return ['error' => 'Search query is required'];
        }

        // Check if we should use mock data
        if ($this->useMockData || empty($this->apiKey)) {
            return $this->getMockImages($query, $perPage);
        }

        // Check cache first
        $cacheKey = "search_{$query}_{$perPage}_{$page}_{$orientation}";
        $cached = $this->cacheGet($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Fetch from API
        $endpoint = "/search";
        $params = [
            'query' => $query,
            'per_page' => min($perPage, PEXELS_MAX_PER_PAGE),
            'page' => $page,
            'orientation' => $orientation
        ];

        $data = $this->makeRequest($endpoint, $params);

        if (!$data) {
            return $this->getMockImages($query, $perPage);
        }

        $formatted = $this->formatResponse($data);

        // Cache the result
        $this->cacheSet($cacheKey, $formatted);

        // Save images to database
        $this->saveImagesToDb($formatted['images'] ?? [], $query);

        return $formatted;
    }

    /**
     * Get curated images
     *
     * @param int $perPage Images per page
     * @param int $page Page number
     * @return array Curated images
     */
    public function getCuratedImages(int $perPage = 15, int $page = 1): array
    {
        // Check if we should use mock data
        if ($this->useMockData || empty($this->apiKey)) {
            return $this->getMockImages('curated', $perPage);
        }

        // Check cache first
        $cacheKey = "curated_{$perPage}_{$page}";
        $cached = $this->cacheGet($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Fetch from API
        $endpoint = "/curated";
        $params = [
            'per_page' => min($perPage, PEXELS_MAX_PER_PAGE),
            'page' => $page
        ];

        $data = $this->makeRequest($endpoint, $params);

        if (!$data) {
            return $this->getMockImages('curated', $perPage);
        }

        $formatted = $this->formatResponse($data);

        // Cache the result
        $this->cacheSet($cacheKey, $formatted);

        return $formatted;
    }

    /**
     * Get a specific image by ID
     *
     * @param int $pexelsId Pexels image ID
     * @return array|null Image data or null if not found
     */
    public function getImageById(int $pexelsId): ?array
    {
        // Check cache/database first
        $cacheKey = "image_{$pexelsId}";
        $cached = $this->cacheGet($cacheKey);
        if ($cached) {
            return $cached;
        }

        // Check database
        $dbImage = $this->getImageFromDb($pexelsId);
        if ($dbImage) {
            return $dbImage;
        }

        // Check if we should use mock data
        if ($this->useMockData || empty($this->apiKey)) {
            return null;
        }

        // Fetch from API
        $endpoint = "/photos/{$pexelsId}";
        $data = $this->makeRequest($endpoint);

        if (!$data) {
            return null;
        }

        $formatted = $this->formatImageData($data);

        // Cache the result
        $this->cacheSet($cacheKey, $formatted);

        return $formatted;
    }

    /**
     * Get images by category
     *
     * @param string $category Category name (parking, london, airport, city, driving, hero)
     * @param int $limit Number of images
     * @return array Images for category
     */
    public function getImagesByCategory(string $category, int $limit = 6): array
    {
        $categories = PEXELS_CATEGORIES;

        if (!isset($categories[$category])) {
            return ['error' => 'Invalid category', 'validCategories' => array_keys($categories)];
        }

        // Pick a random search query from the category
        $queries = $categories[$category];
        $query = $queries[array_rand($queries)];

        return $this->searchImages($query, $limit);
    }

    /**
     * Get a random image from a category
     *
     * @param string $category Category name
     * @return array|null Random image or null
     */
    public function getRandomByCategory(string $category): ?array
    {
        $images = $this->getImagesByCategory($category, 10);

        if (isset($images['error']) || empty($images['images'])) {
            // Return mock image
            $mockImages = getPexelsDemoImages($category, 1);
            return $mockImages[0] ?? null;
        }

        return $images['images'][array_rand($images['images'])];
    }

    /**
     * Get hero image for a specific page
     *
     * @param string $page Page identifier (home, find-parking, airport, etc.)
     * @return array Image data
     */
    public function getHeroImage(string $page = 'home'): array
    {
        $pageCategories = [
            'home' => 'hero',
            'find-parking' => 'parking',
            'airport-parking' => 'airport',
            'business' => 'city',
            'how-it-works' => 'driving'
        ];

        $category = $pageCategories[$page] ?? 'hero';

        $image = $this->getRandomByCategory($category);

        if (!$image) {
            return getPexelsRandomHeroImage();
        }

        return $image;
    }

    /**
     * Get mock images for development
     *
     * @param string $category Category or search query
     * @param int $limit Number of images
     * @return array Mock image data
     */
    protected function getMockImages(string $category, int $limit = 6): array
    {
        // Map search queries to categories
        $categoryMap = [
            'parking' => 'parking',
            'car park' => 'parking',
            'garage' => 'parking',
            'london' => 'london',
            'airport' => 'airport',
            'flight' => 'airport',
            'city' => 'london',
            'hero' => 'hero',
            'curated' => 'hero'
        ];

        // Find matching category
        $matchedCategory = 'parking';
        foreach ($categoryMap as $keyword => $cat) {
            if (stripos($category, $keyword) !== false) {
                $matchedCategory = $cat;
                break;
            }
        }

        $demoImages = getPexelsDemoImages($matchedCategory, $limit);

        return [
            'images' => array_map(function($img) use ($category) {
                return [
                    'id' => $img['id'],
                    'photographer' => $img['photographer'],
                    'photographerUrl' => "https://www.pexels.com/@{$img['photographer']}",
                    'src' => $img['src'],
                    'alt' => $img['alt'],
                    'avgColor' => '#2563eb',
                    'width' => 1920,
                    'height' => 1280
                ];
            }, $demoImages),
            'total' => count($demoImages),
            'page' => 1,
            'perPage' => $limit,
            'query' => $category,
            'isMockData' => true
        ];
    }

    /**
     * Get image from database
     *
     * @param int $pexelsId Pexels image ID
     * @return array|null Image data or null
     */
    protected function getImageFromDb(int $pexelsId): ?array
    {
        if (!$this->db) {
            return null;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT * FROM pexels_images WHERE pexels_id = :id
            ");
            $stmt->execute([':id' => $pexelsId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'id' => $row['pexels_id'],
                    'photographer' => $row['photographer'],
                    'photographerUrl' => $row['photographer_url'],
                    'src' => [
                        'original' => $row['src_original'],
                        'large2x' => $row['src_large2x'],
                        'large' => $row['src_large'],
                        'medium' => $row['src_medium'],
                        'small' => $row['src_small'],
                        'portrait' => $row['src_portrait'],
                        'landscape' => $row['src_landscape'],
                        'tiny' => $row['src_tiny']
                    ],
                    'alt' => $row['alt_text'],
                    'avgColor' => $row['avg_color'],
                    'width' => $row['width'],
                    'height' => $row['height']
                ];
            }
        } catch (PDOException $e) {
            $this->logError("Failed to get image from DB: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Save images to database
     *
     * @param array $images Images to save
     * @param string $searchQuery Search query used
     */
    protected function saveImagesToDb(array $images, string $searchQuery = ''): void
    {
        if (!$this->db || empty($images)) {
            return;
        }

        try {
            $stmt = $this->db->prepare("
                INSERT INTO pexels_images
                    (pexels_id, photographer, photographer_url, photographer_id,
                     src_original, src_large2x, src_large, src_medium, src_small,
                     src_portrait, src_landscape, src_tiny,
                     alt_text, avg_color, width, height, category, search_query)
                VALUES
                    (:id, :photographer, :photographer_url, :photographer_id,
                     :src_original, :src_large2x, :src_large, :src_medium, :src_small,
                     :src_portrait, :src_landscape, :src_tiny,
                     :alt, :color, :width, :height, :category, :query)
                ON DUPLICATE KEY UPDATE
                    cached_at = NOW()
            ");

            foreach ($images as $img) {
                $src = $img['src'] ?? [];
                $stmt->execute([
                    ':id' => $img['id'],
                    ':photographer' => $img['photographer'] ?? null,
                    ':photographer_url' => $img['photographerUrl'] ?? null,
                    ':photographer_id' => $img['photographerId'] ?? null,
                    ':src_original' => $src['original'] ?? null,
                    ':src_large2x' => $src['large2x'] ?? null,
                    ':src_large' => $src['large'] ?? null,
                    ':src_medium' => $src['medium'] ?? null,
                    ':src_small' => $src['small'] ?? null,
                    ':src_portrait' => $src['portrait'] ?? null,
                    ':src_landscape' => $src['landscape'] ?? null,
                    ':src_tiny' => $src['tiny'] ?? null,
                    ':alt' => $img['alt'] ?? null,
                    ':color' => $img['avgColor'] ?? null,
                    ':width' => $img['width'] ?? null,
                    ':height' => $img['height'] ?? null,
                    ':category' => $this->guessCategory($searchQuery),
                    ':query' => $searchQuery
                ]);
            }
        } catch (PDOException $e) {
            $this->logError("Failed to save images: " . $e->getMessage());
        }
    }

    /**
     * Guess category from search query
     *
     * @param string $query Search query
     * @return string Category name
     */
    protected function guessCategory(string $query): string
    {
        $query = strtolower($query);
        $categories = [
            'parking' => ['parking', 'car park', 'garage'],
            'london' => ['london', 'uk', 'england', 'british'],
            'airport' => ['airport', 'flight', 'plane', 'terminal'],
            'city' => ['city', 'urban', 'downtown', 'street']
        ];

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($query, $keyword) !== false) {
                    return $category;
                }
            }
        }

        return 'general';
    }

    /**
     * Get HTTP headers for Pexels API
     *
     * @return array Headers
     */
    protected function getHeaders(): array
    {
        return getPexelsHeaders();
    }

    /**
     * Format API response
     *
     * @param array $data Raw API response
     * @return array Formatted data
     */
    protected function formatResponse(array $data): array
    {
        $images = [];

        foreach ($data['photos'] ?? [] as $photo) {
            $images[] = $this->formatImageData($photo);
        }

        return [
            'images' => $images,
            'total' => $data['total_results'] ?? count($images),
            'page' => $data['page'] ?? 1,
            'perPage' => $data['per_page'] ?? count($images),
            'nextPage' => $data['next_page'] ?? null,
            'prevPage' => $data['prev_page'] ?? null
        ];
    }

    /**
     * Format single image data
     *
     * @param array $photo Raw photo data
     * @return array Formatted image data
     */
    protected function formatImageData(array $photo): array
    {
        return [
            'id' => $photo['id'],
            'photographer' => $photo['photographer'],
            'photographerUrl' => $photo['photographer_url'],
            'photographerId' => $photo['photographer_id'] ?? null,
            'src' => [
                'original' => $photo['src']['original'] ?? null,
                'large2x' => $photo['src']['large2x'] ?? null,
                'large' => $photo['src']['large'] ?? null,
                'medium' => $photo['src']['medium'] ?? null,
                'small' => $photo['src']['small'] ?? null,
                'portrait' => $photo['src']['portrait'] ?? null,
                'landscape' => $photo['src']['landscape'] ?? null,
                'tiny' => $photo['src']['tiny'] ?? null
            ],
            'alt' => $photo['alt'] ?? '',
            'avgColor' => $photo['avg_color'] ?? '#2563eb',
            'width' => $photo['width'],
            'height' => $photo['height']
        ];
    }

    /**
     * Get fallback data
     *
     * @return array Fallback data
     */
    protected function getFallbackData(): array
    {
        return $this->getMockImages('parking', 6);
    }

    /**
     * Generate responsive image srcset
     *
     * @param array $src Image sources
     * @return string srcset attribute value
     */
    public static function generateSrcset(array $src): string
    {
        $srcset = [];

        if (!empty($src['tiny'])) {
            $srcset[] = $src['tiny'] . ' 280w';
        }
        if (!empty($src['small'])) {
            $srcset[] = $src['small'] . ' 400w';
        }
        if (!empty($src['medium'])) {
            $srcset[] = $src['medium'] . ' 640w';
        }
        if (!empty($src['large'])) {
            $srcset[] = $src['large'] . ' 940w';
        }
        if (!empty($src['large2x'])) {
            $srcset[] = $src['large2x'] . ' 1880w';
        }

        return implode(', ', $srcset);
    }
}
