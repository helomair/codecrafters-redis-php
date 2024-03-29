<?php

namespace app\Redis\master;

use app\Config;
use app\Redis\libs\Encoder;

class MasterPropagate {
    public static function sendParamsToSlave(string $command, array $params) {
        $message = Encoder::encodeArrayString([$command, ...$params]);
        self::toSlave($message);
    }
    public static function toSlave(string $message) {
        $slaveConns = Config::getArray(KEY_REPLICA_CONNS);
        
        foreach($slaveConns as $port => $conns) {
            foreach($conns as $conn)
                socket_write($conn, $message);
        }
    }
}