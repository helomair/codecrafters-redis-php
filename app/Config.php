<?php

namespace app;

class Config {
    private static array $configs = [];

    public static function isMaster(): bool {
        return Config::getString(KEY_SELF_ROLE) === MASTER_ROLE;
    }

    public static function getAll(): array {
        return self::$configs;
    }

    public static function getAllStrings(): array {
        $ret = [];

        foreach(self::$configs as $key => $config) {
            if (!is_string($config))
                continue;

            $ret[$key] = $config;
        }

        return $ret;
    }

    public static function getArray(string $key): array {
        return self::$configs[$key] ?? [];
    }

    public static function getString(string $key): string {
        $ret = self::$configs[$key] ?? "";

        if (is_array($ret)) {
            echo "Config get string but key gets array, key : {$key}\n";
            exit(1);
        }

        return $ret;
    }

    public static function getInt(string $key): int {
        $ret = self::$configs[$key] ?? "";

        if (filter_var($ret, FILTER_VALIDATE_INT) === false) {
            echo "Config get int but value can't convert to int, key : {$key}\n";
            exit(1);
        }

        return intval($ret);
    }

    public static function setArray(string $key, array $values): void {
        self::$configs[$key] = $values;
    }

    public static function setString(string $key, string $value): void {
        self::$configs[$key] = $value;
    }
}