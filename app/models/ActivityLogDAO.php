<?php
/**
 * ActivityLogDAO - Handles activity logs and time-based tracking
 */
class ActivityLogDAO {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Log user activity
     */
    public function logActivity($userId, $role, $action, $description = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, role, action, description, ip_address)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            return $stmt->execute([
                $userId,
                $role,
                $action,
                $description,
                $ipAddress
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get all activity logs with filters
     */
    public function getActivityLogs($filters = []) {
        try {
            $sql = "
                SELECT 
                    al.log_id,
                    al.user_id,
                    al.role,
                    al.action,
                    al.description,
                    al.ip_address,
                    al.created_at,
                    u.full_name,
                    u.email
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.user_id
                WHERE 1=1
            ";
            
            $params = [];
            
            // Filter by role
            if (isset($filters['role'])) {
                $sql .= " AND al.role = ?";
                $params[] = $filters['role'];
            }
            
            // Filter by user
            if (isset($filters['user_id'])) {
                $sql .= " AND al.user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            // Filter by date range
            if (isset($filters['start_date'])) {
                $sql .= " AND DATE(al.created_at) >= ?";
                $params[] = $filters['start_date'];
            }
            
            if (isset($filters['end_date'])) {
                $sql .= " AND DATE(al.created_at) <= ?";
                $params[] = $filters['end_date'];
            }
            
            $sql .= " ORDER BY al.created_at DESC LIMIT 100";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get activity statistics by role and time interval
     */
    public function getActivityStatsByTimeInterval($interval = 'hour') {
        try {
            $groupBy = match($interval) {
                'hour' => "DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00')",
                'day' => "DATE(created_at)",
                'week' => "YEARWEEK(created_at)",
                'month' => "DATE_FORMAT(created_at, '%Y-%m')",
                default => "DATE(created_at)"
            };
            
            $stmt = $this->db->query("
                SELECT 
                    role,
                    {$groupBy} as time_interval,
                    COUNT(*) as activity_count,
                    COUNT(DISTINCT user_id) as unique_users
                FROM activity_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY role, time_interval
                ORDER BY time_interval DESC, role
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get activity summary by role
     */
    public function getActivitySummaryByRole() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    role,
                    COUNT(*) as total_activities,
                    COUNT(DISTINCT user_id) as active_users,
                    MAX(created_at) as last_activity,
                    DATE(created_at) as activity_date,
                    COUNT(*) as daily_count
                FROM activity_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY role, DATE(created_at)
                ORDER BY activity_date DESC, role
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get real-time active users by role
     */
    public function getActiveUsersByRole($timeWindow = 30) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    role,
                    COUNT(DISTINCT user_id) as active_users,
                    GROUP_CONCAT(DISTINCT action) as recent_actions
                FROM activity_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                GROUP BY role
            ");
            
            $stmt->execute([$timeWindow]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get hourly activity distribution
     */
    public function getHourlyActivityDistribution() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    HOUR(created_at) as hour,
                    role,
                    COUNT(*) as activity_count
                FROM activity_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY HOUR(created_at), role
                ORDER BY hour, role
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
?>
