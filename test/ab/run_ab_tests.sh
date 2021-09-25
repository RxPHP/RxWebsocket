cd test/ab

docker run --rm \
      -d \
      -v ${PWD}:/config \
      -v ${PWD}/reports:/reports \
      -p 9001:9001 \
      --name rxwsfuzzingserver \
      crossbario/autobahn-testsuite wstest -m fuzzingserver -s /config/fuzzingserver.json
sleep 10
php -d memory_limit=256M clientRunner.php



sleep 10


php -d memory_limit=256M testServer.php &
SERVER_PID=$!
sleep 3

if [ "$RUNNER_OS" = "Linux" ]; then
  IPADDR=`hostname -I | cut -f 1 -d ' '`
else
  IPADDR=`ifconfig | grep "inet " | grep -Fv 127.0.0.1 | awk '{print $2}' | head -1 | tr -d 'adr:'`
fi

docker run --rm \
       \
      -v ${PWD}:/config \
      -v ${PWD}/reports:/reports \
      --name rxwsfuzzingclient \
      crossbario/autobahn-testsuite /bin/sh -c "sh /config/docker_bootstrap.sh $IPADDR; wstest -m fuzzingclient -s /config/fuzzingclient.json"

sleep 12

kill $SERVER_PID
