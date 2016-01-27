<?php

namespace Voryx\RxWebsocket\Test\Functional;

use Voryx\RxWebsocket\Client;
use Voryx\RxWebsocket\Server;
use Voryx\RxWebsocket\Test\TestCase;

class FunctionalTestCase extends TestCase
{
    public function testSubProtocolMatch()
    {
        $server = new Server("127.0.0.1", 61234, true, ['test.subprotocol']);

        $client = new Client("ws://127.0.0.1:61234/", true);
    }
}
