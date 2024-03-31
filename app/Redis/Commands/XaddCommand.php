<?php

namespace app\Redis\Commands;

use app\KeyValues;
use app\Redis\libs\Encoder;
use app\Redis\Datas\StreamData;

class XaddCommand {
    public static function execute(array $params): array {
        $key = $params[0];
        $id  = $params[1];
        $err = "";

        $values = [];
        for($i = 2; $i < count($params); $i += 2) {
            $entryKey = $params[$i];
            $entryvalue = $params[ $i+1 ];

            $values[$entryKey] = $entryvalue;
        }

        $dataSet = KeyValues::get($key);
        if (is_null($dataSet)) {
            $newStreamData = new StreamData();
            [$id, $err] = $newStreamData->addEntry($id, $values);
            KeyValues::set($key, $newStreamData, -1, 'stream');
        }
        else if ($dataSet->getType() === 'stream') {
            $streamData = $dataSet->getValue(); // StreamData
            [$id, $err] = $streamData->addEntry($id, $values);
        }

        $retStr = (empty($err)) ? Encoder::encodeSimpleString($id): Encoder::encodeErrorString($err);
        return [$retStr];
    }
}