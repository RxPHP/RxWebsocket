RxWebsocket is a PHP Websocket library.

## Usage

#### Client
```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$client = new \RxWebsocket\Client("ws://127.0.0.1:9191/");

$client->subscribe(new \Rx\Observer\CallbackObserver(
    function (\RxWebsocket\MessageSubject $ms) {
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

$server = new \RxWebsocket\Server("127.0.0.1", 9191);

$server
    ->subscribe(new \Rx\Observer\CallbackObserver(
        function (\RxWebsocket\MessageSubject $cs) {
            $cs->subscribe($cs);
        }
    ));
```

#### Server that dumps everything to the console
```php
<?php

require_once __DIR__ . "/vendor/autoload.php";

$server = new \RxWebsocket\Server("127.0.0.1", 9191);

$server
    ->subscribe(new \Rx\Observer\CallbackObserver(
        function (\RxWebsocket\MessageSubject $cs) {
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

Right now, this project uses components of the [Ratchet RFC6455 project](https://github.com/ratchetphp/RFC6455) that
are not tagged with a release, so they must be installed manually before this project:
```composer require ratchet/rfc6455:dev-psr```

Then install this project:
```composer require voryx/rxwebsocket```