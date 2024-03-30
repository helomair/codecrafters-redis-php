<?php

namespace app\Redis\master;

use app\Config;
use app\Redis\libs\Encoder;

class MasterPropagate {
    private const WRITE_COMMANDS = ['SET', 'DEL'];
    
    public static function sendParamsToSlave(string $command, array $params) {
        if (!Config::isMaster()) return;
        if (!in_array($command, self::WRITE_COMMANDS)) return;

        $message = Encoder::encodeArrayString([$command, ...$params]);
        self::toSlave($message);
    }
    public static function toSlave(string $message) {
        if (!Config::isMaster()) return;
        
        $slaveConns = Config::getArray(KEY_REPLICA_CONNS);

        foreach($slaveConns as &$connInfo) {
            $conn = $connInfo['conn'];
            socket_write($conn, $message);
            $connInfo['propagates'] ++;
        }

        Config::setArray(KEY_REPLICA_CONNS, $slaveConns);
    }
}