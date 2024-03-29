<?php

namespace app\Redis\libs;

use app\Redis\Config;
use app\Redis\libs\Encoder;

use const app\SLAVE_ROLE;

class SlaveHandshake {
    public static function start() {
        if (Config::get('role') !== SLAVE_ROLE)
            return;

        $masterHost = Config::get('master_host');
        $masterPort = intval(Config::get('master_port'));

        if (empty($masterHost) || empty($masterPort)) {
            echo "Role is slave but master host or port not provided" . PHP_EOL;
            return;
        }

        if (($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            echo "socket_create() failed in slaveHandShake : " . socket_strerror(socket_last_error()) . PHP_EOL;
            exit(1);
        }

        if (!socket_connect($socket, $masterHost, $masterPort)) {
            echo "socket_connect() failed in slaveHandShake : " . socket_strerror(socket_last_error()) . PHP_EOL;
        } else {
            # Step1: ping
            self::sendToMaster($socket, ['ping']);

            # Step2: REPLCONF listening-port <PORT>
            self::sendToMaster($socket, ['REPLCONF', 'listening-port', Config::get('port')]);

            # Step3: REPLCONF capa psync2
            self::sendToMaster($socket, ['REPLCONF', 'capa', 'psync2']);

            # Step4: First connection, PSYNC ? -1, (PSYNC <replication ID> <offset>)
            self::sendToMaster($socket, ['PSYNC', '?', '-1']);

            socket_close($socket);
        }
    }

    private static function sendToMaster($socket, array $texts): string {
        $message = Encoder::encodeArrayString($texts);
        socket_write($socket, $message);
        return socket_read($socket, 1024);
    }
}