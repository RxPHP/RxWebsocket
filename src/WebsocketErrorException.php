<?php

namespace Rx\Websocket;

use Ratchet\RFC6455\Messaging\Frame;

class WebsocketErrorException extends \Exception
{
    /** @var int */
    private $closeCode;

    /**
     * WebsocketErrorException constructor.
     * @param mixed $closeCode
     */
    public function __construct($closeCode = Frame::CLOSE_ABNORMAL)
    {
        $this->closeCode = $closeCode;
    }

    /**
     * @return int
     */
    public function getCloseCode()
    {
        return $this->closeCode;
    }
}
