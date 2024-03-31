<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\KeyValues;

class TypeCommand {
    public static function execute(array $params): array {
        $dataSet = KeyValues::get($params[0]);
        $retStr = is_null($dataSet) ? 'none' : $dataSet->getType();
        return [Encoder::encodeSimpleString($retStr)];
    }
}