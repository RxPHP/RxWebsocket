<?php

namespace Rx\Websocket\Test;

use function EventLoop\addTimer;
use function EventLoop\getLoop;
use Rx\Websocket\Client;
use Rx\Websocket\MessageSubject;
use Rx\Websocket\Server;

class ServerTest extends TestCase
{
    public function testServerShutsDown()
    {
        $server = new Server('127.0.0.1:1235');

        $serverDisp = $server->subscribe();

        addTimer(0.1, function () use ($serverDisp) {
            $serverDisp->dispose();
        });

        getLoop()->run();

        // we are making sure it is not hanging - if it gets here it worked
        $this->assertTrue(true);
    }

    public function testServerShutsDownAfterOneConnection()
    {
        $server = new Server('127.0.0.1:1236');

        $serverDisp = $server->take(1)->subscribe(
            function (MessageSubject $ms) {
                $ms->map('strrev')->subscribe($ms);
            }
        );

        $value = null;

        addTimer(0.1, function () use (&$value) {
            $client = new Client('ws://127.0.0.1:1236');
            $client
                ->flatMap(function (MessageSubject $ms) {
                    $ms->send('Hello');
                    return $ms;
                })
                ->take(1)
                ->subscribe(function ($x) use (&$value) {
                    $this->assertNull($value);
                    $value = $x;
                });
        });

        getLoop()->run();

        $this->assertEquals('olleH', $value);
    }
}