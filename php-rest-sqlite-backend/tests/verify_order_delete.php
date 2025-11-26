<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Controllers\OrderController;
use App\Services\Database;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\ResponseFactory;

// Setup
$config = require __DIR__ . '/../protected/config/config.php';
$dbPath = $config['database']['database_path'];
echo "DB Path: $dbPath\n";

if (!file_exists($dbPath)) {
    echo "ERROR: DB file not found!\n";
    exit(1);
}

try {
    // Use raw PDO to avoid Database::get singleton issues in test context if any
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected.\n";

    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables: " . implode(', ', $tables) . "\n";

    // Helper to create a test user
    function createTestUser($db, $role)
    {
        $email = 'test_' . $role . '_' . uniqid() . '@example.com';
        $stmt = $db->prepare("INSERT INTO users (email, password_hash, role, created_at) VALUES (:email, 'hash', :role, datetime('now'))");
        $stmt->execute([':email' => $email, ':role' => $role]);
        return $db->lastInsertId();
    }

    // Helper to create a test order
    function createTestOrder($db, $userId)
    {
        $orderNumber = 'TEST-' . uniqid();
        $stmt = $db->prepare("INSERT INTO orders (user_id, order_number, shipping_address, subtotal, total, created_at) VALUES (:user_id, :order_number, '123 Test St', 100, 100, datetime('now'))");
        $stmt->execute([':user_id' => $userId, ':order_number' => $orderNumber]);
        $orderId = $db->lastInsertId();

        // Add item
        $db->prepare("INSERT INTO order_items (order_id, product_id, store_id, quantity, unit_price, subtotal) VALUES (:order_id, 1, 1, 1, 100, 100)")->execute([':order_id' => $orderId]);

        return $orderId;
    }

    $adminId = createTestUser($db, 'admin');
    $employeeId = createTestUser($db, 'employee');

    echo "Admin ID: $adminId\n";
    echo "Employee ID: $employeeId\n";

    $controller = new OrderController($db);
    $responseFactory = new ResponseFactory();

    // Test 1: Employee tries to delete (Should fail)
    echo "\nTest 1: Employee deletion attempt\n";
    $orderId1 = createTestOrder($db, $employeeId);
    $request = (new ServerRequestFactory())->createServerRequest('DELETE', '/orders/' . $orderId1);
    $request = $request->withAttribute('user_id', $employeeId);
    $response = $controller->delete($request, $responseFactory->createResponse(), ['id' => $orderId1]);

    echo "Status: " . $response->getStatusCode() . "\n";
    if ($response->getStatusCode() === 403) {
        echo "PASS: Employee denied\n";
    } else {
        echo "FAIL: Employee not denied\n";
    }

    // Test 2: Admin tries to delete (Should succeed)
    echo "\nTest 2: Admin deletion attempt\n";
    $orderId2 = createTestOrder($db, $adminId);
    $request = (new ServerRequestFactory())->createServerRequest('DELETE', '/orders/' . $orderId2);
    $request = $request->withAttribute('user_id', $adminId);
    $response = $controller->delete($request, $responseFactory->createResponse(), ['id' => $orderId2]);

    echo "Status: " . $response->getStatusCode() . "\n";
    if ($response->getStatusCode() === 204) {
        echo "PASS: Admin allowed\n";

        // Verify deletion
        $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE id = :id");
        $stmt->execute([':id' => $orderId2]);
        if ($stmt->fetchColumn() == 0) {
            echo "PASS: Order record deleted\n";
        } else {
            echo "FAIL: Order record still exists\n";
        }
    } else {
        echo "FAIL: Admin failed to delete\n";
        echo "Body: " . (string) $response->getBody() . "\n";
    }

    // Cleanup
    $db->exec("DELETE FROM users WHERE id IN ($adminId, $employeeId)");
    if (isset($orderId1))
        $db->exec("DELETE FROM orders WHERE id = $orderId1");

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
