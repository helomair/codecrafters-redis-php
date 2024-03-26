<?php
error_reporting(E_ALL);


echo "Logs from your program will appear here";

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// Since the tester restarts your program quite often, setting SO_REUSEADDR
// ensures that we don't run into 'Address already in use' errors
socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($sock, "localhost", 6379);

socket_listen($sock, 5);

$connSocket = socket_accept($sock); // Wait for first client

while ($inputStr = socket_read($connSocket, 1024)) {
    $inputs = explode("\r\n", $inputStr);
    foreach ($inputs as $input) {
        if ($input !== "ping")
            continue;

        socket_write($connSocket, "+PONG\r\n");
    }
}

// socket_close($sock);

