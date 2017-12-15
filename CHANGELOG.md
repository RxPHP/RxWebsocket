# 2.1.2

- Update deps

# 2.1.1

- Emit socket errors instead of throwing

# 2.1.0

- Added websocket ping keepalive

# 2.0.0

- Updated react libraries (http/http-client)
- Changed API to allow passing of `Connector`

before
```PHP
$server = new \Rx\Websocket\Server('127.0.0.1', 9191);
```
after
```PHP
$server = new \Rx\Websocket\Server('127.0.0.1:9191');
```

# 1.0.2

- End the request not the response when dispose is called ([b77c5118](https://github.com/RxPHP/RxWebsocket/commit/b77c5118c14d34e034b19383974337aec05d787a))

# 1.0.1

- Connection errors are now sent to `onError` #6 ([a880353](https://github.com/RxPHP/RxWebsocket/commit/a88035322fea54638d67d67985e8f938200155cd))

# 1.0.0

- Upgrade to RxPHP v2

# 0.10.0

## Changes/Additions

- Project now uses [RFC6455](https://github.com/ratchetphp/RFC6455) library for underlying protocol support
- Message subject now emits `Ratchet\RFC6455\Messaging\Message` instead of `Rx\Websocket\Message`
- `Client` is no longer a `Subject`
