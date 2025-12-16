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
// This performs some common setup tasks that should affect all paths through
// the application:
// - Web requests
// - CLI tools
// - Automated tests
// As such, this bootstrap should make no assumptions about the runtime
// - Composer/autoloading

// In the future, this will handle other common tasks.
// - Server/environment checks
// - Error handling
// - App config/service container


/**
 * Future scope:

// Make paths relative to the repo root (there's probably a _lot_ of code that
// this would break)
chdir(__DIR__);

// Set some reasonable defaults around error handling and logging
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
    // Load dotenv. This should only really be used for dev environments (hence
    // being conditionally included, though perhaps it makes sense for all envs
    // for OpenEMR). See current usage in interface/globals.php.
}

// Initialize and make accessible a DI/service container
// $config = require 'some-file-providing-service-container.php';

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
