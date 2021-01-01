<?php

namespace Rx\Websocket\Test;

use React\EventLoop\Factory;

class ClientTest extends \PHPUnit\Framework\TestCase
{
    public function testErrorBeforeRequest()
    {
        $loop = Factory::create();

        // expecting connection error
        $client = new \Rx\Websocket\Client('ws://127.0.0.1:12340/', false, [], $loop);

        $errored = false;

        $client->subscribe(
            null,
            function ($err) use (&$errored) {
                $errored = true;
            }
        );

        $loop->run();

        $this->assertTrue($errored);
    }
}
