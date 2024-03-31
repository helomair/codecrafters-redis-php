<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;

class WaitCommand {
    public static function execute(array $params): array {
        $numreplicas = intval($params[0]);
        $timeout = microtime(true) * 1000 + intval($params[1]);
        
        $slaveConns = Config::getArray(KEY_REPLICA_CONNS);

        $dones = self::contactSlavesAndGetDones($slaveConns);


        while( (microtime(true) * 1000 ) <= $timeout ) {
            if (empty($slaveConns) || $dones >= $numreplicas)
                break;

            foreach($slaveConns as $index => $connInfo) {
                $conn = $connInfo['conn'];
                $ack = socket_read($conn, 1024);

                if (!empty($ack)) {
                    $dones++;
                    unset($slaveConns[$index]);
                }

                if ($dones >= $numreplicas)
                    break;
            }
        }

        Config::resetReplicaPropagates();
        return [Encoder::encodeIntegerString($dones)];
    }

    private static function contactSlavesAndGetDones(array &$slaveConns): int {
        $dones = count($slaveConns);

        foreach($slaveConns as $connInfo) {
            if ($connInfo['propagates'] === 0) continue;

            $dones = 0;
            $conn = $connInfo['conn'];
            $context = Encoder::encodeArrayString(['REPLCONF','GETACK','*']);
            socket_write($conn, $context);
        }

        return $dones;
    }

}