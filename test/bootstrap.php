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
        break;
    }
}

Rx\Scheduler::setDefaultFactory(function () { return new \Rx\Scheduler\EventLoopScheduler(\React\EventLoop\Loop::get()); });
