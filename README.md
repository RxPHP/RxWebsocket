[![Build Status](https://travis-ci.org/RxPHP/Websocket.svg?branch=master)](https://travis-ci.org/RxPHP/Websocket)

Rx\Websocket is a PHP Websocket library.

## Usage

#### Client
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$client = new \Rx\Websocket\Client("ws://127.0.0.1:9191/");

$client->subscribe(new \Rx\Observer\CallbackObserver(
    function (\Rx\Websocket\MessageSubject $ms) {
        $ms->subscribe(new \Rx\Observer\CallbackObserver(
            function ($message) {
                echo $message . "\n";
            }
        ));

        $sayHello = function () use ($ms) { $ms->onNext("Hello"); };

        $sayHello();
        \EventLoop\addPeriodicTimer(5, $sayHello);
    },
    function ($error) {
        // connection errors here
    },
    function () {
        // stopped trying to connect here
    }
));
```

#### An Echo Server
```php
<?php

require_once __DIR__ . "/vendor/autoload.php";

$server = new \Rx\Websocket\Server("127.0.0.1", 9191);

$server
    ->subscribe(new \Rx\Observer\CallbackObserver(
        function (\Rx\Websocket\MessageSubject $cs) {
            $cs->subscribe($cs);
        }
    ));
```

#### Server that dumps everything to the console
```php
<?php

require_once __DIR__ . "/vendor/autoload.php";

$server = new \Rx\Websocket\Server("127.0.0.1", 9191);

$server
    ->subscribe(new \Rx\Observer\CallbackObserver(
        function (\Rx\Websocket\MessageSubject $cs) {
            $ms->subscribe(new CallbackObserver(
                function ($message) {
                    echo $message;
                }
            ));
        }
    ));
```

## Installation

Using [composer](https://getcomposer.org/):

```composer require rx/websocket```