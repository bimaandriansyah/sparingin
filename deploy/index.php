<?php

/*
 * public_html/index.php for shared hosting.
 * The app lives one level up in ../sparingin (not web-accessible); this folder is the web root.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$appRoot = __DIR__.'/../sparingin';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $appRoot.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $appRoot.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $appRoot.'/bootstrap/app.php';

// public_path() must point here (manifest.webmanifest, build/, storage/ uploads).
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
