<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\Redis\libs\KeyValues;

class ReplconfCommand {
    public static function execute(array $params, $requestedSocket): array {
        $ret = [Encoder::encodeSimpleString("OK")];

        $type = $params[0];
        switch ($type) {
            case 'listening-port':
                self::listeningPort($requestedSocket);
                break;

            case 'GETACK':
                $ret = self::GETACK();
                break;
        }

        return $ret;
    }

    private static function listeningPort($requestedSocket): void {
        $slaveConns = Config::getArray(KEY_REPLICA_CONNS);
        $slaveConns[] = [
            'conn' => $requestedSocket,
            'propagates' => 0
        ];
        Config::setArray(KEY_REPLICA_CONNS, $slaveConns);
        print_r("Connected Slave : ");
        print_r($requestedSocket);
        print_r("\n");
    }

    private static function GETACK(): array {
        $datas = ['REPLCONF', 'ACK', Config::getString(KEY_MASTER_REPL_OFFSET)];
        return [Encoder::encodeArrayString($datas)];
    }
}