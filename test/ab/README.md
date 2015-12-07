This directory is used to run the [Autobahn Testsuite](http://autobahn.ws/testsuite/) against the RxWebsocket library.

### Client tests (RxWebsocket is the client being tested)

Start the fuzzing server:
```
wstest -m fuzzingserver -s fuzzingserver.json
```

In another shell, run the test client:
```
php clientRunner.php
```

### Server tests (RxWebsocket is the server being tested)

Start the server:
```
php testServer.php
```

In another shell, run the fuzzing client:
```
wstest -m fuzzingclient -s fuzzingclient.json
```