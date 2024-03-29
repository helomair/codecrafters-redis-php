<?php

namespace app;

use app\Redis\Redis;
use app\Redis\Config;
use app\Redis\libs\SlaveHandshake;

require_once 'autoload.php';

echo "Logs from your program will appear here";

const MASTER_ROLE = 'master';
const SLAVE_ROLE = 'slave';

function makeOriginSocket() {
    $port = Config::get('port');
    if (($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
        echo "socket_create() failed : " . socket_strerror(socket_last_error()) . PHP_EOL;
        exit(1);
    }

    if (socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) === false) {
        echo "socket_set_option() failed : " . socket_strerror(socket_last_error()) . PHP_EOL;
        exit(1);
    }

    if (socket_bind($socket, "localhost", $port) === false) {
        echo "socket_bind() failed : " . socket_strerror(socket_last_error()) . PHP_EOL;
        exit(1);
    }

    if (socket_listen($socket, 5) === false) {
        echo "socket_listen() failed : " . socket_strerror(socket_last_error()) . PHP_EOL;
        exit(1);
    }

    return $socket;
}

# Default
Config::set('port', '6379');
Config::set('role', MASTER_ROLE);
Config::set('master_replid', bin2hex(random_bytes(40)) );
Config::set('master_repl_offset', '0');

for($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    switch ($arg) {
        case '--port':
            Config::set('port', $argv[ ++$i ]);
            break;
        case '--replicaof':
            Config::set('role', SLAVE_ROLE);
            Config::set('master_host', $argv[ ++$i ]);
            Config::set('master_port', $argv[ ++$i ]);
            break;
    }
}

print_r("Configs: \n\n");
print_r(Config::getAll());
print_r("\n\n");

SlaveHandshake::start();

$originSocket = makeOriginSocket();
socket_set_nonblock($originSocket);

$socketPool = [];
$redis = new Redis();

// print_r($redis->handle("*3\r\n$5\r\nPSYNC\r\n$1\r\n?\r\n$2\r\n-1\r\n"));

while (true) {
    if ($newSocket = socket_accept($originSocket)) {
        $socketPool[] = $newSocket;
        socket_set_nonblock($newSocket);
    }

    foreach ($socketPool as $index => $socket) {
        $inputStr = socket_read($socket, 1024);

        if (!$inputStr)
            continue;

        $responses = $redis->handle($inputStr);
        foreach($responses as $response) {
            socket_write($socket, $response);
        }
    }
}
// socket_close($originSocket);
