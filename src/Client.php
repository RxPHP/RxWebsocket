<?php

namespace Rx\Websocket;

use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Ratchet\RFC6455\Handshake\ClientNegotiator;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Http\Client\Client as HttpClient;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use React\Stream\ThroughStream;
use Rx\Disposable\CallbackDisposable;
use Rx\DisposableInterface;
use Rx\Observable;
use Rx\Observable\AnonymousObservable;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;

class Client extends Observable
{
    protected $url;
    private $useMessageObject;
    private $subProtocols;
    private $browser;
    private $keepAlive;
    private $headers;

    public function __construct(string $url, bool $useMessageObject = false, array $subProtocols = [], LoopInterface $loop = null, ConnectorInterface $connector = null, int $keepAlive = 60000, array $headers = [])
    {
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['wss', 'ws'])) {
            throw new \InvalidArgumentException('url must use either ws or wss scheme');
        }

        if ($parsedUrl['scheme'] === 'wss') {
            $url = 'https://' . substr($url, 6);
        }

        if ($parsedUrl['scheme'] === 'ws') {
            $url = 'http://' . substr($url, 5);
        }

        $this->url              = $url;
        $this->useMessageObject = $useMessageObject;
        $this->subProtocols     = $subProtocols;
        $this->browser          = new Browser($connector, $loop);
        $this->keepAlive        = $keepAlive;
        $this->headers          = $headers;
    }

    public function _subscribe(ObserverInterface $clientObserver): DisposableInterface
    {
        $cNegotiator = new ClientNegotiator();

        /** @var Psr7Request $nRequest */
        $nRequest = $cNegotiator->generateRequest(new Uri($this->url));

        if (!empty($this->subProtocols)) {
            $nRequest = $nRequest
                ->withoutHeader('Sec-WebSocket-Protocol')
                ->withHeader('Sec-WebSocket-Protocol', $this->subProtocols);
        }

        $headers = $nRequest->getHeaders();

        $flatHeaders = [];
        foreach ($headers as $k => $v) {
            $flatHeaders[$k] = $v[0];
        }

        foreach ($this->headers as $k => $v) {
            $flatHeaders[$k] = $v;
        }

        $writeStream = new ThroughStream();
        $this->browser->requestStreaming('GET', $this->url, $flatHeaders, $writeStream)->then(
            function (ResponseInterface $response) use ($flatHeaders, $cNegotiator, $nRequest, $clientObserver) {
                if ($response->getStatusCode() !== 101) {
                    $clientObserver->onError(new \Exception('Unexpected response code ' . $response->getStatusCode()));
                    return;
                }

                $psr7Response = new Psr7Response(
                    $response->getStatusCode(),
                    $response->getHeaders(),
                    null,
                    $response->getProtocolVersion()
                );

                $psr7Request = new Psr7Request('GET', $this->url, $flatHeaders);

                if (!$cNegotiator->validateResponse($psr7Request, $psr7Response)) {
                    $clientObserver->onError(new \Exception('Invalid response'));
                    return;
                }

                $subprotoHeader = $psr7Response->getHeader('Sec-WebSocket-Protocol');

                $clientObserver->onNext(new MessageSubject(
                    new AnonymousObservable(function (ObserverInterface $observer) use ($response, $writeStream, $clientObserver) {

                        $writeStream->on('data', function ($data) use ($observer) {
                            $observer->onNext($data);
                        });

                        $writeStream->on('error', function ($e) use ($observer) {
                            $observer->onError($e);
                        });

                        $writeStream->on('close', function () use ($observer, $clientObserver) {
                            $observer->onCompleted();

                            // complete the parent observer - we only do 1 connection
                            $clientObserver->onCompleted();
                        });

                        $writeStream->on('end', function () use ($observer, $clientObserver) {
                            $observer->onCompleted();

                            // complete the parent observer - we only do 1 connection
                            $clientObserver->onCompleted();
                        });

                        return new CallbackDisposable(function () use ($writeStream) {
                            $writeStream->end();
                        });
                    }),
                    new CallbackObserver(
                        function ($x) use ($writeStream) {
                            $writeStream->write($x);
                        },
                        function ($e) use ($writeStream) {
                            $writeStream->close();
                        },
                        function () use ($writeStream) {
                            $writeStream->end();
                        }
                    ),
                    true,
                    $this->useMessageObject,
                    $subprotoHeader,
                    $nRequest,
                    $psr7Response,
                    $this->keepAlive
                ));
            },
            static function ($error) use ($clientObserver) {
                $clientObserver->onError($error);
            }
        );

        // empty write to force connection and header send
        $writeStream->write('');

        return new CallbackDisposable(function () use ($writeStream) {
            $writeStream->close();
        });
    }
}
