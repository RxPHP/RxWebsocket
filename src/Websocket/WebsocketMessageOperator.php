<?php

namespace Rx\Websocket;

use Rx\Websocket\RFC6455\Encoding\Validator;
use Rx\Websocket\RFC6455\Messaging\Protocol\Message;
use Rx\Websocket\RFC6455\Messaging\Validation\MessageValidator;
use Rx\ObservableInterface;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\Operator\OperatorInterface;
use Rx\SchedulerInterface;

/**
 * Class WebsocketMessageOperator
 *
 * This operator takes a source observable sequence of rfc 6455 frames and outputs messages
 *
 * @package RxWebsocket
 */
class WebsocketMessageOperator implements OperatorInterface
{
    /** @var Message */
    private $message;

    /** @var bool */
    private $useMessageObject;

    /**
     * WebsocketMessageOperator constructor.
     * @param bool $masked
     * @param bool $useMessageObject
     */
    public function __construct($masked = true, $useMessageObject = false)
    {
        $this->message = new Message();
        $this->messageValidator = new MessageValidator(new Validator(), $masked);
        $this->useMessageObject = $useMessageObject;
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
            function ($frame) use ($observer) {
                $this->message->addFrame($frame);
                if ($this->message->isCoalesced()) {
                    if ($this->useMessageObject) {
                        $observer->onNext(
                            new \Rx\Websocket\Message($this->message->getPayload(), $this->message->isBinary())
                        );
                    } else {
                        $observer->onNext($this->message->getPayload());
                    }
                    $this->message = new Message();
                }
            }
        ));
    }
}
