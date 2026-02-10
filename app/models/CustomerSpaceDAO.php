<?php
/**
 * Customer Space DAO
 *
 * Handles database operations for customer-listed parking spaces.
 * Supports CRUD operations, search, and availability management.
 */

class CustomerSpaceDAO
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create a new parking space listing
     *
     * @param int $ownerId Owner user ID
     * @param array $data Space data
     * @return array Result with space_id or error
     */
    public function create(int $ownerId, array $data): array
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO customer_spaces (
                    owner_id, space_name, space_type, address_line1, address_line2,
                    city, postcode, latitude, longitude, description, amenities,
                    instructions, price_per_hour, price_per_day, min_booking_hours,
                    max_booking_days, photos, status
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
                )
            ");

            $result = $stmt->execute([
                $ownerId,
                $data['space_name'],
                $data['space_type'] ?? 'driveway',
                $data['address_line1'],
                $data['address_line2'] ?? null,
                $data['city'],
                $data['postcode'],
                $data['latitude'] ?? null,
                $data['longitude'] ?? null,
                $data['description'] ?? null,
                is_array($data['amenities'] ?? null) ? json_encode($data['amenities']) : ($data['amenities'] ?? null),
                $data['instructions'] ?? null,
                $data['price_per_hour'],
                $data['price_per_day'] ?? null,
                $data['min_booking_hours'] ?? 1,
                $data['max_booking_days'] ?? 30,
                is_array($data['photos'] ?? null) ? json_encode($data['photos']) : ($data['photos'] ?? null)
            ]);

            if ($result) {
                return [
                    'success' => true,
                    'space_id' => $this->db->lastInsertId(),
                    'message' => 'Space listing created and pending approval'
                ];
            }

            return ['success' => false, 'error' => 'Failed to create listing'];
        } catch (PDOException $e) {
            error_log("CustomerSpaceDAO::create error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error creating listing'];
        }
    }

    /**
     * Update a parking space listing
     *
     * @param int $spaceId Space ID
     * @param int $ownerId Owner ID (for verification)
     * @param array $data Updated data
     * @return array Result
     */
    public function update(int $spaceId, int $ownerId, array $data): array
    {
        try {
            // Verify ownership
            $stmt = $this->db->prepare("SELECT owner_id FROM customer_spaces WHERE space_id = ?");
            $stmt->execute([$spaceId]);
            $space = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$space || $space['owner_id'] != $ownerId) {
                return ['success' => false, 'error' => 'Space not found or access denied'];
            }

            $updates = [];
            $params = [];

            $allowedFields = [
                'space_name', 'space_type', 'address_line1', 'address_line2',
                'city', 'postcode', 'latitude', 'longitude', 'description',
                'amenities', 'instructions', 'price_per_hour', 'price_per_day',
                'min_booking_hours', 'max_booking_days', 'photos', 'status'
            ];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $value = $data[$field];
                    if (in_array($field, ['amenities', 'photos']) && is_array($value)) {
                        $value = json_encode($value);
                    }
                    $updates[] = "{$field} = ?";
                    $params[] = $value;
                }
            }

            if (empty($updates)) {
                return ['success' => false, 'error' => 'No fields to update'];
            }

            $params[] = $spaceId;
            $params[] = $ownerId;

            $stmt = $this->db->prepare("
                UPDATE customer_spaces
                SET " . implode(', ', $updates) . "
                WHERE space_id = ? AND owner_id = ?
            ");
            $stmt->execute($params);

            return ['success' => true, 'message' => 'Space updated successfully'];
        } catch (PDOException $e) {
            error_log("CustomerSpaceDAO::update error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error updating listing'];
        }
    }

    /**
     * Get a space by ID
     *
     * @param int $spaceId Space ID
     * @return array|null Space data
     */
    public function getById(int $spaceId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT cs.*, u.name as owner_name, u.email as owner_email
            FROM customer_spaces cs
            JOIN users u ON cs.owner_id = u.user_id
            WHERE cs.space_id = ?
        ");
        $stmt->execute([$spaceId]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($space) {
            $space['amenities'] = json_decode($space['amenities'] ?? '[]', true);
            $space['photos'] = json_decode($space['photos'] ?? '[]', true);
        }

        return $space ?: null;
    }

    /**
     * Get all spaces owned by a user
     *
     * @param int $ownerId Owner user ID
     * @return array List of spaces
     */
    public function getByOwner(int $ownerId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM customer_spaces
            WHERE owner_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$ownerId]);
        $spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($spaces as &$space) {
            $space['amenities'] = json_decode($space['amenities'] ?? '[]', true);
            $space['photos'] = json_decode($space['photos'] ?? '[]', true);
        }

        return $spaces;
    }

    /**
     * Search for available spaces
     *
     * @param array $filters Search filters
     * @return array Matching spaces
     */
    public function search(array $filters = []): array
    {
        $where = ['cs.status = ?'];
        $params = ['active'];

        // Location filter
        if (!empty($filters['city'])) {
            $where[] = 'cs.city LIKE ?';
            $params[] = '%' . $filters['city'] . '%';
        }

        if (!empty($filters['postcode'])) {
            $where[] = 'cs.postcode LIKE ?';
            $params[] = $filters['postcode'] . '%';
        }

        // Price filter
        if (!empty($filters['max_price_hour'])) {
            $where[] = 'cs.price_per_hour <= ?';
            $params[] = $filters['max_price_hour'];
        }

        // Space type filter
        if (!empty($filters['space_type'])) {
            $where[] = 'cs.space_type = ?';
            $params[] = $filters['space_type'];
        }

        // Amenities filter
        if (!empty($filters['amenities']) && is_array($filters['amenities'])) {
            foreach ($filters['amenities'] as $amenity) {
                $where[] = 'JSON_CONTAINS(cs.amenities, ?)';
                $params[] = '"' . $amenity . '"';
            }
        }

        // Location radius search (if lat/lng provided)
        $orderBy = 'cs.average_rating DESC, cs.total_bookings DESC';
        if (!empty($filters['latitude']) && !empty($filters['longitude'])) {
            $lat = (float)$filters['latitude'];
            $lng = (float)$filters['longitude'];
            $radius = (float)($filters['radius'] ?? 10); // Default 10 miles

            // Haversine formula for distance
            $orderBy = "(
                3959 * acos(
                    cos(radians({$lat}))
                    * cos(radians(cs.latitude))
                    * cos(radians(cs.longitude) - radians({$lng}))
                    + sin(radians({$lat}))
                    * sin(radians(cs.latitude))
                )
            ) ASC";

            // Only include spaces within radius
            $where[] = "(
                3959 * acos(
                    cos(radians(?))
                    * cos(radians(cs.latitude))
                    * cos(radians(cs.longitude) - radians(?))
                    + sin(radians(?))
                    * sin(radians(cs.latitude))
                )
            ) <= ?";
            $params[] = $lat;
            $params[] = $lng;
            $params[] = $lat;
            $params[] = $radius;
        }

        $limit = min((int)($filters['limit'] ?? 20), 100);
        $offset = (int)($filters['offset'] ?? 0);

        $stmt = $this->db->prepare("
            SELECT cs.*, u.name as owner_name,
                   (SELECT COUNT(*) FROM customer_space_bookings WHERE space_id = cs.space_id AND booking_status = 'completed') as booking_count
            FROM customer_spaces cs
            JOIN users u ON cs.owner_id = u.user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($spaces as &$space) {
            $space['amenities'] = json_decode($space['amenities'] ?? '[]', true);
            $space['photos'] = json_decode($space['photos'] ?? '[]', true);
        }

        return $spaces;
    }

    /**
     * Get owner earnings summary
     *
     * @param int $ownerId Owner user ID
     * @return array Earnings data
     */
    public function getOwnerEarnings(int $ownerId): array
    {
        // Total earnings
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(total_earnings), 0) as total_earnings,
                   COALESCE(SUM(total_bookings), 0) as total_bookings,
                   COUNT(*) as total_spaces
            FROM customer_spaces
            WHERE owner_id = ?
        ");
        $stmt->execute([$ownerId]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        // Pending payouts
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(owner_payout), 0) as pending_payout
            FROM customer_space_bookings
            WHERE owner_id = ? AND payment_status = 'paid' AND booking_status IN ('completed', 'active')
        ");
        $stmt->execute([$ownerId]);
        $pending = $stmt->fetch(PDO::FETCH_ASSOC);

        // This month's earnings
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(owner_payout), 0) as month_earnings
            FROM customer_space_bookings
            WHERE owner_id = ? AND payment_status = 'paid'
                  AND MONTH(created_at) = MONTH(CURRENT_DATE())
                  AND YEAR(created_at) = YEAR(CURRENT_DATE())
        ");
        $stmt->execute([$ownerId]);
        $month = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_earnings' => (float)$totals['total_earnings'],
            'total_bookings' => (int)$totals['total_bookings'],
            'total_spaces' => (int)$totals['total_spaces'],
            'pending_payout' => (float)$pending['pending_payout'],
            'month_earnings' => (float)$month['month_earnings']
        ];
    }

    /**
     * Check if a space is available for given time period
     *
     * @param int $spaceId Space ID
     * @param string $startTime Start datetime
     * @param string $endTime End datetime
     * @return bool True if available
     */
    public function isAvailable(int $spaceId, string $startTime, string $endTime): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM customer_space_bookings
            WHERE space_id = ?
                  AND booking_status NOT IN ('cancelled', 'completed')
                  AND (
                      (start_time <= ? AND end_time > ?)
                      OR (start_time < ? AND end_time >= ?)
                      OR (start_time >= ? AND end_time <= ?)
                  )
        ");
        $stmt->execute([
            $spaceId,
            $startTime, $startTime,
            $endTime, $endTime,
            $startTime, $endTime
        ]);

        return $stmt->fetchColumn() == 0;
    }

    /**
     * Update space statistics after booking
     *
     * @param int $spaceId Space ID
     * @param float $earnings Earnings to add
     */
    public function updateStats(int $spaceId, float $earnings): void
    {
        $stmt = $this->db->prepare("
            UPDATE customer_spaces
            SET total_earnings = total_earnings + ?,
                total_bookings = total_bookings + 1
            WHERE space_id = ?
        ");
        $stmt->execute([$earnings, $spaceId]);
    }

    /**
     * Update average rating
     *
     * @param int $spaceId Space ID
     */
    public function updateRating(int $spaceId): void
    {
        $stmt = $this->db->prepare("
            UPDATE customer_spaces cs
            SET average_rating = (
                SELECT AVG(rating) FROM customer_space_reviews WHERE space_id = cs.space_id
            ),
            review_count = (
                SELECT COUNT(*) FROM customer_space_reviews WHERE space_id = cs.space_id
            )
            WHERE space_id = ?
        ");
        $stmt->execute([$spaceId]);
    }

    /**
     * Delete a space listing
     *
     * @param int $spaceId Space ID
     * @param int $ownerId Owner ID for verification
     * @return array Result
     */
    public function delete(int $spaceId, int $ownerId): array
    {
        try {
            // Check for active bookings
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM customer_space_bookings
                WHERE space_id = ? AND booking_status IN ('pending', 'confirmed', 'active')
            ");
            $stmt->execute([$spaceId]);

            if ($stmt->fetchColumn() > 0) {
                return [
                    'success' => false,
                    'error' => 'Cannot delete space with active bookings'
                ];
            }

            // Delete the space
            $stmt = $this->db->prepare("
                DELETE FROM customer_spaces WHERE space_id = ? AND owner_id = ?
            ");
            $result = $stmt->execute([$spaceId, $ownerId]);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Space deleted successfully'];
            }

            return ['success' => false, 'error' => 'Space not found or access denied'];
        } catch (PDOException $e) {
            error_log("CustomerSpaceDAO::delete error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Database error deleting space'];
        }
    }

    /**
     * Get earnings breakdown by space
     *
     * @param int $ownerId Owner user ID
     * @return array Earnings per space
     */
    public function getEarningsBySpace(int $ownerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    cs.space_id,
                    cs.space_name,
                    cs.city,
                    cs.total_earnings,
                    cs.total_bookings,
                    cs.average_rating,
                    cs.status,
                    COALESCE(
                        (SELECT SUM(owner_payout) FROM customer_space_bookings
                         WHERE space_id = cs.space_id AND payment_status = 'paid'
                         AND MONTH(created_at) = MONTH(CURRENT_DATE())
                         AND YEAR(created_at) = YEAR(CURRENT_DATE())), 0
                    ) as month_earnings,
                    COALESCE(
                        (SELECT COUNT(*) FROM customer_space_bookings
                         WHERE space_id = cs.space_id AND booking_status IN ('pending', 'confirmed', 'active')), 0
                    ) as active_bookings
                FROM customer_spaces cs
                WHERE cs.owner_id = ?
                ORDER BY cs.total_earnings DESC
            ");
            $stmt->execute([$ownerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("CustomerSpaceDAO::getEarningsBySpace error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get earnings breakdown by period
     *
     * @param int $ownerId Owner user ID
     * @param string $period Period type (week, month, year)
     * @return array Earnings by period
     */
    public function getEarningsByPeriod(int $ownerId, string $period = 'month'): array
    {
        try {
            $groupBy = match($period) {
                'week' => "DATE_FORMAT(created_at, '%Y-%u')",
                'year' => "YEAR(created_at)",
                default => "DATE_FORMAT(created_at, '%Y-%m')"
            };

            $labelFormat = match($period) {
                'week' => "CONCAT(YEAR(created_at), ' W', WEEK(created_at))",
                'year' => "YEAR(created_at)",
                default => "DATE_FORMAT(created_at, '%b %Y')"
            };

            $stmt = $this->db->prepare("
                SELECT
                    {$labelFormat} as period_label,
                    SUM(owner_payout) as earnings,
                    COUNT(*) as bookings,
                    SUM(platform_fee) as platform_fees
                FROM customer_space_bookings
                WHERE owner_id = ? AND payment_status = 'paid'
                GROUP BY {$groupBy}
                ORDER BY MIN(created_at) DESC
                LIMIT 12
            ");
            $stmt->execute([$ownerId]);
            return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            error_log("CustomerSpaceDAO::getEarningsByPeriod error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get payout history from owner_payouts table
     *
     * @param int $ownerId Owner user ID
     * @param string|null $status Filter by status
     * @return array Payout records
     */
    public function getPayoutHistory(int $ownerId, ?string $status = null): array
    {
        try {
            $sql = "
                SELECT
                    payout_id,
                    amount,
                    currency,
                    status,
                    bank_account_last4,
                    period_start,
                    period_end,
                    bookings_count,
                    processed_at,
                    failure_reason,
                    created_at
                FROM owner_payouts
                WHERE owner_id = ?
            ";

            $params = [$ownerId];

            if ($status) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY created_at DESC LIMIT 50";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("CustomerSpaceDAO::getPayoutHistory error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get owner's booking history with full details
     *
     * @param int $ownerId Owner user ID
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array Booking records
     */
    public function getOwnerBookingHistory(int $ownerId, int $limit = 20, int $offset = 0): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    b.booking_id,
                    b.space_id,
                    cs.space_name,
                    b.renter_id,
                    u.full_name as renter_name,
                    b.start_time,
                    b.end_time,
                    b.total_price,
                    b.platform_fee,
                    b.owner_payout,
                    b.booking_status,
                    b.payment_status,
                    b.vehicle_reg,
                    b.vehicle_make,
                    b.vehicle_model,
                    b.check_in_time,
                    b.check_out_time,
                    b.created_at
                FROM customer_space_bookings b
                JOIN customer_spaces cs ON b.space_id = cs.space_id
                JOIN users u ON b.renter_id = u.user_id
                WHERE b.owner_id = ?
                ORDER BY b.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$ownerId, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("CustomerSpaceDAO::getOwnerBookingHistory error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get gallery spaces with photos for public display
     *
     * @param int $limit Number of spaces
     * @param int $offset Pagination offset
     * @param string|null $since Timestamp for polling new additions
     * @return array Spaces with photos
     */
    public function getGallerySpaces(int $limit = 20, int $offset = 0, ?string $since = null): array
    {
        try {
            $sql = "
                SELECT
                    cs.space_id,
                    cs.space_name,
                    cs.space_type,
                    cs.city,
                    cs.postcode,
                    cs.price_per_hour,
                    cs.price_per_day,
                    cs.photos,
                    cs.amenities,
                    cs.average_rating,
                    cs.review_count,
                    cs.created_at,
                    u.full_name as owner_name
                FROM customer_spaces cs
                JOIN users u ON cs.owner_id = u.user_id
                WHERE cs.status = 'active'
                  AND cs.photos IS NOT NULL
                  AND cs.photos != '[]'
                  AND cs.photos != 'null'
            ";

            $params = [];

            if ($since) {
                $sql .= " AND cs.created_at > ?";
                $params[] = $since;
            }

            $sql .= " ORDER BY cs.created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON fields
            foreach ($spaces as &$space) {
                $space['photos'] = json_decode($space['photos'], true) ?: [];
                $space['amenities'] = json_decode($space['amenities'], true) ?: [];
            }

            return $spaces;
        } catch (PDOException $e) {
            error_log("CustomerSpaceDAO::getGallerySpaces error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total count of gallery spaces
     *
     * @return int Total count
     */
    public function getGalleryCount(): int
    {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) FROM customer_spaces
                WHERE status = 'active'
                  AND photos IS NOT NULL
                  AND photos != '[]'
                  AND photos != 'null'
            ");
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }
}
