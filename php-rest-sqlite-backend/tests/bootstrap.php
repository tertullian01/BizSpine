<?php

// Bootstrap file for PHPUnit tests
require_once dirname(__DIR__) . '/vendor/autoload.php';
// Set up test environment
putenv('JWT_SECRET=test-secret-key');
