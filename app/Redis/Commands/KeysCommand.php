<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;
use app\KeyValues;

class KeysCommand {
    public static function execute(array $params): array {
        print_r($params);
        $ret = null;

        switch ($params[0]) {
            case "*":
                $ret = KeyValues::getKeys();
        }

        return is_null($ret) ? [] : [Encoder::encodeArrayString($ret)];
    }
}