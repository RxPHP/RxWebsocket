<?php

require_once __DIR__ . '/../bootstrap.php';

const AGENT = "Websocket/0.0.0";

echo "Using " . get_class(\EventLoop\getLoop()) . "\n";

$runReports = function () {
    echo "Generating report.\n";

    $reportUrl = "ws://127.0.0.1:9001/updateReports?agent=" . AGENT . "&shutdownOnComplete=true";
    $client    = new \Rx\Websocket\Client($reportUrl);

    $client->subscribe(
        function (\Rx\Websocket\MessageSubject $messages) {
            echo "Report runner connected.\n";
            $messages->subscribe(new \Rx\Observer\CallbackObserver(
                                     function ($x) use ($messages) {
                                         echo "Message received by report runner connection: " . $x . "\n";;
                                     },
                                     [$messages, "onError"],
                                     [$messages, "onCompleted"]
                                 ));
        },
        function (Throwable $error) {
            echo "Error on report runner connection:" . $error->getMessage() . "\n";
            echo "Seeing an error here might be normal. Network trace shows that AB fuzzingserver\n";
            echo "disconnects without sending an HTTP response.\n";
        },
        function () {
            echo "Report runner connection completed.\n";
        }
    );
};

$runIndividualTest = function ($case, $timeout = 60000) {
    echo "Running " . $case . "\n";

    $casePath = "/runCase?case={$case}&agent=" . AGENT . "-" . $timeout;

    $client = new \Rx\Websocket\Client("ws://127.0.0.1:9001" . $casePath, true, [], null, null, $timeout);

    $deferred = new \React\Promise\Deferred();

    $client->subscribe(
        function (\Rx\Websocket\MessageSubject $messages) {
            $messages->subscribe(new \Rx\Observer\CallbackObserver(
                function ($x) use ($messages) {
                    //echo $x . "\n";
                    $messages->onNext($x);
                },
                [$messages, "onError"],
                [$messages, "onCompleted"]
            ));
        },
        function ($error) use ($case, $deferred) {
            echo "Error on " . $case . "\n";
            $deferred->reject($error);
        },
        function () use ($case, $deferred) {
            echo "Finished " . $case . "\n";
            $deferred->resolve();
        }
    );

    return $deferred->promise();
};

$runTests = function ($testCount) use ($runIndividualTest, $runReports) {
    echo "Server would like us to run " . $testCount . " tests.\n";

    $i = 0;

    $deferred = new \React\Promise\Deferred();

    $runNextCase = function () use (&$i, &$runNextCase, $testCount, $deferred, $runIndividualTest) {
        $i++;
        if ($i > $testCount) {
            $deferred->resolve();
            return;
        }
        $runIndividualTest($i, 60000)->then(function ($result) use ($runIndividualTest, &$i) {
            // Use this if you want to run with no keepalive
            //return $runIndividualTest($i, 0);
            return $result;
        })->then($runNextCase);
    };

    $runNextCase();

    $deferred->promise()->then($runReports);
};

// get the tests that need to run
$client = new \Rx\Websocket\Client("ws://127.0.0.1:9001/getCaseCount");

$client
    ->flatMap(function ($x) {
        return $x;
    })
    ->subscribe(
        $runTests,
        function ($error) {
            echo $error . "\n";
        }
    );
