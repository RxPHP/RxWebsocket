<?php

namespace Rx\Websocket;

use GuzzleHttp\Psr7\Uri;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\EventLoop\LoopInterface;
use React\Http\Request;
use React\Http\Response;
use Rx\Disposable\CallbackDisposable;
use Rx\DisposableInterface;
use Rx\Observable;
use Rx\Observable\AnonymousObservable;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;

class Server extends Observable
{
    protected $bindAddress;
    protected $port;
    private $useMessageObject;
    private $subProtocols;
    private $loop;

    public function __construct(string $bindAddress, int $port, bool $useMessageObject = false, array $subProtocols = [], LoopInterface $loop = null)
    {
        $this->bindAddress      = $bindAddress;
        $this->port             = $port;
        $this->useMessageObject = $useMessageObject;
        $this->subProtocols     = $subProtocols;
        $this->loop             = $loop ?: \EventLoop\getLoop();
    }

    public function _subscribe(ObserverInterface $observer): DisposableInterface
    {
        $socket = new \React\Socket\Server($this->loop);

        $negotiator = new ServerNegotiator(new RequestVerifier());
        if (!empty($this->subProtocols)) {
            $negotiator->setSupportedSubProtocols($this->subProtocols);
        }

        $http = new \React\Http\Server($socket);
        $http->on('request', function (Request $request, Response $response) use ($negotiator, $observer, &$outStream) {
            $uri = new Uri($request->getPath());
            if (count($request->getQuery()) > 0) {
                $uri = $uri->withQuery(\GuzzleHttp\Psr7\build_query($request->getQuery()));
            }

            $psrRequest = new \GuzzleHttp\Psr7\Request(
                $request->getMethod(),
                $uri,
                $request->getHeaders()
            );

            // cram the remote address into the header in our own X- header so
            // the user will have access to it
            $psrRequest = $psrRequest->withAddedHeader('X-RxWebsocket-Remote-Address', $request->remoteAddress);

            $negotiatorResponse = $negotiator->handshake($psrRequest);

            $response->writeHead(
                $negotiatorResponse->getStatusCode(),
                array_merge(
                    $negotiatorResponse->getHeaders(),
                    ['Content-Length' => '0']
                )
            );

            if ($negotiatorResponse->getStatusCode() !== 101) {
                $response->end();
                return;
            }

            $subProtocol = "";
            if (count($negotiatorResponse->getHeader('Sec-WebSocket-Protocol')) > 0) {
                $subProtocol = $negotiatorResponse->getHeader('Sec-WebSocket-Protocol')[0];
            }

            $connection = new MessageSubject(
                new AnonymousObservable(
                    function (ObserverInterface $observer) use ($request) {
                        $request->on('data', function ($data) use ($observer) {
                            $observer->onNext($data);
                        });
                        $request->on('error', function ($error) use ($observer) {
                            $observer->onError($error);
                        });
                        $request->on('close', function () use ($observer) {
                            $observer->onCompleted();
                        });
                        $request->on('end', function () use ($observer) {
                            $observer->onCompleted();
                        });

                        return new CallbackDisposable(
                            function () use ($request) {
                                $request->close();
                            }
                        );
                    }
                ),
                new CallbackObserver(
                    function ($x) use ($response) {
                        $response->write($x);
                    },
                    function ($error) use ($response) {
                        $response->close();
                    },
                    function () use ($response) {
                        $response->end();
                    }
                ),
                false,
                $this->useMessageObject,
                $subProtocol,
                $psrRequest,
                $negotiatorResponse
            );

            $observer->onNext($connection);
        });

        $socket->listen($this->port, $this->bindAddress);

        return new CallbackDisposable(function () use ($socket) {
            $socket->shutdown();
        });
    }
}
