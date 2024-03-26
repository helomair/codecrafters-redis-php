<?php
error_reporting(E_ALL);


echo "Logs from your program will appear here";

$originSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// Since the tester restarts your program quite often, setting SO_REUSEADDR
// ensures that we don't run into 'Address already in use' errors
socket_set_option($originSocket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($originSocket, "localhost", 6379);

socket_listen($originSocket, 5);

$socketPool = [$originSocket];

while (true) {
    $tmp_socketPool = $socketPool;
    $write = NULL;
    $except = NULL;

    socket_select($tmp_socketPool, $write, $except, NULL);

    foreach ($tmp_socketPool as $index => $socket) {
        if ($socket == $originSocket) {
            $socket = socket_accept($originSocket); // Get another socket resources.
            $socketPool[] = $socket;
        }

        $inputStr = socket_read($socket, 1024);

        if (empty ($inputStr))
            continue;

        $ret = socket_write($socket, "+PONG\r\n");
    }
}
