<?php

namespace Rx\Websocket\Test;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Ratchet\RFC6455\Messaging\Frame;
use Rx\Exception\TimeoutException;
use Rx\Observer\CallbackObserver;
use Rx\Subject\Subject;
use Rx\Testing\MockObserver;
use Rx\Websocket\MessageSubject;
use Rx\Websocket\WebsocketErrorException;

class MessageSubjectTest extends TestCase
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

    public function testPingPongTimeout()
    {
        $dataIn = $this->createHotObservable([
            onNext(200, (new Frame('', true, Frame::OP_TEXT))->getContents()),
            onNext(205, (new Frame('', true, Frame::OP_TEXT))->getContents()),
        ]);

        $dataOut = new Subject();

        $ms = new MessageSubject(
            $dataIn,
            $dataOut,
            true,
            false,
            '',
            new Request('GET', '/ws'),
            new Response(),
            300
        );

        $result = $this->scheduler->startWithCreate(function () use ($dataOut) {
            return $dataOut->map(function (Frame $frame) {
                return $frame->getContents();
            });
        });

        $this->assertMessages([
            onNext(650, (new Frame('', true, Frame::OP_PING))->getContents()),
            onError(950, new TimeoutException())
        ], $result->getMessages());
    }

    public function testPingPong()
    {
        $dataIn = $this->createHotObservable([
            onNext(200, (new Frame('', true, Frame::OP_TEXT))->getContents()),
            onNext(205, (new Frame('', true, Frame::OP_TEXT))->getContents()),
            onNext(651, (new Frame('', true, Frame::OP_PONG))->getContents())
        ]);

        $dataOut = new Subject();

        $ms = new MessageSubject(
            $dataIn,
            $dataOut,
            true,
            false,
            '',
            new Request('GET', '/ws'),
            new Response(),
            300
        );

        $result = $this->scheduler->startWithDispose(function () use ($dataOut) {
            return $dataOut->map(function (Frame $frame) {
                return $frame->getContents();
            });
        }, 2000);

        $this->assertMessages([
            onNext(650, (new Frame('', true, Frame::OP_PING))->getContents()),
            onNext(951, (new Frame('', true, Frame::OP_PING))->getContents()),
            onError(1251, new TimeoutException())
        ], $result->getMessages());
    }

    public function testPingPongDataSuppressesPing()
    {
        $dataIn = $this->createHotObservable([
            onNext(201, (new Frame('', true, Frame::OP_TEXT))->getContents()),
            onNext(205, (new Frame('', true, Frame::OP_TEXT))->getContents()),
            onNext(649, (new Frame('', true, Frame::OP_TEXT))->getContents())
        ]);

        $dataOut = new Subject();

        $ms = new MessageSubject(
            $dataIn,
            $dataOut,
            true,
            false,
            '',
            new Request('GET', '/ws'),
            new Response(),
            300
        );

        $result = $this->scheduler->startWithDispose(function () use ($dataOut) {
            return $dataOut->map(function (Frame $frame) {
                return $frame->getContents();
            });
        }, 2000);

        $this->assertMessages([
            onNext(949, (new Frame('', true, Frame::OP_PING))->getContents()),
            onError(1249, new TimeoutException())
        ], $result->getMessages());
    }

    public function testDisposeOnMessageSubjectClosesConnection()
    {
        $dataIn = $this->createHotObservable([
            onNext(201, (new Frame('', true, Frame::OP_TEXT))->getContents()),
            onNext(205, (new Frame('', true, Frame::OP_TEXT))->getContents()),
        ]);

        $dataOut = new MockObserver($this->scheduler);

        $ms = new MessageSubject(
            $dataIn,
            $dataOut,
            true,
            false,
            '',
            new Request('GET', '/ws'),
            new Response(),
            300
        );

        $result = $this->scheduler->startWithDispose(function () use ($ms) {
            return $ms;
        }, 300);

        $this->assertMessages([
            onNext(201, ''),
            onNext(205, ''),
        ], $result->getMessages());

        $this->assertSubscriptions([
            subscribe(0,300)
        ], $dataIn->getSubscriptions());

        $this->assertMessages([
            onCompleted(300)
        ], $dataOut->getMessages());
    }
}