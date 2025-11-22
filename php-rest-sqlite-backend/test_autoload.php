<?php
require __DIR__ . '/vendor/autoload.php';
echo 'vendor/autoload.php loaded' . PHP_EOL;
var_dump(class_exists('App\\Services\\Database', true));
try {
    $cls = new \ReflectionClass('App\\Services\\Database');
    echo "Reflection OK\n";
} catch (Throwable $e) {
    echo "Reflection failed: " . $e->getMessage() . PHP_EOL;
}