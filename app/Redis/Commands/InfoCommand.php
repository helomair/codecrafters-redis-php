<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\KeyValues;

class InfoCommand implements CommandInterface {
    public static function execute(array $params = []): string {
        return Encoder::encodeKeyValueBulkStrings(Config::getAllStrings());
    }
}