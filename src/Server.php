<?php

namespace Rx\Websocket;

use GuzzleHttp\Psr7\Uri;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\Http\Request;
use React\Http\Response;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable;
use Rx\Observable\AnonymousObservable;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;

class Server extends Observable
{
    protected $bindAddress;

    protected $port;

    /** @var bool */
    private $useMessageObject;

    /** @var array */
    private $subProtocols;

    /**
     * Server constructor.
     * @param $bindAddress
     * @param $port
     * @param bool $useMessageObject
     * @param array $subProtocols
     */
    public function __construct($bindAddress, $port, $useMessageObject = false, array $subProtocols = [])
    {
        $this->bindAddress      = $bindAddress;
        $this->port             = $port;
        $this->useMessageObject = $useMessageObject;
        $this->subProtocols     = $subProtocols;
    }

    public function subscribe(ObserverInterface $observer, $scheduler = null)
    {
        $socket = new \React\Socket\Server(\EventLoop\getLoop());

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

//        $http->on('end', function () {});
//        $http->on('data', function () {});
//        $http->on('pause', function () {});
//        $http->on('resume', function () {});

        $this->started = true;

        return new CallbackDisposable(function () use ($socket) {
            $socket->shutdown();
        });
    }
}
