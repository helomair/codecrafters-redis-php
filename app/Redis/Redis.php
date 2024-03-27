<?php

namespace app\Redis;

use app\Helpers\Helpers;
use app\Redis\libs\KeyValues;

class Redis {

    private string $command;
    private array  $params = [];

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
            case "INFO":
                $ret = $this->infos();
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
        return Helpers::makeBulkString($echoStr);
    }

    private function set(): string {
        // ! Race condition?
        $key = $this->params[0];
        $value = $this->params[1];

        $expiredAt = -1;
        if ((count($this->params) > 2) && ($this->params[2] === 'px')) {
            $nowTime = microtime(true) * 1000;
            $expiredAt = $nowTime + intval($this->params[3]);
        }

        KeyValues::set($key, $value, $expiredAt);

        return "+OK\r\n";
    }

    private function get(): string {
        return KeyValues::get($this->params[0]);
    }

    private function infos(): string {
        return Helpers::makeBulkString("role:master");
    }
}
