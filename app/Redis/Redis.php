<?php

namespace app\Redis;

use app\Redis\libs\Helper;
use app\Redis\libs\Encoder;
use app\Redis\libs\KeyValues;

class Redis {
    private string $command;

    private array  $params = [];

    public function __construct() {
        Config::set('master_replid', Helper::generateRandomString(40));
        Config::set('master_repl_offset', '0');
    }

    public function handle(string $input): string {
        $this->parseInputString($input);

        $ret = "";
        switch ($this->command) {
            case "PING":
                $ret = $this->ping();
                break;
            case "ECHO":
                $ret = $this->echo();
                break;
            case "SET":
                $ret = $this->set();
                break;
            case "GET":
                $ret = $this->get();
                break;
            case "INFO":
                $ret = $this->infos();
                break;
            case "REPLCONF":
                $ret = $this->replconf();
        }

        return $ret;
    }

    private function parseInputString(string $input): void {
        $data = explode("\r\n", $input);
        $this->command = strtoupper($data[2]);

        if (count($data) <= 3)
            return;

        foreach (array_slice($data, 3) as $param) {
            if (empty($param)) continue;
            if (strpos($param, '$') !== false) continue;

            $this->params[] = $param;
        }
    }

    private function ping(): string {
        return Encoder::encodeSimpleString("PONG");
    }

    private function echo(): string {
        $echoStr = $this->params[0];
        return Encoder::encodeSimpleString($echoStr);
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

        return Encoder::encodeSimpleString("OK");
    }

    private function get(): string {
        return KeyValues::get($this->params[0]);
    }

    private function infos(): string {
        return Encoder::encodeKeyValueBulkStrings(Config::getAll());
    }

    private function replconf(): string {
        return Encoder::encodeSimpleString("OK");
    }
}