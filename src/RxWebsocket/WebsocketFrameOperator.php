<?php

namespace Voryx\RxWebsocket;

use Ratchet\RFC6455\Encoding\Validator;
use Ratchet\RFC6455\Messaging\Protocol\Frame;
use Ratchet\RFC6455\Messaging\Validation\MessageValidator;
use Rx\ObservableInterface;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\Operator\OperatorInterface;
use Rx\SchedulerInterface;

/**
 * Class WebsocketFrameOperator
 *
 * The operator takes a source observable sequence of data chunks (strings) and emits rfc 6455 websocket frames
 *
 * @package RxWebsocket
 */
class WebsocketFrameOperator implements OperatorInterface
{
    /** @var Frame */
    private $frame;

    /** @var MessageValidator */
    private $validator;

    private $previousFrame;

    /**
     * WebsocketFrameOperator constructor.
     */
    public function __construct()
    {
        $this->frame = new Frame();

        $this->validator = new MessageValidator(new Validator(), false);
    }

    /**
     * @inheritDoc
     */
    public function __invoke(
        ObservableInterface $observable,
        ObserverInterface $observer,
        SchedulerInterface $scheduler = null
    ) {
        return $observable->subscribe(new CallbackObserver(
            function ($data) use ($observer) {
                while (strlen($data) > 0) {
                    $frame = $this->frame;

                    $frame->addBuffer($data);
                    $data = "";

                    if ($frame->isCoalesced()) {
                        $result = $this->validator->validateFrame($frame, $this->previousFrame);
                        if (0 !== $result) {
                            $observer->onError(new WebsocketErrorException($result));
                        }

                        $data = $frame->extractOverflow();
                        $frame->unMaskPayload();

                        $observer->onNext($frame);


                        if ($frame->getOpcode() < 3) {
                            $this->previousFrame = $this->frame;
                            if ($frame->isFinal()) {
                                $this->previousFrame = null;
                            }
                        }

                        $this->frame = new Frame();
                    }
                }
            }
        ));
    }
}
