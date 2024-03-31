<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\KeyValues;

class ReplconfCommand {
    public static function execute(array $params): string {
        $ret = Encoder::encodeSimpleString("OK");

        $type = $params[0];
        switch ($type) {
            case 'listening-port':
                self::listeningPort();
                break;

            case 'GETACK':
                $ret = self::GETACK();
                break;
        }

        return $ret;
    }

    private static function listeningPort(): void {
        $slaveConns = Config::getArray(KEY_REPLICA_CONNS);
        $slaveConns[] = [
            'conn' => Config::getSocket(KEY_NOW_RUNNING_SOCKET),
            'propagates' => 0
        ];
        Config::setArray(KEY_REPLICA_CONNS, $slaveConns);
        print_r("Connected Slave : ");
        print_r(Config::getSocket(KEY_NOW_RUNNING_SOCKET));
        print_r("\n");
    }

    private static function GETACK(): string {
        $datas = ['REPLCONF', 'ACK', Config::getString(KEY_MASTER_REPL_OFFSET)];
        return Encoder::encodeArrayString($datas);
    }
}