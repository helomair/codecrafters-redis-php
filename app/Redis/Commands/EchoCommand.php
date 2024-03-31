<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\KeyValues;

class EchoCommand {
    public static function execute(array $params): string {
        $echoStr = $params[0];
        return Encoder::encodeSimpleString($echoStr);
    }
}