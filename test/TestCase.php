<?php

namespace Voryx\RxWebsocket\Test;

use EventLoop\EventLoop;
use React\EventLoop\Timer\TimerInterface;

class TestCase extends \PHPUnit_Framework_TestCase
{
    /** @var TimerInterface */
    static private $timeoutTimer;

    public static function getLoop()
    {
        return EventLoop::getLoop();
    }

    public static function stopLoop()
    {
        static::getLoop()->stop();
    }

    public static function cancelCurrentTimeoutTimer()
    {
        if (static::$timeoutTimer !== null) {
            static::$timeoutTimer->cancel();
            static::$timeoutTimer = null;
        }
    }
    public static function runLoopWithTimeout($seconds)
    {
        $loop = static::getLoop();
        static::cancelCurrentTimeoutTimer();
        static::$timeoutTimer = $loop->addTimer($seconds, function ($timer) use ($seconds) {
            static::stopLoop();
            static::$timeoutTimer = null;
            throw new \Exception("Test timed out after " . $seconds . " seconds.");
        });
        $loop->run();
        static::cancelCurrentTimeoutTimer();
    }
}
