[![Build Status](https://travis-ci.org/RxPHP/RxWebsocket.svg?branch=master)](https://travis-ci.org/RxPHP/RxWebsocket)

Rx\Websocket is a PHP Websocket library.

## Usage

#### Client
```php
$client = new \Rx\Websocket\Client('ws://127.0.0.1:9191/');

$client->subscribe(
    function (\Rx\Websocket\MessageSubject $ms) {
        $ms->subscribe(
            function ($message) {
                echo $message . "\n";
            }
        );

        $sayHello = function () use ($ms) {
            $ms->onNext('Hello');
        };

        $sayHello();
        \EventLoop\addPeriodicTimer(5, $sayHello);
    },
    function ($error) {
        // connection errors here
    },
    function () {
        // stopped trying to connect here
    }
);
```

#### An Echo Server
```php
$server = new \Rx\Websocket\Server('127.0.0.1', 9191);

$server->subscribe(function (\Rx\Websocket\MessageSubject $cs) {
    $cs->subscribe($cs);
});
```

#### Server that dumps everything to the console
```php
$server = new \Rx\Websocket\Server('127.0.0.1', 9191);

$server->subscribe(function (\Rx\Websocket\MessageSubject $cs) {
    $cs->subscribe(function ($message) {
        echo $message;
    });
});
```

## Installation

Using [composer](https://getcomposer.org/):

```composer require rx/websocket```
