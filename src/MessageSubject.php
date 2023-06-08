<?php

namespace Rx\Websocket;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\Message;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;
use Rx\Disposable\CallbackDisposable;
use Rx\Disposable\CompositeDisposable;
use Rx\DisposableInterface;
use Rx\Exception\TimeoutException;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Subject\Subject;

class MessageSubject extends Subject
{
    protected $rawDataIn;
    protected $rawDataOut;
    protected $mask;
    protected $controlFrames;
    private $subProtocol;
    private $request;
    private $response;
    private $rawDataDisp;

    public function __construct(
        Observable $rawDataIn,
        ObserverInterface $rawDataOut,
        bool $mask,
        bool $useMessageObject,
        $subProtocol,
        RequestInterface $request,
        ResponseInterface $response,
        int $keepAlive = 60000
    ) {
        $this->request     = $request;
        $this->response    = $response;
        $this->rawDataIn   = $rawDataIn->share();
        $this->rawDataOut  = $rawDataOut;
        $this->mask        = $mask;
        $this->subProtocol = $subProtocol;

        $messageBuffer = new MessageBuffer(
            new CloseFrameChecker(),
            function (MessageInterface $msg) use ($useMessageObject) {
                parent::onNext($useMessageObject ? $msg : $msg->getPayload());
            },
            function (FrameInterface $frame) {
                switch ($frame->getOpcode()) {
                    case Frame::OP_PING:
                        $this->sendFrame(new Frame($frame->getPayload(), true, Frame::OP_PONG));
                        return;
                    case Frame::OP_CLOSE:
                        // send close frame to remote
                        $this->sendFrame($frame);

                        // get close code
                        list($closeCode) = array_merge(unpack('n*', substr($frame->getPayload(), 0, 2)));
                        if ($closeCode !== 1000) {
                            // emit close code as error
                            $exception = new WebsocketErrorException($closeCode);
                            parent::onError($exception);
                        }

                        $this->rawDataOut->onCompleted();

                        parent::onCompleted();

                        $this->rawDataDisp->dispose();
                        return;
                }
            },
            !$this->mask
        );

        // keepAlive
        $keepAliveObs = Observable::empty();
        if ($keepAlive > 0) {
            $keepAliveObs = $this->rawDataIn
                ->startWith(0)
                ->throttle($keepAlive / 2)
                ->map(function () use ($keepAlive, $rawDataOut) {
                    return Observable::timer($keepAlive)
                        ->do(function () use ($rawDataOut) {
                            $frame = new Frame('', true, Frame::OP_PING);
                            if ($this->mask) {
                                $frame->maskPayload();
                            }
                            $rawDataOut->onNext($frame->getContents());
                        })
                        ->delay($keepAlive)
                        ->do(function () use ($rawDataOut) {
                            $rawDataOut->onError(new TimeoutException());
                        });
                })
                ->switch()
                ->flatMapTo(Observable::never());
        }

        $this->rawDataDisp = $this->rawDataIn
            ->merge($keepAliveObs)
            ->subscribe(
                [$messageBuffer, 'onData'],
                parent::onError(...),
                parent::onCompleted(...)
            );

        $this->subProtocol = $subProtocol;
    }

    protected function _subscribe(ObserverInterface $observer): DisposableInterface
    {
        $disposable = new CompositeDisposable([
            parent::_subscribe($observer),
            $this->rawDataDisp,
            new CallbackDisposable([$this->rawDataOut, 'onCompleted'])
        ]);

        return $disposable;
    }

    private function createCloseFrame(int $closeCode = Frame::CLOSE_NORMAL): Frame
    {
        $frame = new Frame(pack('n', $closeCode), true, Frame::OP_CLOSE);
        if ($this->mask) {
            $frame->maskPayload();
        }
        return $frame;
    }

    public function send($value)
    {
        $this->onNext($value);
    }

    public function sendFrame(Frame $frame)
    {
        if ($this->mask) {
            $this->rawDataOut->onNext($frame->maskPayload()->getContents());
            return;
        }

        $this->rawDataOut->onNext($frame->getContents());
    }

    // The ObserverInterface is commandeered by this class. We will use the parent:: stuff ourselves for notifying
    // subscribers
    public function onNext($value)
    {
        if ($value instanceof Message) {
            $this->sendFrame(new Frame($value, true, $value->isBinary() ? Frame::OP_BINARY : Frame::OP_TEXT));
            return;
        }
        $this->sendFrame(new Frame($value));
    }

    public function onError(\Throwable $exception)
    {
        $this->rawDataDisp->dispose();

        parent::onError($exception);
    }

    public function onCompleted()
    {
        $this->sendFrame($this->createCloseFrame());

        parent::onCompleted();
    }

    public function getSubProtocol(): string
    {
        return $this->subProtocol;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
