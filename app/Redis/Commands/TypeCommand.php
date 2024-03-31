<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\KeyValues;

class TypeCommand {
    public static function execute(array $params): array {
        $value = KeyValues::get($params[0]);
        $retStr = is_null($value) ? 'none' : gettype($value);
        return [Encoder::encodeSimpleString($retStr)];
    }
}