<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\KeyValues;

class SetCommand {
    public static function execute(array $params): array {
        // ! Race condition?
        $key = $params[0];
        $value = $params[1];

        $expiredAt = -1;
        if ((count($params) > 2) && ($params[2] === 'px')) {
            $nowTime = microtime(true) * 1000;
            $expiredAt = $nowTime + intval($params[3]);
        }

        KeyValues::set($key, $value, $expiredAt);
        return Config::isMaster() ? [Encoder::encodeSimpleString("OK")] : [];
    }
}