<?php

namespace app;

use app\Redis\Redis;
use app\Redis\Config;

require_once 'autoload.php';

echo "Logs from your program will appear here";

function makeOriginSocket(int $port) {
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

$port = 6379;
Config::set('role', 'master');

for($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    switch ($arg) {
        case '--port':
            $port = intval($argv[ ++$i ]);
            break;
        case '--replicaof':
            Config::set('role', 'slave');
            Config::set('master_host', $argv[ ++$i ]);
            Config::set('master_port', $argv[ ++$i ]);
            break;
    }
}

print_r("Configs: \n\n");
print_r(Config::getAll());
print_r("\n\n");

$originSocket = makeOriginSocket($port);
socket_set_nonblock($originSocket);

$socketPool = [];
$redis = new Redis();

while (true) {
    if ($newSocket = socket_accept($originSocket)) {
        $socketPool[] = $newSocket;
        socket_set_nonblock($newSocket);
    }

    foreach ($socketPool as $index => $socket) {
        $inputStr = socket_read($socket, 1024);

        if (!$inputStr)
            continue;

        socket_write($socket, $redis->handle($inputStr));
    }
}
// socket_close($originSocket);
