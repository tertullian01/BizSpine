<?php
declare(strict_types=1);

// Direct API handler - bypasses .htaccess issues
header('Access-Control-Allow-Origin: https://test.nakednettle.com');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Parse the request path
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove /api.php prefix if present
$path = str_replace('/api.php', '', $path);
$path = $path ?: '/';

// Simple routing
switch ($path) {
    case '/cors-test':
        echo json_encode([
            'success' => true,
            'data' => [
                'message' => 'CORS test successful',
                'method' => $_SERVER['REQUEST_METHOD'],
                'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none'
            ]
        ]);
        break;

    case '/auth/register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            // Simple validation
            if (isset($input['email']) && isset($input['password'])) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'message' => 'Registration successful',
                        'email' => $input['email']
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Email and password required'
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Route not found: ' . $path
        ]);
        break;
}