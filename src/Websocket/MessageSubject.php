<?php

namespace Rx\Websocket;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Rx\Websocket\RFC6455\Messaging\Protocol\Frame;
use Rx\Observable;
use Rx\Observable\AnonymousObservable;
use Rx\ObservableInterface;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\Subject\Subject;

class MessageSubject extends Subject
{
    /** @var Observable */
    protected $rawDataIn;

    /** @var ObserverInterface */
    protected $rawDataOut;

    /** @var bool */
    protected $mask;

    /** @var Observable */
    protected $controlFrames;

    /** @var string */
    private $subProtocol;

    /** @var RequestInterface */
    private $request;

    /** @var ResponseInterface */
    private $response;

    /**
     * ConnectionSubject constructor.
     * @param ObservableInterface $rawDataIn
     * @param ObserverInterface $rawDataOut
     * @param bool $mask
     * @param bool $useMessageObject
     * @param string $subProtocol
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    public function __construct(
        ObservableInterface $rawDataIn,
        ObserverInterface $rawDataOut,
        $mask = false,
        $useMessageObject = false,
        $subProtocol = "",
        RequestInterface $request,
        ResponseInterface $response
    ) {
        $this->request = $request;
        $this->response = $response;

        $this->rawDataIn = new AnonymousObservable(function ($observer) use ($rawDataIn) {
            return $rawDataIn->subscribe($observer);
        });
        $this->rawDataOut = $rawDataOut;
        $this->mask = $mask;
        $this->subProtocol = $subProtocol;

        // This can be used instead of the subjecg when this issue is addressed:
        // https://github.com/asm89/Rx.PHP/issues/20
        // Actually - using the subject is better so that the framing doesn't get done for every
        // subscriber.
        //$frames = $this->rawDataIn
        //    ->lift(new WebsocketFrameOperator());
        $frames = new Subject();

        $this->rawDataIn
            ->lift(function () {
                return new WebsocketFrameOperator();
            })
            ->subscribe(new CallbackObserver(
                [$frames, "onNext"],
                function ($error) use ($frames) {
                    $close = $this->createCloseFrame();
                    if ($error instanceof WebsocketErrorException) {
                        $close = $this->createCloseFrame($error->getCloseCode());
                    }
                    $this->sendFrame($close);
                    $this->rawDataOut->onCompleted();

                    // TODO: Should this error through to frame observers?
                    $frames->onCompleted();
                },
                function () use ($frames) {
                    $this->rawDataOut->onCompleted();

                    $frames->onCompleted();
                }
            ));

        $this->controlFrames = $frames
            ->filter(function (Frame $frame) {
                return $frame->getOpcode() > 2;
            });

        // default ping handler (ping received from far end
        $this
            ->controlFrames
            ->filter(function (Frame $frame) {
                return $frame->getOpcode() === $frame::OP_PING;
            })
            ->subscribe(new CallbackObserver(
                function (Frame $frame) {
                    $pong = new Frame($frame->getPayload(), true, Frame::OP_PONG);
                    $this->sendFrame($pong);
                }
            ));

        $frames
            ->filter(function (Frame $frame) {
                return $frame->getOpcode() < 3;
            })
            ->lift(function () use ($mask, $useMessageObject) {
                return new WebsocketMessageOperator($mask, $useMessageObject);
            })
            ->subscribe(new CallbackObserver(
                function ($x) {
                    parent::onNext($x);
                },
                function ($x) {
                    parent::onError($x);
                },
                function () {
                    parent::onCompleted();
                }
            ));
        $this->subProtocol = $subProtocol;
    }

    private function createCloseFrame($closeCode = Frame::CLOSE_NORMAL)
    {
        return new Frame(pack('n', $closeCode), true, Frame::OP_CLOSE);
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

    /**
     * @return Observable
     */
    public function getControlFrames()
    {
        return $this->controlFrames;
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

    public function onError(\Exception $exception)
    {

    }

    public function onCompleted()
    {
        $this->sendFrame($this->createCloseFrame());

        // notify subscribers
        parent::onCompleted();
    }

    /**
     * @return string
     */
    public function getSubProtocol()
    {
        return $this->subProtocol;
    }

    /**
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }
}
