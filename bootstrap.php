<?php

declare(strict_types=1);

// Transitional check; once there's a true front-controller this should be safe
// to remove.
if (defined('APPLICATION_BOOTSTRAP_INCLUDED')) {
    die('Do not include the bootstrap file multiple times.');
}
define('APPLICATION_BOOTSTRAP_INCLUDED', true);

// Application bootstrapping
//
// This performs some common setup tasks that should affect all requests as
// well as CLI interactions:
// - Server/environment checks
// - Composer/autoloading
// - Error handling
// - App config/service container


/**
 * Future scope:

// Make everything relative to the repo root
chdir(__DIR__);

// Set some
date_default_timezone_set('UTC');
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('error_log', '/dev/stdout'); // Docker wants logs written to stdout
ini_set('error_reporting', (string)E_ALL);
ini_set('log_errors', '1');

 */

require_once __DIR__ . '/vendor/autoload.php';

/**
 * Future scope:

if (class_exists(Dotenv\Dotenv::class)) {
    // Load dotenv. This should only really be used for dev environments,
    // though perhaps it makes sense for all envs for OpenEMR.
}

// Initialize and make accessible a DI/service container
// $config = require 'di_init.php';

// Set up error handling
$handler = $config->get(ErrorHandler::clas);
if (!$handler instanceof ErrorHandler) {
    throw new LogicException('Error handler in container is invalid');
}
set_error_handler($handler->handleError(...), E_ALL);
set_exception_handler($handler->handleException(...));

// Additional hooks for other tooling?
// - Sentry
// - Datadog
// - Remote loggers
// etc etc
 */
