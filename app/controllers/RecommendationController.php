<?php
/**
 * RecommendationController - AI-powered garage recommendations
 */
class RecommendationController {
    private $engine;
    
    public function __construct($db) {
        require_once __DIR__ . '/../models/RecommendationEngine.php';
        $this->engine = new RecommendationEngine($db);
    }

    /**
     * Get personalized recommendations
     */
    public function getRecommendations($userId = null, $params = []) {
        try {
            $recommendations = $this->engine->getRecommendations($userId, $params);
            
            return [
                'success' => true,
                'recommendations' => $recommendations,
                'count' => count($recommendations)
            ];
        } catch (Exception $e) {
            return ['error' => 'Failed to generate recommendations'];
        }
    }

    /**
     * Log when user clicks on a recommendation
     */
    public function logClick($userId, $garageId, $score) {
        try {
            $this->engine->logRecommendationClick($userId, $garageId, $score);
            return ['success' => true];
        } catch (Exception $e) {
            return ['error' => 'Failed to log interaction'];
        }
    }
}
?>
