<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\KeyValues;

class InfoCommand {
    public static function execute(): string {
        return Encoder::encodeKeyValueBulkStrings(Config::getAllStrings());
    }
}