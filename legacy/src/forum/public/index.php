<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../bootstrap.php';

use App\Kernel;

$kernel = new Kernel();
$response = $kernel->handle($_SERVER['REQUEST_URI']);

echo $response;
