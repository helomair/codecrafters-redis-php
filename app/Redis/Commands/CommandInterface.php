<?php

namespace app\Redis\Commands;

interface CommandInterface {
    public static function execute(array $params): ?string;
}