<?php

namespace Rx\Websocket\Test\Functional;

use Rx\Websocket\Client;
use Rx\Websocket\Server;
use Rx\Websocket\Test\TestCase;

class FunctionalTestCase extends TestCase
{
    public function testSubProtocolMatch()
    {
        $server = new Server("127.0.0.1", 61234, true, ['test.subprotocol']);

        $client = new Client("ws://127.0.0.1:61234/", true);
    }
}
