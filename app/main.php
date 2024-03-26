<?php

namespace app;

use app\RedisLibs\CommandHandler;

require_once 'autoload.php';

echo "Logs from your program will appear here";


if (($originSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() failed : " . socket_strerror(socket_last_error()) . PHP_EOL;
    exit(1);
}

if (socket_set_option($originSocket, SOL_SOCKET, SO_REUSEADDR, 1) === false) {
    echo "socket_set_option() failed : " . socket_strerror(socket_last_error()) . PHP_EOL;
    exit(1);
}

if (socket_bind($originSocket, "localhost", 6379) === false) {
    echo "socket_bind() failed : " . socket_strerror(socket_last_error()) . PHP_EOL;
    exit(1);
}

if (socket_listen($originSocket, 5) === false) {
    echo "socket_listen() failed : " . socket_strerror(socket_last_error()) . PHP_EOL;
    exit(1);
}

socket_set_nonblock($originSocket);

$socketPool = [];
$commandHandler = new CommandHandler();

while (true) {
    if ($newSocket = socket_accept($originSocket)) {
        $socketPool[] = $newSocket;
        socket_set_nonblock($newSocket);
    }

    foreach ($socketPool as $index => $socket) {
        $inputStr = socket_read($socket, 1024);

        if (!$inputStr)
            continue;

        socket_write($socket, $commandHandler->handle($inputStr));
    }
}
