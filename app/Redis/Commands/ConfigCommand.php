<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;

class ConfigCommand {
    public static function execute(array $params): array {
        $subCommand = strtoupper($params[0]);

        $ret = [];
        switch ($subCommand) {
            case 'GET':
                $ret = self::GET($params[1]);
                break;
        }

        return $ret;
    }

    public static function GET(string $key) {
        $value = Config::getString($key);

        return [Encoder::encodeArrayString([$key, $value])];
    }
}