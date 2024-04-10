<?php

namespace app\Redis\Commands;

use app\KeyValues;
use app\Redis\libs\Encoder;
use app\Redis\Datas\StreamData;

class XaddCommand implements CommandInterface {
    public static function execute(array $params): string {
        $key = $params[0];
        $id  = $params[1];
        $err = "";

        $values = [];
        for($i = 2; $i < count($params); $i += 2) {
            $entryKey = $params[$i];
            $entryvalue = $params[ $i+1 ];

            $values[$entryKey] = $entryvalue;
        }

        [$id, $err] = self::addEntry($key, $id, $values);

        $retStr = (empty($err)) ? Encoder::encodeSimpleString($id): Encoder::encodeErrorString($err);
        return $retStr;
    }

    /**
     * @return array [string $id, string $err].
     */
    private static function addEntry(string $key, string $id, array $values): array {
        $streamData = KeyValues::getStreamData($key);

        if (is_null($streamData)) {
            $newStreamData = new StreamData();
            $ret = $newStreamData->addEntry($id, $values);
            KeyValues::set($key, $newStreamData, -1, 'stream');
        }
        else {
            $ret = $streamData->addEntry($id, $values);
        }

        return $ret;
    }
}