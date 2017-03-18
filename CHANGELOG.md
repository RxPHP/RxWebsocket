# 0.10.0

## Changes/Additions

- Project now uses [RFC6455](https://github.com/ratchetphp/RFC6455) library for underlying protocol support
- Message subject now emits `Ratchet\RFC6455\Messaging\Message` instead of `Rx\Websocket\Message`
- `Client` is no longer a `Subject`
