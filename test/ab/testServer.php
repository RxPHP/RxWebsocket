<?php

require_once __DIR__ . '/../bootstrap.php';

if ($argc > 1 && is_numeric($argv[1])) {
    echo "Setting test server to stop in " . $argv[1] . " seconds.\n";
    \EventLoop\addTimer($argv[1], function () {
        exit;
    });
}

$server = new \Rx\Websocket\Server("127.0.0.1", 9001, true);

$server->subscribe(new \Rx\Observer\CallbackObserver(
    function (\Rx\Websocket\MessageSubject $ms) {
        $ms->subscribe($ms);
    }
));
