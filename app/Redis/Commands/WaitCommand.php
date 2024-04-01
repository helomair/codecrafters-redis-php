<?php

namespace app\Redis\Commands;

use app\Config;
use app\JobQueue;
use app\Redis\libs\Encoder;

class WaitCommand {
    public static function execute(array $params): ?string {
        $dones = self::contactSlavesAndGetDones();

        $param = new WaitLoopJobParam();
        $param->numreplicas     = intval($params[0]);
        $param->timeout         = ceil(microtime(true) * 1000) + intval($params[1]);
        $param->slaveSockets    = Config::getArray(KEY_REPLICA_CONNS);
        $param->dones           = $dones;
        $param->requestedSocket = Config::getSocket(KEY_NOW_RUNNING_SOCKET);

        print_r(Config::getArray(KEY_REPLICA_CONNS));

        JobQueue::add(
            [self::class, 'waitingLoop'],
            [$param],
            Config::getSocket(KEY_NOW_RUNNING_SOCKET)
        );

        return null;
    }

    private static function contactSlavesAndGetDones(): int {
        $dones = count(Config::getArray(KEY_REPLICA_CONNS));

        foreach(Config::getArray(KEY_REPLICA_CONNS) as $connInfo) {
            if ($connInfo['propagates'] === 0) continue;

            $dones = 0;
            $conn = $connInfo['conn'];
            $context = Encoder::encodeArrayString(['REPLCONF','GETACK','*']);
            socket_write($conn, $context);
        }

        return $dones;
    }

    public static function waitingLoop(WaitLoopJobParam $param) {
        $numreplicas     = $param->numreplicas;
        $slaveConns      = $param->slaveSockets;
        $timeout         = $param->timeout;
        $requestedSocket = $param->requestedSocket;

        if ( empty($slaveConns) || ($param->dones >= $numreplicas) || ($timeout < ceil(microtime(true) * 1000)) ) {
            print_r("Step1\n");
            print_r("timeout: {$timeout}, nowTime: " . round(microtime(true) * 1000) . "\n");
            Config::resetReplicaPropagates();
            return Encoder::encodeIntegerString($param->dones); // Stop job
        }

        foreach($slaveConns as $index => $connInfo) {
            $conn = $connInfo['conn'];
            $ack = socket_read($conn, 1024);

            if (!empty($ack)) {
                print_r($ack . "\n");
                $param->dones++;
                unset($slaveConns[$index]);
            }

            if ($param->dones >= $numreplicas) {
                print_r("Step2\n");
                Config::resetReplicaPropagates();
                return Encoder::encodeIntegerString($param->dones); // Stop job
            }
        }

        $param->slaveSockets = $slaveConns;

        JobQueue::add(
            [self::class, 'waitingLoop'],
            [$param],
            $requestedSocket
        );
    }
}

class WaitLoopJobParam {
    public int $numreplicas;
    public int $timeout;
    public $requestedSocket;
    public array $slaveSockets;
    public int $dones = 0;
}