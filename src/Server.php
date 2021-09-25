<?php

namespace Rx\Websocket;

use Psr\Http\Message\ServerRequestInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use React\EventLoop\LoopInterface;
use React\Http\Message\Response;
use React\Http\Middleware\StreamingRequestMiddleware as StreamingRequestMiddlewareAlias;
use React\Http\HttpServer;
use React\Socket\SocketServer;
use React\Stream\CompositeStream;
use React\Stream\ThroughStream;
use Rx\Disposable\CallbackDisposable;
use Rx\Disposable\EmptyDisposable;
use Rx\DisposableInterface;
use Rx\Observable;
use Rx\Observable\AnonymousObservable;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use function RingCentral\Psr7\str;

class Server extends Observable
{
    protected $bindAddress;
    protected $port;
    private $useMessageObject;
    private $subProtocols;
    private $loop;
    private $keepAlive;

    public function __construct(string $bindAddressOrPort, bool $useMessageObject = false, array $subProtocols = [], LoopInterface $loop = null, int $keepAlive = 60000)
    {
        $this->bindAddress      = $bindAddressOrPort;
        $this->useMessageObject = $useMessageObject;
        $this->subProtocols     = $subProtocols;
        $this->loop             = $loop ?: \EventLoop\getLoop();
        $this->keepAlive        = $keepAlive;
    }

    public function _subscribe(ObserverInterface $observer): DisposableInterface
    {
        $socket = new SocketServer($this->bindAddress, [], $this->loop);

        $negotiator = new ServerNegotiator(new RequestVerifier());
        if (!empty($this->subProtocols)) {
            $negotiator->setSupportedSubProtocols($this->subProtocols);
        }

        $http = new HttpServer(
            $this->loop,
            new StreamingRequestMiddlewareAlias(),
            function (ServerRequestInterface $request) use ($negotiator, $observer) {
                // cram the remote address into the header in our own X- header so
                // the user will have access to it
                $request = $request->withAddedHeader('X-RxWebsocket-Remote-Address', $request->getServerParams()['REMOTE_ADDR'] ?? '');

                $negotiatorResponse = $negotiator->handshake($request);

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
                    $responseStream->end(str($negotiatorResponse));
                    return new EmptyDisposable();
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
                    $request,
                    $negotiatorResponse,
                    $this->keepAlive
                );

                $observer->onNext($messageSubject);

                return $response;
            }
        );

        $http->listen($socket);

        return new CallbackDisposable(function () use ($socket) {
            $socket->close();
        });
    }
}
