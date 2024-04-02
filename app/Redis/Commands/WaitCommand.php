<?php

namespace app\Redis\Commands;

use app\Helpers;
use app\Config;
use app\JobQueue;
use app\Redis\libs\Encoder;

class WaitCommand {
    public static function execute(array $params): ?string {
        $slaveConns = Config::getArray(KEY_REPLICA_CONNS);
        $dones = self::contactSlavesAndGetDones();
        
        $inJobSockets = [];
        foreach($slaveConns as $connInfo) {
            $connID = Helpers::getSocketID($connInfo['conn']);
            $inJobSockets[$connID] = 1;
        }
        Config::setArray(KEY_NOW_IN_JOB_SOCKETS, $inJobSockets);

        $param = new WaitLoopJobParam();
        $param->numreplicas     = intval($params[0]);
        $param->timeout         = intval(microtime(true) * 1000) + intval($params[1]);
        $param->slaveSockets    = $slaveConns;
        $param->dones           = $dones;
        $param->requestedSocket = Config::getSocket(KEY_NOW_RUNNING_SOCKET);

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
            $ack = socket_read($conn, 1024);

            if (!empty($ack)) {
                $dones ++;
            }
        }

        return $dones;
    }

    public static function waitingLoop(WaitLoopJobParam $param) {
        $numreplicas     = $param->numreplicas;
        $slaveConns      = $param->slaveSockets;
        $timeout         = $param->timeout;
        $requestedSocket = $param->requestedSocket;
        $inJobSockets    = Config::getArray(KEY_NOW_IN_JOB_SOCKETS);

        if ( empty($slaveConns) ) {
            return self::waitLoopEnd($param->dones); // Stop job
        }

        foreach($slaveConns as $index => $connInfo) {
            $conn = $connInfo['conn'];
            $ack = socket_read($conn, 1024);
            if (!empty($ack)) {
                $param->dones++;
                unset($param->slaveSockets[$index]);
                unset($inJobSockets[ Helpers::getSocketID($conn) ]);
            }

            if (($param->dones >= $numreplicas) || ($timeout < intval(microtime(true) * 1000))) {
                return self::waitLoopEnd($param->dones); // Stop job
            }
        }

        Config::setArray(KEY_NOW_IN_JOB_SOCKETS, $inJobSockets);

        JobQueue::add(
            [self::class, 'waitingLoop'],
            [$param],
            $requestedSocket
        );
    }

    private static function waitLoopEnd(int $dones): string {
        Config::resetReplicaPropagates();
        Config::setArray(KEY_NOW_IN_JOB_SOCKETS, []);
        return Encoder::encodeIntegerString($dones); 
    }
}

class WaitLoopJobParam {
    public int $numreplicas;
    public int $timeout;
    public $requestedSocket;
    public array $slaveSockets;
    public int $dones = 0;
}