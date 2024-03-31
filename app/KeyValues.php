<?php

namespace app;

class KeyValues {
    /**
     * @param DataSet
     */
    private static array $keyValue = [];

    private static int   $dbNumber = 0;

    public static function getAll() {
        // ? test
        print_r(self::$keyValue);
    }

    public static function getDBNum(): int {
        return self::$dbNumber;
    }

    public static function useDB(int $number): void {
        self::$dbNumber = $number;
    }

    public static function set(string $key, $value, int $expiredAt = -1, string $type = 'string'): void {
        // ! Race condition?
        $newData = new DataSet($value, $expiredAt, $type);
        self::$keyValue[self::$dbNumber][$key] = $newData;
    }

    public static function get(string $key): ?DataSet {
        $dataSet = self::$keyValue[self::$dbNumber][$key] ?? null;

        if (empty($dataSet) || $dataSet->isExpired())
            return null;
        else {
            return $dataSet;
        }
    }

    public static function getKeys(): array {
        return array_keys(self::$keyValue[self::$dbNumber]);
    }
}

class DataSet {
    private $value;
    private string $type;
    private int    $expiredAt;

    public function __construct($value, int $expiredAt, string $type = 'string') {
        $this->value = $value;
        $this->expiredAt = $expiredAt;
        $this->type = $type;
    }

    public function getValue() {
        return $this->value;
    }

    public function getType() {
        if ($this->type === 'string')
            return gettype($this->type);

        return $this->type;
    }

    public function isExpired(): bool {
        $nowTime = microtime(true) * 1000;
        return ($this->expiredAt != -1 && $this->expiredAt < $nowTime);
    }
}
