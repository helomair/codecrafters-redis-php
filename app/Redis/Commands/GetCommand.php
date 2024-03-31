<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\KeyValues;

class GetCommand {
    public static function execute(array $params): array {
        $value = KeyValues::get($params[0]);
        $retStr = is_null($value) ? Encoder::nullString() : Encoder::encodeBulkString($value);
        return [$retStr];
    }
}