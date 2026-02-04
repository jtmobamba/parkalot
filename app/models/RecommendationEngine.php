<?php
/**
 * AI-Powered Recommendation Engine
 * Provides intelligent garage recommendations based on multiple factors
 */
class RecommendationEngine {
    private $db;
    
    // Scoring weights for different factors
    const WEIGHT_USER_HISTORY = 0.25;
    const WEIGHT_RATING = 0.20;
    const WEIGHT_PRICE = 0.20;
    const WEIGHT_AVAILABILITY = 0.15;
    const WEIGHT_LOCATION = 0.10;
    const WEIGHT_AMENITIES = 0.10;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get AI-powered garage recommendations for a user
     * @param int $userId User ID (or null for guest users)
     * @param array $params Optional parameters (location, date_time, duration)
     * @return array Ranked list of recommended garages
     */
    public function getRecommendations($userId = null, $params = []) {
        $garages = $this->getAllGarages();
        $scoredGarages = [];

        foreach ($garages as $garage) {
            $score = $this->calculateGarageScore($garage, $userId, $params);
            $garage['recommendation_score'] = round($score, 2);
            $garage['recommendation_reasons'] = $this->getRecommendationReasons($garage, $score);
            $scoredGarages[] = $garage;
        }

        // Sort by recommendation score (highest first)
        usort($scoredGarages, function($a, $b) {
            return $b['recommendation_score'] <=> $a['recommendation_score'];
        });

        return array_slice($scoredGarages, 0, 5); // Return top 5 recommendations
    }

    /**
     * Calculate comprehensive score for a garage
     */
    private function calculateGarageScore($garage, $userId, $params) {
        $score = 0;

        // 1. User History Score (25%)
        if ($userId) {
            $score += $this->getUserHistoryScore($garage['garage_id'], $userId) * self::WEIGHT_USER_HISTORY;
        } else {
            $score += 50 * self::WEIGHT_USER_HISTORY; // Neutral score for guests
        }

        // 2. Rating Score (20%)
        $score += $this->getRatingScore($garage['rating']) * self::WEIGHT_RATING;

        // 3. Price Score (20%)
        $score += $this->getPriceScore($garage['price_per_hour'], $userId) * self::WEIGHT_PRICE;

        // 4. Availability Score (15%)
        $score += $this->getAvailabilityScore($garage['garage_id'], $params) * self::WEIGHT_AVAILABILITY;

        // 5. Location Score (10%)
        $score += $this->getLocationScore($garage, $params) * self::WEIGHT_LOCATION;

        // 6. Amenities Score (10%)
        $score += $this->getAmenitiesScore($garage['amenities'], $userId) * self::WEIGHT_AMENITIES;

        return $score;
    }

