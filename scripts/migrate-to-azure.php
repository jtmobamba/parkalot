<?php
/**
 * Azure Migration Script
 *
 * Migrates existing local files to Azure Blob Storage and updates database URLs.
 *
 * Usage:
 *   php scripts/migrate-to-azure.php [--dry-run] [--cv-only] [--photos-only] [--verify]
 *
 * Options:
 *   --dry-run     Show what would be migrated without actually migrating
 *   --cv-only     Only migrate CV files
 *   --photos-only Only migrate space photos
 *   --verify      Verify migration integrity after completion
 *   --rollback    Rollback the last migration (restore local URLs in database)
 */

// Ensure this is run from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/azure.php';
require_once __DIR__ . '/../app/services/AzureBlobService.php';

// Parse command line arguments
$options = getopt('', ['dry-run', 'cv-only', 'photos-only', 'verify', 'rollback', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
Azure Migration Script - Migrate local files to Azure Blob Storage

Usage:
  php scripts/migrate-to-azure.php [options]

Options:
  --dry-run       Show what would be migrated without actually doing it
  --cv-only       Only migrate CV files from job applications
  --photos-only   Only migrate space photos
  --verify        Verify all migrated files are accessible
  --rollback      Rollback last migration (restore local URLs in database)
  --help          Show this help message

Examples:
  php scripts/migrate-to-azure.php --dry-run
  php scripts/migrate-to-azure.php --cv-only
  php scripts/migrate-to-azure.php --verify

HELP;
    exit(0);
}

$dryRun = isset($options['dry-run']);
$cvOnly = isset($options['cv-only']);
$photosOnly = isset($options['photos-only']);
$verify = isset($options['verify']);
$rollback = isset($options['rollback']);

// Logging functions
function logInfo(string $message): void {
    echo "[INFO] " . date('Y-m-d H:i:s') . " - {$message}\n";
}

function logSuccess(string $message): void {
    echo "[SUCCESS] " . date('Y-m-d H:i:s') . " - {$message}\n";
}

function logWarning(string $message): void {
    echo "[WARNING] " . date('Y-m-d H:i:s') . " - {$message}\n";
}

function logError(string $message): void {
    echo "[ERROR] " . date('Y-m-d H:i:s') . " - {$message}\n";
}

// Main migration class
class AzureMigration {
    private PDO $db;
    private AzureBlobService $azure;
    private bool $dryRun;
    private string $baseDir;
    private array $migrationLog = [];
    private string $logFile;

    public function __construct(bool $dryRun = false) {
        $this->db = Database::connect();
        $this->azure = new AzureBlobService();
        $this->dryRun = $dryRun;
        $this->baseDir = realpath(__DIR__ . '/..');
        $this->logFile = $this->baseDir . '/logs/migration_' . date('Y-m-d_His') . '.json';

        // Create logs directory if needed
        $logsDir = dirname($this->logFile);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
    }

    /**
     * Check prerequisites before migration
     */
    public function checkPrerequisites(): bool {
        logInfo("Checking prerequisites...");

        // Check Azure configuration
        if (!$this->azure->isEnabled()) {
            logError("Azure Blob Storage is not configured. Please set AZURE_STORAGE_ENABLED=true and provide credentials.");
            return false;
        }

        // Check database connection
        try {
            $this->db->query("SELECT 1");
            logSuccess("Database connection OK");
        } catch (Exception $e) {
            logError("Database connection failed: " . $e->getMessage());
            return false;
        }

        // Check local directories exist
        $cvDir = $this->baseDir . '/uploads/cv';
        $spacesDir = $this->baseDir . '/uploads/spaces';

        if (!is_dir($cvDir) && !is_dir($spacesDir)) {
            logWarning("No local upload directories found. Nothing to migrate.");
        }

        logSuccess("Prerequisites check passed");
        return true;
    }

    /**
     * Migrate CV files to Azure
     */
    public function migrateCVFiles(): array {
        logInfo("Starting CV file migration...");

        $results = [
            'total' => 0,
            'migrated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'files' => []
        ];

        // Get all CV file paths from database
        $stmt = $this->db->query("
            SELECT application_id, cv_file_path
            FROM job_applications
            WHERE cv_file_path IS NOT NULL
              AND cv_file_path NOT LIKE 'https://%'
        ");
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results['total'] = count($applications);
        logInfo("Found {$results['total']} CV files to migrate");

        foreach ($applications as $app) {
            $localPath = $this->baseDir . '/' . ltrim($app['cv_file_path'], '/');
            $filename = basename($app['cv_file_path']);

            if (!file_exists($localPath)) {
                logWarning("File not found: {$localPath}");
                $results['skipped']++;
                continue;
            }

            if ($this->dryRun) {
                logInfo("[DRY RUN] Would migrate: {$app['cv_file_path']} -> Azure");
                $results['migrated']++;
                continue;
            }

            // Upload to Azure
            $result = $this->azure->uploadFile(
                $localPath,
                $filename,
                AZURE_CONTAINER_CV_FILES
            );

            if ($result['success']) {
                // Update database with new URL
                $updateStmt = $this->db->prepare("
                    UPDATE job_applications
                    SET cv_file_path = ?
                    WHERE application_id = ?
                ");
                $updateStmt->execute([$result['url'], $app['application_id']]);

                $this->migrationLog[] = [
                    'type' => 'cv',
                    'id' => $app['application_id'],
                    'old_path' => $app['cv_file_path'],
                    'new_url' => $result['url'],
                    'local_file' => $localPath
                ];

                $results['files'][] = [
                    'old' => $app['cv_file_path'],
                    'new' => $result['url']
                ];

                logSuccess("Migrated CV: {$filename}");
                $results['migrated']++;
            } else {
                logError("Failed to migrate {$filename}: " . ($result['error'] ?? 'Unknown error'));
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Migrate space photos to Azure
     */
    public function migrateSpacePhotos(): array {
        logInfo("Starting space photos migration...");

        $results = [
            'total' => 0,
            'migrated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'spaces_updated' => 0
        ];

        // Get all spaces with local photos
        $stmt = $this->db->query("
            SELECT space_id, owner_id, photos
            FROM customer_spaces
            WHERE photos IS NOT NULL
              AND photos != '[]'
              AND photos NOT LIKE '%blob.core.windows.net%'
        ");
        $spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

        logInfo("Found " . count($spaces) . " spaces with photos to migrate");

        foreach ($spaces as $space) {
            $photos = json_decode($space['photos'], true);

            if (!is_array($photos) || empty($photos)) {
                continue;
            }

            $newPhotos = [];
            $spaceMigrated = false;

            foreach ($photos as $photoUrl) {
                // Skip if already an Azure URL
                if (strpos($photoUrl, 'blob.core.windows.net') !== false) {
                    $newPhotos[] = $photoUrl;
                    continue;
                }

                $results['total']++;
                $localPath = $this->baseDir . '/' . ltrim($photoUrl, '/');
                $filename = basename($photoUrl);

                if (!file_exists($localPath)) {
                    logWarning("Photo not found: {$localPath}");
                    $results['skipped']++;
                    $newPhotos[] = $photoUrl; // Keep old URL
                    continue;
                }

                if ($this->dryRun) {
                    logInfo("[DRY RUN] Would migrate: {$photoUrl} -> Azure");
                    $newPhotos[] = $photoUrl;
                    $results['migrated']++;
                    continue;
                }

                // Upload to Azure with user folder structure
                $blobPath = $space['owner_id'] . '/' . $filename;
                $result = $this->azure->uploadFile(
                    $localPath,
                    $blobPath,
                    AZURE_CONTAINER_SPACE_PHOTOS
                );

                if ($result['success']) {
                    $newPhotos[] = $result['url'];
                    $spaceMigrated = true;
                    $results['migrated']++;

                    $this->migrationLog[] = [
                        'type' => 'photo',
                        'space_id' => $space['space_id'],
                        'old_path' => $photoUrl,
                        'new_url' => $result['url'],
                        'local_file' => $localPath
                    ];

                    logSuccess("Migrated photo: {$filename}");
                } else {
                    logError("Failed to migrate {$filename}: " . ($result['error'] ?? 'Unknown error'));
                    $newPhotos[] = $photoUrl; // Keep old URL on failure
                    $results['failed']++;
                }
            }

            // Update space with new photo URLs
            if ($spaceMigrated && !$this->dryRun) {
                $updateStmt = $this->db->prepare("
                    UPDATE customer_spaces
                    SET photos = ?
                    WHERE space_id = ?
                ");
                $updateStmt->execute([json_encode($newPhotos), $space['space_id']]);
                $results['spaces_updated']++;
            }
        }

        return $results;
    }

    /**
     * Verify migrated files are accessible
     */
    public function verifyMigration(): array {
        logInfo("Verifying migration...");

        $results = [
            'cv_checked' => 0,
            'cv_accessible' => 0,
            'photos_checked' => 0,
            'photos_accessible' => 0,
            'errors' => []
        ];

        // Check CV files
        $stmt = $this->db->query("
            SELECT application_id, cv_file_path
            FROM job_applications
            WHERE cv_file_path LIKE 'https://%blob.core.windows.net%'
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $results['cv_checked']++;
            $blobName = AzureBlobService::extractBlobNameFromUrl($row['cv_file_path']);

            if ($blobName && $this->azure->exists($blobName)) {
                $results['cv_accessible']++;
            } else {
                $results['errors'][] = "CV not accessible: {$row['cv_file_path']}";
            }
        }

        // Check space photos
        $stmt = $this->db->query("
            SELECT space_id, photos
            FROM customer_spaces
            WHERE photos LIKE '%blob.core.windows.net%'
        ");

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $photos = json_decode($row['photos'], true);

            if (!is_array($photos)) {
                continue;
            }

            foreach ($photos as $photoUrl) {
                if (strpos($photoUrl, 'blob.core.windows.net') === false) {
                    continue;
                }

                $results['photos_checked']++;
                $blobName = AzureBlobService::extractBlobNameFromUrl($photoUrl);

                if ($blobName && $this->azure->exists($blobName)) {
                    $results['photos_accessible']++;
                } else {
                    $results['errors'][] = "Photo not accessible: {$photoUrl}";
                }
            }
        }

        return $results;
    }

    /**
     * Rollback migration using the log file
     */
    public function rollback(?string $logFile = null): bool {
        if (!$logFile) {
            // Find the most recent migration log
            $logsDir = $this->baseDir . '/logs';
            $files = glob($logsDir . '/migration_*.json');

            if (empty($files)) {
                logError("No migration logs found to rollback");
                return false;
            }

            rsort($files);
            $logFile = $files[0];
        }

        if (!file_exists($logFile)) {
            logError("Migration log not found: {$logFile}");
            return false;
        }

        logInfo("Rolling back migration from: {$logFile}");

        $log = json_decode(file_get_contents($logFile), true);

        if (!$log || empty($log)) {
            logError("Invalid or empty migration log");
            return false;
        }

        $rolledBack = 0;

        foreach ($log as $entry) {
            if ($entry['type'] === 'cv') {
                $stmt = $this->db->prepare("
                    UPDATE job_applications
                    SET cv_file_path = ?
                    WHERE application_id = ?
                ");
                $stmt->execute([$entry['old_path'], $entry['id']]);
                $rolledBack++;
            } elseif ($entry['type'] === 'photo') {
                // For photos, we need to rebuild the photos array
                // This is more complex, so we'll just log it
                logWarning("Photo rollback requires manual intervention: space_id={$entry['space_id']}");
            }
        }

        logSuccess("Rolled back {$rolledBack} CV entries");
        return true;
    }

    /**
     * Save migration log to file
     */
    public function saveMigrationLog(): void {
        if (!$this->dryRun && !empty($this->migrationLog)) {
            file_put_contents($this->logFile, json_encode($this->migrationLog, JSON_PRETTY_PRINT));
            logInfo("Migration log saved to: {$this->logFile}");
        }
    }

    /**
     * Get migration summary
     */
    public function getSummary(): array {
        return [
            'migrated_count' => count($this->migrationLog),
            'log_file' => $this->logFile
        ];
    }
}

// ============================================
// Main Execution
// ============================================

echo "\n";
echo "========================================\n";
echo "  ParkaLot Azure Migration Tool\n";
echo "========================================\n\n";

if ($dryRun) {
    logInfo("Running in DRY RUN mode - no changes will be made");
}

$migration = new AzureMigration($dryRun);

// Check prerequisites
if (!$migration->checkPrerequisites()) {
    exit(1);
}

// Handle rollback
if ($rollback) {
    if ($migration->rollback()) {
        logSuccess("Rollback completed");
    } else {
        logError("Rollback failed");
        exit(1);
    }
    exit(0);
}

// Handle verify
if ($verify) {
    $results = $migration->verifyMigration();

    echo "\n--- Verification Results ---\n";
    echo "CV Files:    {$results['cv_accessible']}/{$results['cv_checked']} accessible\n";
    echo "Photos:      {$results['photos_accessible']}/{$results['photos_checked']} accessible\n";

    if (!empty($results['errors'])) {
        echo "\nErrors:\n";
        foreach ($results['errors'] as $error) {
            echo "  - {$error}\n";
        }
    }
    exit(0);
}

// Run migration
$cvResults = null;
$photoResults = null;

if (!$photosOnly) {
    $cvResults = $migration->migrateCVFiles();
}

if (!$cvOnly) {
    $photoResults = $migration->migrateSpacePhotos();
}

// Save log
$migration->saveMigrationLog();

// Print summary
echo "\n";
echo "========================================\n";
echo "  Migration Summary\n";
echo "========================================\n\n";

if ($cvResults) {
    echo "CV Files:\n";
    echo "  Total:    {$cvResults['total']}\n";
    echo "  Migrated: {$cvResults['migrated']}\n";
    echo "  Skipped:  {$cvResults['skipped']}\n";
    echo "  Failed:   {$cvResults['failed']}\n\n";
}

if ($photoResults) {
    echo "Space Photos:\n";
    echo "  Total:          {$photoResults['total']}\n";
    echo "  Migrated:       {$photoResults['migrated']}\n";
    echo "  Skipped:        {$photoResults['skipped']}\n";
    echo "  Failed:         {$photoResults['failed']}\n";
    echo "  Spaces Updated: {$photoResults['spaces_updated']}\n\n";
}

$summary = $migration->getSummary();
if ($summary['migrated_count'] > 0) {
    logSuccess("Migration completed! {$summary['migrated_count']} files migrated.");
    logInfo("Log file: {$summary['log_file']}");
} elseif ($dryRun) {
    logInfo("Dry run completed - no changes made");
} else {
    logInfo("No files needed migration");
}

echo "\n";
