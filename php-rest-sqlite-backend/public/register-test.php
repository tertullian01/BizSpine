<?php
// Simple register test - bypasses framework
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

// Debug what method we're receiving
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

echo json_encode([
    'method_received' => $method,
    'has_input' => !empty($input),
    'input' => $input,
    'headers' => [
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'none'
    ]
]);