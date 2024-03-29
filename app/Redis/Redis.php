<?php

namespace app\Redis;

use app\Config;
use app\Redis\libs\Encoder;
use app\Redis\libs\KeyValues;
use app\Redis\master\MasterPropagate;

class Redis {
    private string $command = "";

    private array  $params = [];
    private $requestedSocket;
    private const WRITE_COMMANDS = ['SET', 'DEL'];

    public function handle(string $input, $requestedSocket): array {
        $this->parseInputString($input);
        $this->requestedSocket = $requestedSocket;

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
                break;
            case "PSYNC":
                $ret = $this->psync();
                break;
            default:
                $ret = [];
        }

        if (in_array($this->command, self::WRITE_COMMANDS)) {
            MasterPropagate::sendParamsToSlave($this->command, $this->params);
        }

        return $ret;
    }

    private function parseInputString(string $input): void {
        $this->command = "";
        $this->params = [];

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

    private function ping(): array {
        return [Encoder::encodeSimpleString("PONG")];
    }

    private function echo(): array {
        $echoStr = $this->params[0];
        return [Encoder::encodeSimpleString($echoStr)];
    }

    private function set(): array {
        // ! Race condition?
        $key = $this->params[0];
        $value = $this->params[1];

        $expiredAt = -1;
        if ((count($this->params) > 2) && ($this->params[2] === 'px')) {
            $nowTime = microtime(true) * 1000;
            $expiredAt = $nowTime + intval($this->params[3]);
        }

        KeyValues::set($key, $value, $expiredAt);

        return [Encoder::encodeSimpleString("OK")];
    }

    private function get(): array {
        return [KeyValues::get($this->params[0])];
    }

    private function infos(): array {
        return [Encoder::encodeKeyValueBulkStrings(Config::getAllStrings())];
    }

    private function replconf(): array {
        $ret = [Encoder::encodeSimpleString("OK")];

        if ($this->params[0] !== 'listening-port') {
            return $ret;
        }

        $slavePort = $this->params[1];
        $slaveConns = Config::getArray(KEY_REPLICA_CONNS);
        $slaveConns[$slavePort][] = $this->requestedSocket;
        Config::setArray(KEY_REPLICA_CONNS, $slaveConns);

        return $ret;
    }

    private function psync(): array {
        $replid = Config::getString(KEY_MASTER_REPLID);
        $offset = Config::getString(KEY_MASTER_REPL_OFFSET);

        $fullSync = Encoder::encodeSimpleString("FULLRESYNC {$replid} {$offset}");

        $fileContent = "UkVESVMwMDEx+glyZWRpcy12ZXIFNy4yLjD6CnJlZGlzLWJpdHPAQPoFY3RpbWXCbQi8ZfoIdXNlZC1tZW3CsMQQAPoIYW9mLWJhc2XAAP/wbjv+wP9aog==";
        $fullSyncFile = Encoder::encodeFileString(base64_decode($fileContent));

        return [$fullSync, $fullSyncFile];
    }
}