<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH.'/vendor/autoload.php';

// Load environment variables
if (file_exists(BASE_PATH.'/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->load();
}

use Phast\Framework;

// Bootstrap framework
$framework = new Framework;

// Get web entrypoint from container (routes are already loaded by RouterProvider)
$entrypoint = $framework->getWebEntrypoint();

// Handle the request
$entrypoint->handle();
