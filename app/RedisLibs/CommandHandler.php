<?php

namespace app\RedisLibs;

class CommandHandler {

    private string $command;
    private array  $params = [];

    /**
     * @param DataSet array
     */
    private array  $keyValues = [];

    // public function __construct() {

    // }

    public function handle(string $input): string {
        $this->parseInputString($input);

        $ret = "";
        switch ($this->command) {
            case "ping":
                $ret = $this->ping();
                break;
            case "echo":
                $ret = $this->echo();
                break;
            case "set":
                $ret = $this->set();
                break;
            case "get":
                $ret = $this->get();
                break;
        }

        return $ret;
    }

    private function parseInputString(string $input): void {
        $data = explode("\r\n", $input);
        $this->command = $data[2];

        if (count($data) <= 3)
            return;

        foreach (array_slice($data, 3) as $param) {
            if (empty($param)) continue;
            if (strpos($param, '$') !== false) continue;

            $this->params[] = $param;
        }
    }

    private function ping(): string {
        return "+PONG\r\n";
    }

    private function echo(): string {
        $echoStr = $this->params[0];
        $length  = strlen($echoStr);

        return "$$length\r\n$echoStr\r\n";
    }

    private function set(): string {
        // ! Race condition?
        $key = $this->params[0];
        $value = $this->params[1];

        $expiredAt = -1;
        if ((count($this->params) > 2) && ($this->params[2] === 'px')) {
            $nowTime = floor(microtime() * 1000);
            $expiredAt = $nowTime + intval($this->params[3]);
        }

        $this->keyValues[$key] = [
            'value' => $value,
            'expired_at' => $expiredAt
        ];

        return "+OK\r\n";
    }

    private function get(): string {
        $nowTime = floor(microtime() * 1000);

        $key = $this->params[0];
        $data = $this->keyValues[$key];

        if ($data['expired_at'] != -1 && $data['expired_at'] < $nowTime)
            return "$-1\r\n";
        else {
            $value = $data['value'];
            $length  = strlen($value);
            return "$$length\r\n$value\r\n";
        }
    }
}
