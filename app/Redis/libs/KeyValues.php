<?php

namespace app\Redis\libs;

class KeyValues {
    /**
     * @param DataSet
     */
    private static array $keyValue = [];

    public static function set(array $params) {
        // ! Race condition?
        $key = $params[0];
        $value = $params[1];

        $expiredAt = -1;
        if ((count($params) > 2) && ($params[2] === 'px')) {
            $nowTime = microtime(true) * 1000;
            $expiredAt = $nowTime + intval($params[3]);
        }

        static::$keyValue[$key] = new DataSet($value, $expiredAt);

        return "+OK\r\n";
    }

    public static function get(array $params) {
        $key = $params[0];
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
