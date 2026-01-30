<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'message' => '#^Constructor of class Application\\\\Plugin\\\\CommonPlugin has an unused parameter \\$container\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../interface/modules/zend_modules/module/Application/src/Application/Plugin/CommonPlugin.php',
];
$ignoreErrors[] = [
    'message' => '#^Constructor of class Application\\\\Plugin\\\\Phimail has an unused parameter \\$container\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../interface/modules/zend_modules/module/Application/src/Application/Plugin/Phimail.php',
];
$ignoreErrors[] = [
    'message' => '#^Constructor of class Documents\\\\Plugin\\\\Documents has an unused parameter \\$sm\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../interface/modules/zend_modules/module/Documents/src/Documents/Plugin/Documents.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
