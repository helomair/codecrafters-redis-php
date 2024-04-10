<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\KeyValues;

class PingCommand implements CommandInterface {
    public static function execute(array $params = []): string {
        return Config::isMaster() ? Encoder::encodeSimpleString("PONG") : "";
    }
}