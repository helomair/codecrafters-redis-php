<?php

namespace app;

use app\Config;
use app\Redis\Redis;
use app\Redis\RDB\RDBParser;
use app\KeyValues;
use app\Redis\libs\InputParser;
use app\Redis\slave\SlaveHandshake;

require_once 'autoload.php';

echo "Logs from your program will appear here";

define('MASTER_ROLE', 'master');
define('SLAVE_ROLE', 'slave');

define('KEY_DIR_PATH', 'dir');
define('KEY_DB_FILENAME', 'dbfilename');
define('KEY_REPLICA_CONNS', 'replica_connections');
define('KEY_SELF_PORT', 'port');
define('KEY_SELF_ROLE', 'role');
define('KEY_MASTER_REPLID', 'master_replid');
define('KEY_MASTER_REPL_OFFSET', 'master_repl_offset');
define('KEY_MASTER_HOST', 'master_host');
define('KEY_MASTER_PORT', 'master_port');
define('KEY_MASTER_SOCKET', 'master_socket');

function makeSelfListeningSocket() {
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

function sendResponses(array $responses, $socket) {
    foreach($responses as $response) {
        foreach ($response as $text) 
            socket_write($socket, $text);
    }
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
        case '--dir':
            Config::setString(KEY_DIR_PATH, $argv[ ++$i ]);
            break;
        case '--dbfilename':
            Config::setString(KEY_DB_FILENAME, $argv[ ++$i ]);
            break;
    }
}

print_r("Configs: \n\n");
print_r(Config::getAll());
print_r("\n\n");

$selfListeningSocket = makeSelfListeningSocket();
socket_set_nonblock($selfListeningSocket);

$slaveToMasterSocket = Config::isMaster() ? null : SlaveHandshake::start();
Config::setArray(KEY_MASTER_SOCKET, [$slaveToMasterSocket]);

$socketPool = !is_null($slaveToMasterSocket) ? [$slaveToMasterSocket] : [];
$redis = new Redis();

RDBParser::parse();
// KeyValues::getAll();

// print_r($redis->handle("*1\r\n$4\r\nping\r\n", null));
// exit(0);

while (true) {
    if ( $newSocket = socket_accept($selfListeningSocket) ) {
        $socketPool[] = $newSocket;
        socket_set_nonblock($newSocket);
    }

    $infos = [];
    foreach ($socketPool as $index => $socket) {
        $inputStr = socket_read($socket, 1024);
        if (!empty($inputStr)) {
            $infos[] = [$inputStr, $socket];
        }
    }

    foreach ($infos as $inputStrAndSocket) {
        if (!$inputStrAndSocket)
            continue;

        $inputStr = $inputStrAndSocket[0];
        $socket = $inputStrAndSocket[1];
        $responses = $redis->handle($inputStr, $socket);
        // KeyValues::getAll();

        sendResponses($responses, $socket);

    }
}
// socket_close($originSocket);
