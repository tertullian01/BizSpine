<?php
/**
 * API bootstrap for shared hosting when the backend lives OUTSIDE public_html.
 *
 * Save as public_html/BizSpine/api/index.php
 * Pair with deploy/BizSpine-api.htaccess
 */
declare(strict_types=1);

$backendPublic = null;

// Auto-detect: ~/bizspine-backend/public next to public_html (release ZIP layout)
$home = dirname(__DIR__, 3);
$candidate = $home . DIRECTORY_SEPARATOR . 'bizspine-backend' . DIRECTORY_SEPARATOR . 'public';
if (is_file($candidate . DIRECTORY_SEPARATOR . 'index.php')) {
    $backendPublic = $candidate;
}

// Legacy layout: ~/bizspine/backend/public
if ($backendPublic === null) {
    $legacy = $home . DIRECTORY_SEPARATOR . 'bizspine' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'public';
    if (is_file($legacy . DIRECTORY_SEPARATOR . 'index.php')) {
        $backendPublic = $legacy;
    }
}

// Manual override (uncomment and set if auto-detect fails on your host)
// $backendPublic = '/home/USERNAME/bizspine-backend/public';

if ($backendPublic === null || !is_file($backendPublic . DIRECTORY_SEPARATOR . 'index.php')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'API bootstrap could not find bizspine-backend. Upload the backend folder from the release ZIP.',
    ]);
    exit;
}

// Preserve SCRIPT_NAME (/BizSpine/api/index.php) so Slim can setBasePath for subdirectory routing.
chdir($backendPublic);
require $backendPublic . DIRECTORY_SEPARATOR . 'index.php';
