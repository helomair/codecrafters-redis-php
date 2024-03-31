<?php

namespace app\Redis\Commands;

use app\KeyValues;
use app\Redis\libs\Encoder;
use app\Redis\Datas\StreamData;

class XaddCommand {
    public static function execute(array $params): array {
        $key = $params[0];
        $id  = $params[1];

        $values = [];
        for($i = 2; $i < count($params); $i += 2) {
            $entryKey = $params[$i];
            $entryvalue = $params[ $i+1 ];

            $values[$entryKey] = $entryvalue;
        }

        $newStreamData = new StreamData($id, $values);
        KeyValues::set($key, $newStreamData, -1, 'stream');

        return [Encoder::encodeSimpleString($id)];
    }
}