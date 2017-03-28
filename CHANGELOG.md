# 1.0.1

- Connection errors are now sent to `onError` #6 ([a880353](https://github.com/RxPHP/RxWebsocket/commit/a88035322fea54638d67d67985e8f938200155cd))

# 1.0.0

- Upgrade to RxPHP v2

# 0.10.0

## Changes/Additions

- Project now uses [RFC6455](https://github.com/ratchetphp/RFC6455) library for underlying protocol support
- Message subject now emits `Ratchet\RFC6455\Messaging\Message` instead of `Rx\Websocket\Message`
- `Client` is no longer a `Subject`
