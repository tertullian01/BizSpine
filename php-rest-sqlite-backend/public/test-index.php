<?php
// Test if index.php framework is working
echo json_encode([
    'message' => 'Direct PHP file works',
    'time' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'uri' => $_SERVER['REQUEST_URI']
]);