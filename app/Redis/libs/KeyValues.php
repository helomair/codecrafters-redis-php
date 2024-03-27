<?php

namespace app\Redis\libs;

class KeyValues {
    /**
     * @param DataSet
     */
    private static array $keyValue = [];

    public static function set(string $key, string $value, int $expiredAt = -1) {
        // ! Race condition?
        static::$keyValue[$key] = new DataSet($value, $expiredAt);
        return "+OK\r\n";
    }

    public static function get(string $key) {
        $dataSet = static::$keyValue[$key];

        if ($dataSet->isExpired())
            return "$-1\r\n";
        else {
            $value = $dataSet->getValue();
            $length = strlen($value);
            return "$$length\r\n$value\r\n";
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
