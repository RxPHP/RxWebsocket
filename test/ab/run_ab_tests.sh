cd test/ab || exit

docker run --rm \
    -d \
    -v ${PWD}:/config \
    -v ${PWD}/reports:/reports \
    -p 9001:9001 \
    --name fuzzingserver \
    crossbario/autobahn-testsuite wstest -m fuzzingserver -s /config/fuzzingserver.json
sleep 5

php -d memory_limit=256M clientRunner.php

docker ps -a

docker logs fuzzingserver

docker stop fuzzingserver

sleep 2


php -d memory_limit=256M testServer.php &
SERVER_PID=$!
sleep 3

if [ "$OSTYPE" = "linux-gnu" ]; then
  IPADDR=`hostname -I | cut -f 1 -d ' '`
else
  IPADDR=`ifconfig | grep "inet " | grep -Fv 127.0.0.1 | awk '{print $2}' | head -1 | tr -d 'adr:'`
fi

docker run --rm \
    -it \
    -v ${PWD}:/config \
    -v ${PWD}/reports:/reports \
    --name fuzzingclient \
    crossbario/autobahn-testsuite /bin/sh -c "sh /config/docker_bootstrap.sh $IPADDR; wstest -m fuzzingclient -s /config/fuzzingclient.json"
sleep 1

kill $SERVER_PID

#wstest -m fuzzingserver -s fuzzingserver.json &
#sleep 5
#php clientRunner.php
#
#sleep 2

#php testServer.php 600 &
#sleep 3
#wstest -m fuzzingclient -s fuzzingclient.json
#sleep 12
