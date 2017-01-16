<?php

namespace Rx\Websocket;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\Message;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Rx\DisposableInterface;
use Rx\Observable;
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

    /** @var DisposableInterface */
    private $rawDataDisp;

    /**
     * ConnectionSubject constructor.
     * @param Observable $rawDataIn
     * @param ObserverInterface $rawDataOut
     * @param bool $mask
     * @param bool $useMessageObject
     * @param string $subProtocol
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    public function __construct(
        Observable $rawDataIn,
        ObserverInterface $rawDataOut,
        $mask = false,
        $useMessageObject = false,
        $subProtocol = "",
        RequestInterface $request,
        ResponseInterface $response
    ) {
        $this->request  = $request;
        $this->response = $response;

        $this->rawDataIn   = $rawDataIn;
        $this->rawDataOut  = $rawDataOut;
        $this->mask        = $mask;
        $this->subProtocol = $subProtocol;

        $messageBuffer = new MessageBuffer(
            new CloseFrameChecker(),
            function (\Ratchet\RFC6455\Messaging\MessageInterface $msg) use ($useMessageObject) {
                parent::onNext($useMessageObject ? $msg : $msg->getPayload());
            },
            function (FrameInterface $frame) use ($rawDataOut) {
                switch ($frame->getOpcode()) {
                    case Frame::OP_PING:
                        $this->sendFrame(new Frame($frame->getPayload(), true, Frame::OP_PONG));
                        return;
                    case Frame::OP_CLOSE:
                        // send close frame to remote
                        $this->sendFrame($frame);
                        // complete output stream
                        $rawDataOut->onCompleted();

                        // signal subscribers that we are done here
                        //parent::onCompleted();
                        return;
                }
            },
            !$this->mask
        );

        $this->rawDataDisp = $this->rawDataIn
            ->subscribe(new CallbackObserver(
                function ($data) use ($messageBuffer) {
                    $messageBuffer->onData($data);
                },
                function (\Exception $exception) {
                    parent::onError($exception);
                },
                function () {
                    parent::onCompleted();
                }
            ));

        $this->subProtocol = $subProtocol;
    }

    private function createCloseFrame($closeCode = Frame::CLOSE_NORMAL)
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
        $this->rawDataDisp->dispose();

        parent::onError($exception);
    }

    public function onCompleted()
    {
        $this->sendFrame($this->createCloseFrame());

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
