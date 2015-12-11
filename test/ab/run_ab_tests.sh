cd test/ab

wstest -m fuzzingserver -s fuzzingserver.json &
sleep 5
php clientRunner.php

sleep 2

php testServer.php 15 &
sleep 3
wstest -m fuzzingclient -s fuzzingclient.json
sleep 12