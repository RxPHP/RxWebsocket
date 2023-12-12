<?php

namespace Rx\Websocket\Test;

use Rx\Functional\FunctionalTestCase;
use Rx\Scheduler;

class TestCase extends FunctionalTestCase
{
    public function setup(): void
    {
        parent::setup();

        self::resetScheduler();

        Scheduler::setDefaultFactory(function () {
            return $this->scheduler;
        });
    }

    public static function resetScheduler()
    {
        $ref = new \ReflectionClass(Scheduler::class);
        $props = $ref->getProperties();

        foreach ($props as $prop) {
            $prop->setAccessible(true);
            $prop->setValue($prop, null);
            $prop->setAccessible(false);
        }
    }
}
