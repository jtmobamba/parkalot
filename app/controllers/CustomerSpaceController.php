<?php
/**
 * Customer Space Controller
 *
 * Handles API endpoints for customer parking space listings:
 * - CRUD operations for spaces
 * - Search and filtering
 * - Booking management for spaces
 * - Earnings and statistics
 */

require_once __DIR__ . '/../services/AzureBlobService.php';

class CustomerSpaceController
{
    private $spaceDAO;
    private $bookingDAO;
    private $db;
    private ?AzureBlobService $azureBlob = null;

    public function __construct($db)
    {
        $this->db = $db;
        $this->spaceDAO = DAOFactory::customerSpaceDAO($db);
        $this->bookingDAO = DAOFactory::customerSpaceBookingDAO($db);
        $this->azureBlob = new AzureBlobService();
    }

    /**
     * Create a new space listing
     *
     * @param object $data Request data
     * @param int $userId Owner user ID
     * @return array Response
     */
    public function create($data, int $userId): array
    {
        // Validate required fields
        $required = ['space_name', 'address_line1', 'city', 'postcode', 'price_per_hour'];
        foreach ($required as $field) {
            if (empty($data->$field)) {
                return ['error' => "Missing required field: {$field}", 'code' => 'validation'];
            }
        }

        // Validate price
        if (!is_numeric($data->price_per_hour) || $data->price_per_hour <= 0) {
            return ['error' => 'Price per hour must be a positive number', 'code' => 'validation'];
        }

        // Validate space type
        $validTypes = ['driveway', 'garage', 'parking_spot', 'car_park'];
        if (!empty($data->space_type) && !in_array($data->space_type, $validTypes)) {
            return ['error' => 'Invalid space type', 'code' => 'validation'];
        }

        $result = $this->spaceDAO->create($userId, (array)$data);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Space listing created successfully and is pending approval',
                'space_id' => $result['space_id']
            ];
        }

        return ['error' => $result['error'] ?? 'Failed to create listing'];
    }

    /**
     * Update a space listing
     *
     * @param int $spaceId Space ID
     * @param object $data Updated data
     * @param int $userId Owner user ID
     * @return array Response
     */
    public function update(int $spaceId, $data, int $userId): array
    {
        if (!$spaceId) {
            return ['error' => 'Space ID required', 'code' => 'validation'];
        }

        $result = $this->spaceDAO->update($spaceId, $userId, (array)$data);

        if ($result['success']) {
            return ['success' => true, 'message' => 'Space updated successfully'];
        }

        return ['error' => $result['error'] ?? 'Failed to update listing'];
    }

    /**
     * Get a single space
     *
     * @param int $spaceId Space ID
     * @return array Response
     */
    public function get(int $spaceId): array
    {
        $space = $this->spaceDAO->getById($spaceId);

        if (!$space) {
            return ['error' => 'Space not found', 'code' => 'not_found'];
        }

        // Get upcoming bookings for availability calendar
        $bookings = $this->bookingDAO->getBySpace($spaceId, date('Y-m-d'));

        return [
            'success' => true,
            'space' => $space,
            'bookings' => array_map(function($b) {
                return [
                    'start' => $b['start_time'],
                    'end' => $b['end_time'],
                    'status' => $b['booking_status']
                ];
            }, $bookings)
        ];
    }

    /**
     * Get all spaces owned by a user
     *
     * @param int $userId Owner user ID
     * @return array Response
     */
    public function getMySpaces(int $userId): array
    {
        $spaces = $this->spaceDAO->getByOwner($userId);

        return [
            'success' => true,
            'spaces' => $spaces,
            'count' => count($spaces)
        ];
    }

    /**
     * Search for available spaces
     *
     * @param array $filters Search filters
     * @return array Response
     */
    public function search(array $filters = []): array
    {
        $spaces = $this->spaceDAO->search($filters);

        return [
            'success' => true,
            'spaces' => $spaces,
            'count' => count($spaces),
            'filters' => $filters
        ];
    }

    /**
     * Get earnings summary for space owner
     *
     * @param int $userId Owner user ID
     * @return array Response
     */
    public function getEarnings(int $userId): array
    {
        $earnings = $this->spaceDAO->getOwnerEarnings($userId);

        // Get recent bookings on owner's spaces
        $recentBookings = $this->bookingDAO->getByOwner($userId);

        return [
            'success' => true,
            'earnings' => $earnings,
            'recent_bookings' => array_slice($recentBookings, 0, 10)
        ];
    }

    /**
     * Book a customer space
     *
     * @param object $data Booking data
     * @param int $userId Renter user ID
     * @return array Response
     */
    public function book($data, int $userId): array
    {
        // Validate required fields
        $required = ['space_id', 'start_time', 'end_time'];
        foreach ($required as $field) {
            if (empty($data->$field)) {
                return ['error' => "Missing required field: {$field}", 'code' => 'validation'];
            }
        }

        // Validate dates
        $start = strtotime($data->start_time);
        $end = strtotime($data->end_time);

        if ($start === false || $end === false) {
            return ['error' => 'Invalid date format', 'code' => 'validation'];
        }

        if ($start >= $end) {
            return ['error' => 'End time must be after start time', 'code' => 'validation'];
        }

        if ($start < time()) {
            return ['error' => 'Start time cannot be in the past', 'code' => 'validation'];
        }

        // Check if space exists and is active
        $space = $this->spaceDAO->getById($data->space_id);
        if (!$space) {
            return ['error' => 'Space not found', 'code' => 'not_found'];
        }

        if ($space['status'] !== 'active') {
            return ['error' => 'Space is not available for booking', 'code' => 'unavailable'];
        }

        // Cannot book own space
        if ($space['owner_id'] == $userId) {
            return ['error' => 'You cannot book your own space', 'code' => 'validation'];
        }

        // Check availability
        if (!$this->spaceDAO->isAvailable($data->space_id, $data->start_time, $data->end_time)) {
            return ['error' => 'Space is not available for the selected time', 'code' => 'unavailable'];
        }

        // Create booking
        $bookingData = (array)$data;
        $bookingData['renter_id'] = $userId;

        $result = $this->bookingDAO->create($bookingData);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Booking created successfully',
                'booking_id' => $result['booking_id'],
                'total_price' => $result['total_price'],
                'platform_fee' => $result['platform_fee'],
                'hours' => $result['hours']
            ];
        }

        return ['error' => $result['error'] ?? 'Failed to create booking'];
    }

    /**
     * Get bookings for a user (as renter)
     *
     * @param int $userId User ID
     * @param string|null $status Status filter
     * @return array Response
     */
    public function getMyBookings(int $userId, ?string $status = null): array
    {
        $bookings = $this->bookingDAO->getByRenter($userId, $status);

        return [
            'success' => true,
            'bookings' => $bookings,
            'count' => count($bookings)
        ];
    }

    /**
     * Get bookings on owner's spaces
     *
     * @param int $userId Owner user ID
     * @param string|null $status Status filter
     * @return array Response
     */
    public function getSpaceBookings(int $userId, ?string $status = null): array
    {
        $bookings = $this->bookingDAO->getByOwner($userId, $status);

        return [
            'success' => true,
            'bookings' => $bookings,
            'count' => count($bookings)
        ];
    }

    /**
     * Cancel a booking
     *
     * @param int $bookingId Booking ID
     * @param int $userId User ID
     * @param string|null $reason Cancellation reason
     * @return array Response
     */
    public function cancelBooking(int $bookingId, int $userId, ?string $reason = null): array
    {
        $result = $this->bookingDAO->cancel($bookingId, $userId, $reason);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Booking cancelled',
                'refund_amount' => $result['refund_amount'] ?? 0,
                'refund_eligible' => $result['refund_eligible'] ?? false
            ];
        }

        return ['error' => $result['error'] ?? 'Failed to cancel booking'];
    }

    /**
     * Calculate price for a potential booking
     *
     * @param int $spaceId Space ID
     * @param string $startTime Start datetime
     * @param string $endTime End datetime
     * @return array Price breakdown
     */
    public function calculatePrice(int $spaceId, string $startTime, string $endTime): array
    {
        $price = $this->bookingDAO->calculateBookingPrice($spaceId, $startTime, $endTime);

        if (isset($price['error'])) {
            return ['error' => $price['error']];
        }

        return [
            'success' => true,
            'price' => $price
        ];
    }

    /**
     * Delete a space listing
     *
     * @param int $spaceId Space ID
     * @param int $userId Owner user ID
     * @return array Response
     */
    public function delete(int $spaceId, int $userId): array
    {
        $result = $this->spaceDAO->delete($spaceId, $userId);

        if ($result['success']) {
            return ['success' => true, 'message' => 'Space deleted successfully'];
        }

        return ['error' => $result['error'] ?? 'Failed to delete space'];
    }

    /**
     * Pause/resume a space listing
     *
     * @param int $spaceId Space ID
     * @param int $userId Owner user ID
     * @param bool $pause True to pause, false to resume
     * @return array Response
     */
    public function togglePause(int $spaceId, int $userId, bool $pause): array
    {
        $status = $pause ? 'paused' : 'active';
        $result = $this->spaceDAO->update($spaceId, $userId, ['status' => $status]);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => $pause ? 'Space paused' : 'Space resumed',
                'status' => $status
            ];
        }

        return ['error' => $result['error'] ?? 'Failed to update space status'];
    }

    /**
     * Upload photos for a space listing
     *
     * Uploads to Azure Blob Storage if configured, otherwise uses local storage.
     *
     * @param array $files Uploaded files from $_FILES
     * @param int $userId Owner user ID
     * @param int|null $spaceId Optional space ID to attach photos to
     * @return array Response with photo URLs
     */
    public function uploadPhotos(array $files, int $userId, ?int $spaceId = null): array
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $maxPhotos = 6;
        $uploadedUrls = [];

        // Check if Azure Blob Storage is enabled
        $useAzure = $this->azureBlob && $this->azureBlob->isEnabled();

        // Create local upload directory (fallback or for temp files)
        $uploadDir = __DIR__ . '/../../uploads/spaces/' . $userId . '/';
        if (!$useAzure && !is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Handle both single and multiple file uploads
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;

        if ($fileCount > $maxPhotos) {
            return ['error' => "Maximum {$maxPhotos} photos allowed", 'code' => 'validation'];
        }

        for ($i = 0; $i < $fileCount; $i++) {
            $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];

            // Check for upload errors
            if ($error !== UPLOAD_ERR_OK) {
                continue;
            }

            // Validate file size
            if ($size > $maxSize) {
                return ['error' => 'File size exceeds 5MB limit', 'code' => 'validation'];
            }

            // Validate file type using finfo
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($tmpName);

            if (!in_array($mimeType, $allowedTypes)) {
                return ['error' => 'Invalid file type. Only JPEG, PNG, and WebP allowed', 'code' => 'validation'];
            }

            // Generate unique filename
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $newFilename = 'space_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . strtolower($extension);

            if ($useAzure) {
                // Upload to Azure Blob Storage
                $blobPath = $userId . '/' . $newFilename;
                $result = $this->azureBlob->uploadFile(
                    $tmpName,
                    $blobPath,
                    AZURE_CONTAINER_SPACE_PHOTOS,
                    $mimeType
                );

                if ($result['success']) {
                    $uploadedUrls[] = $result['url'];
                }
            } else {
                // Fallback to local storage
                $targetPath = $uploadDir . $newFilename;
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $uploadedUrls[] = '/uploads/spaces/' . $userId . '/' . $newFilename;
                }
            }
        }

        if (empty($uploadedUrls)) {
            return ['error' => 'No files were uploaded', 'code' => 'upload_failed'];
        }

        // If spaceId provided, update the space's photos
        if ($spaceId) {
            $space = $this->spaceDAO->getById($spaceId);
            if ($space && $space['owner_id'] == $userId) {
                $existingPhotos = is_array($space['photos']) ? $space['photos'] : [];
                $allPhotos = array_merge($existingPhotos, $uploadedUrls);

                // Limit to max photos
                $allPhotos = array_slice($allPhotos, 0, $maxPhotos);

                $this->spaceDAO->update($spaceId, $userId, ['photos' => $allPhotos]);
            }
        }

        return [
            'success' => true,
            'photos' => $uploadedUrls,
            'message' => count($uploadedUrls) . ' photo(s) uploaded successfully',
            'storage' => $useAzure ? 'azure' : 'local'
        ];
    }

    /**
     * Delete a photo from a space listing
     *
     * Deletes from Azure Blob Storage if it's an Azure URL, otherwise from local storage.
     *
     * @param int $spaceId Space ID
     * @param int $userId Owner user ID
     * @param string $photoUrl Photo URL to delete
     * @return array Response
     */
    public function deletePhoto(int $spaceId, int $userId, string $photoUrl): array
    {
        $space = $this->spaceDAO->getById($spaceId);

        if (!$space) {
            return ['error' => 'Space not found', 'code' => 'not_found'];
        }

        if ($space['owner_id'] != $userId) {
            return ['error' => 'Access denied', 'code' => 'forbidden'];
        }

        $photos = is_array($space['photos']) ? $space['photos'] : [];
        $photoIndex = array_search($photoUrl, $photos);

        if ($photoIndex === false) {
            return ['error' => 'Photo not found', 'code' => 'not_found'];
        }

        // Remove from array
        array_splice($photos, $photoIndex, 1);

        // Update space
        $this->spaceDAO->update($spaceId, $userId, ['photos' => $photos]);

        // Delete the actual file
        if (AzureBlobService::isAzureUrl($photoUrl)) {
            // Delete from Azure Blob Storage
            if ($this->azureBlob && $this->azureBlob->isEnabled()) {
                $blobName = AzureBlobService::extractBlobNameFromUrl($photoUrl);
                if ($blobName) {
                    $this->azureBlob->deleteFile($blobName);
                }
            }
        } else {
            // Delete local file
            $filePath = __DIR__ . '/../../' . ltrim($photoUrl, '/');
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        return [
            'success' => true,
            'message' => 'Photo deleted successfully',
            'photos' => $photos
        ];
    }

    /**
     * Get earnings breakdown by space
     *
     * @param int $userId Owner user ID
     * @return array Earnings per space
     */
    public function getEarningsBySpace(int $userId): array
    {
        return $this->spaceDAO->getEarningsBySpace($userId);
    }

    /**
     * Get earnings breakdown by period
     *
     * @param int $userId Owner user ID
     * @param string $period Period type (week, month, year)
     * @return array Earnings by period
     */
    public function getEarningsByPeriod(int $userId, string $period = 'month'): array
    {
        return $this->spaceDAO->getEarningsByPeriod($userId, $period);
    }

    /**
     * Get payout history
     *
     * @param int $userId Owner user ID
     * @param string|null $status Filter by status
     * @return array Payout records
     */
    public function getPayoutHistory(int $userId, ?string $status = null): array
    {
        return $this->spaceDAO->getPayoutHistory($userId, $status);
    }

    /**
     * Get owner's booking history with details
     *
     * @param int $userId Owner user ID
     * @param int $limit Number of records
     * @param int $offset Pagination offset
     * @return array Booking records
     */
    public function getOwnerBookingHistory(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->spaceDAO->getOwnerBookingHistory($userId, $limit, $offset);
    }

    /**
     * Get gallery spaces with photos
     *
     * @param int $limit Number of spaces
     * @param int $offset Pagination offset
     * @param string|null $since Timestamp for polling
     * @return array Spaces with photos
     */
    public function getGallerySpaces(int $limit = 20, int $offset = 0, ?string $since = null): array
    {
        return $this->spaceDAO->getGallerySpaces($limit, $offset, $since);
    }
}
