<?php

namespace app;

use app\Redis\Redis;
use app\Config;
use app\Redis\slave\SlaveHandshake;

require_once 'autoload.php';

echo "Logs from your program will appear here";

define('MASTER_ROLE', 'master');
define('SLAVE_ROLE', 'slave');

define('KEY_REPLICA_CONNS', 'replica_connections');
define('KEY_SELF_PORT', 'port');
define('KEY_SELF_ROLE', 'role');
define('KEY_MASTER_REPLID', 'master_replid');
define('KEY_MASTER_REPL_OFFSET', 'master_repl_offset');
define('KEY_MASTER_HOST', 'master_host');
define('KEY_MASTER_PORT', 'master_port');

function makeOriginSocket() {
    $port = intval(Config::getString(KEY_SELF_PORT));
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
Config::setString(KEY_SELF_PORT, '6379');
Config::setString(KEY_SELF_ROLE, MASTER_ROLE);
Config::setString(KEY_MASTER_REPLID, bin2hex(random_bytes(40)) );
Config::setString(KEY_MASTER_REPL_OFFSET, '0');

for($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    switch ($arg) {
        case '--port':
            Config::setString(KEY_SELF_PORT, $argv[ ++$i ]);
            break;
        case '--replicaof':
            Config::setString(KEY_SELF_ROLE, SLAVE_ROLE);
            Config::setString(KEY_MASTER_HOST, $argv[ ++$i ]);
            Config::setString(KEY_MASTER_PORT, $argv[ ++$i ]);
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

        $responses = $redis->handle($inputStr, $socket);
        foreach($responses as $response) {
            socket_write($socket, $response);
        }
    }
}
// socket_close($originSocket);
