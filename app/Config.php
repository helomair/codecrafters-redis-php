<?php

namespace app;

class Config {
    private static array $configs = [];

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

    public static function setArray(string $key, array $values): void {
        self::$configs[$key] = $values;
    }

    public static function setString(string $key, string $value): void {
        self::$configs[$key] = $value;
    }
    // public static function getAll(): array {
    //     return self::$configs;
    // }

    // public static function get(string $key): string {
    //     return self::$configs[$key][0] ?? "";
    // }

    // public static function getArray(string $key): array {
    //     return self::$configs[$key] ?? [];
    // }

    // public static function set(string $key, string $value): void {
    //     self::$configs[$key] = [$value];
    // }

    // public static function setArray(string $key, array $values): void {
    //     self::$configs[$key] = $values;
    // }
}