    /**
     * Score based on user's previous reservations and preferences
     */
    private function getUserHistoryScore($garageId, $userId) {
        try {
            // Check if user has used this garage before
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as visit_count,
                       AVG(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completion_rate
                FROM reservations
                WHERE user_id = ? AND garage_id = ?
            ");
            $stmt->execute([$userId, $garageId]);
            $history = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($history['visit_count'] > 0) {
                // User has been here before - boost score
                return 70 + ($history['completion_rate'] * 30);
            }

            // Check user's preferred location pattern
            $stmt = $this->db->prepare("
                SELECT g.location, COUNT(*) as location_visits
                FROM reservations r
                INNER JOIN garages g ON r.garage_id = g.garage_id
                WHERE r.user_id = ?
                GROUP BY g.location
                ORDER BY location_visits DESC
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $preferredLocation = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($preferredLocation) {
                $stmt = $this->db->prepare("SELECT location FROM garages WHERE garage_id = ?");
                $stmt->execute([$garageId]);
                $currentLocation = $stmt->fetchColumn();

                if ($currentLocation === $preferredLocation['location']) {
                    return 60; // Same preferred location
                }
            }

            return 40; // New garage, neutral score
        } catch (Exception $e) {
            return 50; // Default neutral score on error
        }
    }

    /**
     * Score based on garage rating
     */
    private function getRatingScore($rating) {
        if ($rating >= 4.5) return 100;
        if ($rating >= 4.0) return 85;
        if ($rating >= 3.5) return 70;
        if ($rating >= 3.0) return 50;
        return 30;
    }

    /**
     * Score based on price competitiveness
     */
    private function getPriceScore($price, $userId) {
        try {
            // Get average market price
            $stmt = $this->db->query("SELECT AVG(price_per_hour) as avg_price FROM garages");
            $avgPrice = $stmt->fetchColumn();

            // Get user's price preference if available
            $userMaxPrice = null;
            if ($userId) {
                $stmt = $this->db->prepare("SELECT max_price_per_hour FROM user_preferences WHERE user_id = ?");
                $stmt->execute([$userId]);
                $userMaxPrice = $stmt->fetchColumn();
            }

            // Calculate price score
            if ($userMaxPrice && $price <= $userMaxPrice) {
                return 100; // Within user's budget
            }

            $priceRatio = $price / $avgPrice;
            if ($priceRatio <= 0.7) return 100; // Very cheap
            if ($priceRatio <= 0.9) return 85;  // Below average
            if ($priceRatio <= 1.1) return 70;  // Average
            if ($priceRatio <= 1.3) return 50;  // Above average
            return 30; // Expensive

        } catch (Exception $e) {
            return 50;
        }
    }

    /**
     * Score based on real-time availability
     */
    private function getAvailabilityScore($garageId, $params) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    total_spaces,
                    (SELECT COUNT(*) FROM reservations 
                     WHERE garage_id = ? AND status = 'active') as active_reservations
                FROM garages 
                WHERE garage_id = ?
            ");
            $stmt->execute([$garageId, $garageId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            $occupancyRate = $data['active_reservations'] / $data['total_spaces'];
            
            if ($occupancyRate < 0.5) return 100; // Plenty of space
            if ($occupancyRate < 0.7) return 80;  // Good availability
            if ($occupancyRate < 0.85) return 60; // Moderate availability
            if ($occupancyRate < 0.95) return 40; // Limited availability
            return 20; // Nearly full

        } catch (Exception $e) {
            return 50;
        }
    }

    /**
     * Score based on location (if provided in params)
     */
    private function getLocationScore($garage, $params) {
        if (isset($params['preferred_location'])) {
            $similarity = similar_text(
                strtolower($garage['location']),
                strtolower($params['preferred_location'])
            );
            return min(100, $similarity * 5);
        }
        return 50; // Neutral if no location preference
    }

    /**
     * Score based on amenities matching user preferences
     */
    private function getAmenitiesScore($amenities, $userId) {
        if (!$amenities) return 30;

        $amenityList = explode(',', $amenities);
        $amenityCount = count($amenityList);

        // Premium amenities
        $premiumAmenities = ['EV Charging', 'CCTV', '24/7 Access', 'Security Guard'];
        $premiumCount = 0;
        
        foreach ($premiumAmenities as $premium) {
            if (stripos($amenities, $premium) !== false) {
                $premiumCount++;
            }
        }

        $baseScore = min(70, $amenityCount * 15);
        $premiumBonus = $premiumCount * 10;

        return min(100, $baseScore + $premiumBonus);
    }

    /**
     * Generate human-readable reasons for recommendation
     */
    private function getRecommendationReasons($garage, $score) {
        $reasons = [];

        if ($garage['rating'] >= 4.5) {
            $reasons[] = "Highly rated (" . $garage['rating'] . "★)";
        }

        if ($garage['price_per_hour'] <= 3.00) {
            $reasons[] = "Budget-friendly (£" . $garage['price_per_hour'] . "/hr)";
        }

        if (stripos($garage['amenities'], 'EV Charging') !== false) {
            $reasons[] = "EV Charging available";
        }

        if (stripos($garage['amenities'], 'CCTV') !== false) {
            $reasons[] = "Enhanced security";
        }

        if (stripos($garage['amenities'], '24/7 Access') !== false) {
            $reasons[] = "24/7 accessible";
        }

        if (empty($reasons)) {
            $reasons[] = "Good overall match";
        }

        return array_slice($reasons, 0, 3); // Max 3 reasons
    }

    /**
     * Get all garages with basic info
     */
    private function getAllGarages() {
        $stmt = $this->db->query("
            SELECT 
                garage_id,
                garage_name,
                location,
                total_spaces,
                price_per_hour,
                rating,
                amenities,
                image_url,
                latitude,
                longitude
            FROM garages
            ORDER BY garage_id ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Log recommendation interaction for ML improvement
     */
    public function logRecommendationClick($userId, $garageId, $recommendationScore) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, role, action, description)
                VALUES (?, 'customer', 'recommendation_click', ?)
            ");
            $description = json_encode([
                'garage_id' => $garageId,
                'score' => $recommendationScore
            ]);
            $stmt->execute([$userId, $description]);
        } catch (Exception $e) {
            // Silently fail - logging shouldn't break the flow
        }
    }
}
?>
