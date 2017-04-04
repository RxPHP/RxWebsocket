<?php

namespace Rx\Websocket;

use GuzzleHttp\Psr7\Uri;
use Ratchet\RFC6455\Handshake\ClientNegotiator;
use React\Dns\Resolver\Factory;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\HttpClient\Request;
use React\HttpClient\Response;
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
    private $loop;
    private $dnsResolver;

    public function __construct(string $url, bool $useMessageObject = false, array $subProtocols = [], LoopInterface $loop = null, Resolver $dnsResolver = null)
    {
        $this->url              = $url;
        $this->useMessageObject = $useMessageObject;
        $this->subProtocols     = $subProtocols;
        $this->loop             = $loop ?: \EventLoop\getLoop();
        $this->dnsResolver      = $dnsResolver ?: (new Factory())->createCached('8.8.8.8', $this->loop);
    }

    public function _subscribe(ObserverInterface $clientObserver): DisposableInterface
    {
        $factory = new \React\HttpClient\Factory();
        $client  = $factory->create($this->loop, $this->dnsResolver);

        $cNegotiator = new ClientNegotiator();

        /** @var \GuzzleHttp\Psr7\Request $nRequest */
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

        $request = $client->request('GET', $this->url, $flatHeaders, '1.1');

        $request->on('error', function ($error) use ($clientObserver) {
            $clientObserver->onError($error);
        });

        $request->on('response', function (Response $response, Request $request) use ($flatHeaders, $cNegotiator, $nRequest, $clientObserver) {
            if ($response->getCode() !== 101) {
                throw new \Exception('Unexpected response code ' . $response->getCode());
            }

            $psr7Response = new \GuzzleHttp\Psr7\Response(
                $response->getCode(),
                $response->getHeaders(),
                null,
                $response->getVersion()
            );

            $psr7Request = new \GuzzleHttp\Psr7\Request('GET', $this->url, $flatHeaders);

            if (!$cNegotiator->validateResponse($psr7Request, $psr7Response)) {
                throw new \Exception('Invalid response');
            }

            $subprotoHeader = $psr7Response->getHeader('Sec-WebSocket-Protocol');

            $clientObserver->onNext(new MessageSubject(
                new AnonymousObservable(function (ObserverInterface $observer) use ($response, $request, $clientObserver) {

                    $response->on('data', function ($data) use ($observer) {
                        $observer->onNext($data);
                    });

                    $response->on('error', function ($e) use ($observer) {
                        $observer->onError($e);
                    });

                    $response->on('close', function () use ($observer) {
                        $observer->onCompleted();
                    });

                    $response->on('end', function () use ($observer, $clientObserver) {
                        $observer->onCompleted();

                        // complete the parent observer - we only do 1 connection
                        $clientObserver->onCompleted();
                    });

                    return new CallbackDisposable(function () use ($request) {
                        $request->end();
                    });
                }),
                new CallbackObserver(
                    function ($x) use ($request) {
                        $request->write($x);
                    },
                    function ($e) use ($request) {
                        $request->close();
                    },
                    function () use ($request) {
                        $request->end();
                    }
                ),
                true,
                $this->useMessageObject,
                $subprotoHeader,
                $nRequest,
                $psr7Response
            ));
        });

        $request->writeHead();

        return new CallbackDisposable(function () use ($request) {
            $request->close();
        });
    }
}
