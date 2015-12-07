<?php

namespace Voryx\RxWebsocket;

use Ratchet\RFC6455\Encoding\Validator;
use Ratchet\RFC6455\Messaging\Protocol\Message;
use Ratchet\RFC6455\Messaging\Validation\MessageValidator;
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

    /**
     * WebsocketMessageOperator constructor.
     * @param bool $masked
     */
    public function __construct($masked = true)
    {
        $this->message = new Message();
        $this->messageValidator = new MessageValidator(new Validator(), $masked);
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
                    $observer->onNext($this->message->getPayload());
                    $this->message = new Message();
                }
            }
        ));
    }
}
