<?php
declare(strict_types=1);

use Slim\Factory\AppFactory;
use App\Services\Database;
use App\Models\BaseModel;

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Ensure Composer autoloader
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloader)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Dependencies missing. Run composer install.']);
    exit;
}
require $autoloader;

// Initialize database connection
$db = Database::get();
BaseModel::setDatabase($db);

// Create Slim App
$app = AppFactory::create();

// Add Body Parsing Middleware
$app->addBodyParsingMiddleware();

// Add Routing Middleware
$app->addRoutingMiddleware();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Load app routes
require __DIR__ . '/../src/Routes/api.php';

// Run app
$app->run();