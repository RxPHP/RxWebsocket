<?php

namespace Voryx\RxWebsocket;

class Message
{
    private $binary;

    private $content;

    /**
     * Message constructor.
     * @param $content
     * @param bool $binary
     */
    public function __construct($content, $binary = false)
    {
        $this->content = $content;
        $this->binary = $binary;
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return $this->content;
    }

    /**
     * @return boolean
     */
    public function isBinary()
    {
        return $this->binary;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }
}
