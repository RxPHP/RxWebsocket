<?php

namespace Rx\Websocket;

use Exception;
use Rx\Websocket\RFC6455\Handshake\ClientNegotiator;
use React\Dns\Resolver\Factory;
use React\HttpClient\Request;
use React\HttpClient\Response;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable\AnonymousObservable;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\Subject\Subject;

class Client extends Subject
{
    /** @var string */
    protected $url;

    /** @var bool */
    private $useMessageObject;

    /** @var array */
    private $subProtocols;

    /**
     * Websocket constructor.
     * @param $url
     * @param bool $useMessageObject
     * @param array $subProtocols
     */
    public function __construct($url, $useMessageObject = false, array $subProtocols = [])
    {
        $this->url = $url;
        $this->useMessageObject = $useMessageObject;
        $this->subProtocols = $subProtocols;
    }

    private function startConnection()
    {
        $loop = \EventLoop\getLoop();

        $dnsResolverFactory = new Factory();
        $dnsResolver = $dnsResolverFactory->createCached('8.8.8.8', $loop);

        $factory = new \React\HttpClient\Factory();
        $client = $factory->create($loop, $dnsResolver);

        $cNegotiator = new ClientNegotiator($this->url, $this->subProtocols);

        $headers = $cNegotiator->getRequest()->getHeaders();

        $flatHeaders = [];
        foreach ($headers as $k => $v) {
            $flatHeaders[$k] = $v[0];
        }

        $request = $client->request("GET", $this->url, $flatHeaders, '1.1');

        $request->on('response', function (Response $response, Request $request) use ($cNegotiator) {
            if ($response->getCode() !== 101) {
                throw new \Exception("Unexpected response code " . $response->getCode());
            }
            // TODO: Should validate response
            //$cNegotiator->validateResponse($response);

            $subprotoHeader = "";

            $psr7Response = new \GuzzleHttp\Psr7\Response(
                $response->getCode(),
                $response->getHeaders(),
                null,
                $response->getVersion()
            );

            if (count($psr7Response->getHeader('Sec-WebSocket-Protocol')) == 1) {
                $subprotoHeader = $psr7Response->getHeader('Sec-WebSocket-Protocol')[0];
            }

            parent::onNext(new MessageSubject(
                new AnonymousObservable(function (ObserverInterface $observer) use ($response) {
                    $response->on('data', function ($data) use ($observer) {
                        $observer->onNext($data);
                    });

                    $response->on('error', function ($e) use ($observer) {
                        $observer->onError($e);
                    });

                    $response->on('close', function () use ($observer) {
                        $observer->onCompleted();
                    });

                    $response->on('end', function () use ($observer) {
                        $observer->onCompleted();

                        // complete the parent observer - we only do 1 connection
                        parent::onCompleted();
                    });


                    return new CallbackDisposable(function () use ($response) {
                        // commented this out because disposal was causing the other
                        // end (the request) to close also - which causes the pending messages
                        // to get tossed
                        //$response->close();
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
                $cNegotiator->getRequest(),
                $psr7Response
            ));
        });

        $request->writeHead();
    }

    public function subscribe(ObserverInterface $observer, $scheduler = null)
    {
        if (!$this->isStopped) {
            $this->startConnection();
        }

        return parent::subscribe($observer, $scheduler);
    }

    public function send($value)
    {
        $this->onNext($value);
    }

    // Not sure we need this object to be a subject - just being an observer should be good enough I think
    public function onNext($value)
    {

    }

    public function onError(Exception $exception)
    {

    }

    public function onCompleted()
    {

    }
}
