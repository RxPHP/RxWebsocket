<?php

/**
 * Find the auto loader file
 */
$files = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $loader = require $file;
        $loader->addPsr4('Rx\\Websocket\\Test\\', __DIR__);
        break;
    }
}
