<?php

namespace Rx\Websocket\Test;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Ratchet\RFC6455\Messaging\Frame;
use Rx\Observer\CallbackObserver;
use Rx\Subject\Subject;
use Rx\Websocket\MessageSubject;
use Rx\Websocket\WebsocketErrorException;

class MessageSubjectTest extends \PHPUnit_Framework_TestCase
{
    public function testCloseCodeSentToOnError()
    {
        $rawDataIn = new Subject();
        $rawDataOut = new Subject();

        $ms = new MessageSubject(
            $rawDataIn,
            $rawDataOut,
            false,
            false,
            '',
            new Request('GET', ''),
            new Response()
        );

        $closeCode = 0;

        $ms->subscribe(new CallbackObserver(
            function ($x) {
                $this->fail('Was not expecting a message.');
            },
            function (\Exception $exception) use (&$closeCode) {
                $this->assertInstanceOf(WebsocketErrorException::class, $exception);

                /** @var WebsocketErrorException $exception */
                $closeCode = $exception->getCloseCode();
            },
            function () use (&$closeCode) {
                $this->fail('Was not expecting observable to complete');
            }
        ));

        $closeFrame = new Frame(pack('n', 4000), true, Frame::OP_CLOSE);
        $closeFrame->maskPayload();
        $rawDataIn->onNext($closeFrame->getContents());

        $this->assertEquals(4000, $closeCode);
    }
}