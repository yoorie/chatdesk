<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

$basePath = dirname(__DIR__);
if (! file_exists($basePath.'/vendor/autoload.php')) {
    http_response_code(500);
    exit('The vendor/ folder is missing. Upload the complete package (vendor/ is included in the download).');
}

// First run: send the browser to the web installer until storage/installed exists.
if (! file_exists(__DIR__.'/../storage/installed') && PHP_SAPI !== 'cli') {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    $base = preg_replace('#/public$#', '', $base);
    header('Location: '.$base.'/install/', true, 302);
    exit;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Root .htaccess rewrites into public/, so Apache reports SCRIPT_NAME as
// /public/index.php or /{folder}/public/index.php. The browser path does not
// include that segment. Correct it before Laravel reads the request, or a
// subfolder install 404s on every page. Domain-root installs stay at /.
\App\Support\SharedHostingRequest::normalize();

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
