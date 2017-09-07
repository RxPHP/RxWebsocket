<?php

namespace Rx\Websocket;

use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\EventLoop\LoopInterface;
use React\Http\Response;
use React\Stream\CompositeStream;
use React\Stream\ReadableStreamInterface;
use React\Stream\ThroughStream;
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

    public function __construct(string $bindAddressOrPort, bool $useMessageObject = false, array $subProtocols = [], LoopInterface $loop = null)
    {
        $this->bindAddress      = $bindAddressOrPort;
        $this->useMessageObject = $useMessageObject;
        $this->subProtocols     = $subProtocols;
        $this->loop             = $loop ?: \EventLoop\getLoop();
    }

    public function _subscribe(ObserverInterface $observer): DisposableInterface
    {
        $socket = new \React\Socket\Server($this->bindAddress, $this->loop);

        $negotiator = new ServerNegotiator(new RequestVerifier());
        if (!empty($this->subProtocols)) {
            $negotiator->setSupportedSubProtocols($this->subProtocols);
        }

        $http = new \React\Http\Server(function (ServerRequestInterface $request) use ($negotiator, $observer) {
            $uri = $request->getUri();

            $psrRequest = new \GuzzleHttp\Psr7\Request(
                $request->getMethod(),
                $uri,
                $request->getHeaders()
            );

            // cram the remote address into the header in our own X- header so
            // the user will have access to it
            $psrRequest = $psrRequest->withAddedHeader('X-RxWebsocket-Remote-Address', $request->getServerParams()['REMOTE_ADDR'] ?? '');

            $negotiatorResponse = $negotiator->handshake($psrRequest);

            /** @var ReadableStreamInterface $requestStream */
            $requestStream  = new ThroughStream();
            $responseStream = new ThroughStream();

            $response = new Response(
                $negotiatorResponse->getStatusCode(),
                array_merge(
                    $negotiatorResponse->getHeaders()
                ),
                new CompositeStream(
                    $responseStream,
                    $requestStream
                )
            );


            if ($negotiatorResponse->getStatusCode() !== 101) {
                $responseStream->end();
                return;
            }

            $subProtocol = "";
            if (count($negotiatorResponse->getHeader('Sec-WebSocket-Protocol')) > 0) {
                $subProtocol = $negotiatorResponse->getHeader('Sec-WebSocket-Protocol')[0];
            }

            $messageSubject = new MessageSubject(
                new AnonymousObservable(
                    function (ObserverInterface $observer) use ($requestStream) {
                        $requestStream->on('data', function ($data) use ($observer) {
                            $observer->onNext($data);
                        });
                        $requestStream->on('error', function ($error) use ($observer) {
                            $observer->onError($error);
                        });
                        $requestStream->on('close', function () use ($observer) {
                            $observer->onCompleted();
                        });
                        $requestStream->on('end', function () use ($observer) {
                            $observer->onCompleted();
                        });

                        return new CallbackDisposable(
                            function () use ($requestStream) {
                                $requestStream->close();
                            }
                        );
                    }
                ),
                new CallbackObserver(
                    function ($x) use ($responseStream) {
                        $responseStream->write($x);
                    },
                    function ($error) use ($responseStream) {
                        $responseStream->close();
                    },
                    function () use ($responseStream) {
                        $responseStream->end();
                    }
                ),
                false,
                $this->useMessageObject,
                $subProtocol,
                $psrRequest,
                $negotiatorResponse
            );

            $observer->onNext($messageSubject);

            return $response;
        });

        $http->listen($socket);

        return new CallbackDisposable(function () use ($socket) {
            $socket->close();
        });
    }
}
