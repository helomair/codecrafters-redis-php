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

    public static function useDB(int $number): void {
        self::$dbNumber = $number;
    }

    public static function setToSelectedDB(
        int $dbNumber, 
        string $key, 
        string $value, 
        int $expiredAt = -1
    ): void {
        self::$keyValue[$dbNumber][$key] = new DataSet($value, $expiredAt);
    }

    public static function set(string $key, string $value, int $expiredAt = -1): void {
        // ! Race condition?
        self::$keyValue[self::$dbNumber][$key] = new DataSet($value, $expiredAt);
    }

    public static function get(string $key): ?string {
        $dataSet = self::$keyValue[self::$dbNumber][$key] ?? null;

        if (empty($dataSet) || $dataSet->isExpired())
            return null;
        else {
            return $dataSet->getValue();
        }
    }

    public static function getKeys(): array {
        return array_keys(self::$keyValue[self::$dbNumber]);
    }
}

class DataSet {
    private string $value;
    private int    $expiredAt;

    public function __construct(string $value, int $expiredAt) {
        $this->value = $value;
        $this->expiredAt = $expiredAt;
    }

    public function getValue(): string {
        return $this->value;
    }

    public function isExpired(): bool {
        $nowTime = microtime(true) * 1000;
        return ($this->expiredAt != -1 && $this->expiredAt < $nowTime);
    }
}
