<?php

namespace Rx\Websocket\Test;

use React\EventLoop\Factory;
use Rx\Websocket\Client;
use Rx\Websocket\MessageSubject;
use Rx\Websocket\Server;

class ClientTest extends \PHPUnit_Framework_TestCase
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

    public function testRequestEndOnDispose()
    {
        $this->markTestSkipped();
        $loop = Factory::create();

        $server = new Server('tcp://127.0.0.1:1234', false, [], $loop);
        $serverDisp = $server->subscribe(function (MessageSubject $ms) {
            $ms->map('strrev')->subscribe($ms);
        });

        $value = null;

        $client = new Client('ws://127.0.0.1:1234/', false, [], $loop);
        $client
            ->subscribe(function (MessageSubject $ms) use ($serverDisp) {
                $ms->onNext('Hello');
                $ms
                    ->finally(function () use ($serverDisp) {
                        $serverDisp->dispose();
                    })
                    ->take(1)
                    ->subscribe(function ($x) use (&$value) {
                        $this->assertNull($value);
                        $value = $x;
                    });
            });

        $loop->run();

        $this->assertEquals('olleH', $value);
    }
}
