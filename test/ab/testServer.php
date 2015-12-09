<?php

require_once __DIR__ . '/../bootstrap.php';

$server = new \Voryx\RxWebsocket\Server("127.0.0.1", 9001, true);

$server->subscribe(new \Rx\Observer\CallbackObserver(
    function (\Voryx\RxWebsocket\MessageSubject $ms) {
        $ms->subscribe($ms);
    }
));
