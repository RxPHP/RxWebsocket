<?php

require_once __DIR__ . '/../bootstrap.php';

const AGENT = "RxWebsocket/0.0.0";

echo "Using " . get_class(\EventLoop\getLoop()) . "\n";

$runReports = function () {
    echo "Generating report.\n";

    $client = new \Voryx\RxWebsocket\Client("ws://127.0.0.1:9001/updateReports?agent=" . AGENT);

    $client->subscribe(new \Rx\Observer\CallbackObserver());
};

$runIndividualTest = function ($case) {
    echo "Running " . $case . "\n";

    $casePath = "/runCase?case={$case}&agent=" . AGENT;

    $client = new \Voryx\RxWebsocket\Client("ws://127.0.0.1:9001" . $casePath);

    $deferred = new \React\Promise\Deferred();

    $client->subscribe(new \Rx\Observer\CallbackObserver(
        function (\Voryx\RxWebsocket\MessageSubject $messages) {
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
    ));

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
        $runIndividualTest($i)->then($runNextCase);
    };

    $runNextCase();

    $deferred->promise()->then($runReports);
};

// get the tests that need to run
$client = new \Voryx\RxWebsocket\Client("ws://127.0.0.1:9001/getCaseCount");

$client
    ->flatMap(function ($x) {
        return $x;
    })
    ->subscribe(new \Rx\Observer\CallbackObserver(
        $runTests,
        function ($error) {
            echo $error . "\n";
        }
    ));
