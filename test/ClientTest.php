<?php

namespace Rx\Websocket\Test;

use React\EventLoop\Factory;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use function React\Promise\resolve;
use function RingCentral\Psr7\parse_request;

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

    public function testPassthroughOfHeaders() {
        $writtenData = '';

        $connection = $this->getMockBuilder(ConnectionInterface::class)
            ->getMock();
        $connection
            ->method('write')
            ->with($this->callback(function($data) use (&$writtenData) { $writtenData .= $data; return true;}))
            ->willReturn(true);


        $connector = $this->getMockBuilder(ConnectorInterface::class)
            ->getMock();

        $connector
            ->expects($this->once())
            ->method('connect')
            ->willReturn(resolve($connection));

        $loop = Factory::create();

        // expecting connection error
        $client = new \Rx\Websocket\Client(
            'ws://127.0.0.1:12340/',
            false,
            [],
            $loop,
            $connector,
            60000,
            [
                'X-Test-Header' => 'test header value'
            ]
        );

        $client->subscribe(
            null,
            function ($err) use (&$errored) {
                $errored = true;
            }
        );

        // This should be the Request
        $requestRaw = $writtenData;
        $request = parse_request($requestRaw);
        $this->assertEquals(['test header value'], $request->getHeader('X-Test-Header'));
    }
}
