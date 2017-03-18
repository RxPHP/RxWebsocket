<?php

use Rx\Observable;

require_once __DIR__ . '/../bootstrap.php';

$timerObservable = Observable::emptyObservable();

if ($argc > 1 && is_numeric($argv[1])) {
    echo "Setting test server to stop in " . $argv[1] . " seconds.\n";
    $timerObservable = Observable::timer(1000 * $argv[1]);
}

$server = new \Rx\Websocket\Server("127.0.0.1", 9001, true);

$server
    ->takeUntil($timerObservable)
    ->subscribe(function (\Rx\Websocket\MessageSubject $ms) {
        $ms->subscribe($ms);
    });
