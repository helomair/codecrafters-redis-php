<?php

namespace app\RedisLibs;

class CommandHandler {

    private string $command;
    private array  $params = [];
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
        $key = $this->params[0];
        $value = $this->params[1];

        $this->keyValues[$key] = $value;

        return "+OK\r\n";
    }

    private function get(): string {
        $key = $this->params[0];
        $value = $this->keyValues[$key];

        $length  = strlen($value);

        return "$$length\r\n$value\r\n";
    }
}
