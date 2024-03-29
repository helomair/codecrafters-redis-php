<?php

namespace app\Redis;

use app\Config;
use app\Redis\libs\Encoder;
use app\Redis\libs\KeyValues;
use app\Redis\master\MasterPropagate;

class Redis {
    private array $inputArguments = [];

    private array  $params = [];
    private string $command = "";
    private $requestedSocket;
    private const WRITE_COMMANDS = ['SET', 'DEL'];

    public function handle(string $input, $requestedSocket): array {
        $this->parseInputString($input);
        $this->requestedSocket = $requestedSocket;

        $ret = [];
        foreach($this->inputArguments as $arg) {
            $this->command = strtoupper($arg[0]);
            $this->params = array_slice($arg, 1) ?? [];
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
                $ret = Config::isMaster() ? $ret : [];
                MasterPropagate::sendParamsToSlave($this->command, $this->params);
            }
        }

        return $ret;
    }

    private function parseInputString(string $inputStr): void {
        $this->inputArguments = [];

        $inputs = explode("\r\n", $inputStr);
        $length = count($inputs);
        
        for($i = 0; $i < $length; $i++) {
            $this->parseInputStringVerifyArgs($inputs, $i);
        }
    }

    private function parseInputStringVerifyArgs(array &$inputs, int &$i) {
        $RESPArrayLength = $inputs[$i];
        if( ($pos = strpos($RESPArrayLength, '*')) === false)
            return;

        $RESPArrayLength = intval(substr($RESPArrayLength, $pos + 1));
        $args = [];
        while($RESPArrayLength > 0) {
            $arg = $inputs[ ++$i ];
            
            if (strpos($arg, '$') !== false) continue;

            $args[] = $arg;
            $RESPArrayLength --;
        }
        $this->inputArguments[] = $args;
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