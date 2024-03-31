<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\Redis\libs\KeyValues;

class PingCommand {
    public static function execute(): array {
        return Config::isMaster() ? [Encoder::encodeSimpleString("PONG")] : [];
    }
}