<?php
/**
 * API Proxy
 *
 * This file proxies API requests to the main api/index.php when
 * the public folder is the document root (e.g., in XAMPP).
 * In Docker, the Apache alias handles this directly.
 */

// Forward all requests to the actual API
require_once __DIR__ . '/../../api/index.php';
