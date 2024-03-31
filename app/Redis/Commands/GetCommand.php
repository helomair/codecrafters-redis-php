<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\Redis\libs\KeyValues;

class GetCommand {
    public static function execute(array $params): array {
        return [KeyValues::get($params[0])];
    }
}