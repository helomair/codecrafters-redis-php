<?php

namespace app\Redis\libs;

class KeyValues {
    /**
     * @param DataSet
     */
    private static array $keyValue = [];

    public static function getAll() {
        // ? test
        print_r(self::$keyValue);
    }

    public static function set(string $key, string $value, int $expiredAt = -1) {
        // ! Race condition?
        self::$keyValue[$key] = new DataSet($value, $expiredAt);
        return Encoder::encodeSimpleString("OK");
    }

    public static function get(string $key): string {
        $dataSet = self::$keyValue[$key] ?? null;

        if (empty($dataSet) || $dataSet->isExpired())
            return Encoder::nullString();
        else {
            return Encoder::encodeBulkString( $dataSet->getValue() );
        }
    }
}

class DataSet {
    private string $value;
    private int    $expiredAt;

    public function __construct(string $value, int $expiredAt) {
        $this->value = $value;
        $this->expiredAt = $expiredAt;
    }

    public function getValue() {
        return $this->value;
    }

    public function isExpired() {
        $nowTime = microtime(true) * 1000;
        return ($this->expiredAt != -1 && $this->expiredAt < $nowTime);
    }
}
