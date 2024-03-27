<?php

namespace app\Redis;

class Config {
    private static array $configs = [];
    public static function getAll(): array {
        return self::$configs;
    }

    public static function get(string $key): string {
        return self::$configs[$key] ?? "";
    }

    public static function set(string $key, string $value) {
        self::$configs[$key] = $value;
    }
}