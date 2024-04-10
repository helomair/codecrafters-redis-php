<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\KeyValues;

class GetCommand implements CommandInterface {
    public static function execute(array $params): string {
        $dataSet = KeyValues::get($params[0]);
        
        $retStr = is_null($dataSet) ? 
            Encoder::nullString() : 
            Encoder::encodeBulkString($dataSet->getValue());

        return $retStr;
    }
}