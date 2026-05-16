<?php

// Bootstrap file for PHPUnit tests
require_once dirname(__DIR__) . '/vendor/autoload.php';
// Set up test environment
putenv('JWT_SECRET=test-secret-key');
$_ENV['JWT_SECRET'] = 'test-secret-key';
putenv('ALLOW_INSECURE_SETUP=false');
$_ENV['ALLOW_INSECURE_SETUP'] = 'false';
