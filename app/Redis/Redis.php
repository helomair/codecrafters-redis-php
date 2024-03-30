<?php

namespace app\Redis;

use app\Config;
use app\Redis\libs\Encoder;
use app\Redis\libs\InputParser;
use app\Redis\libs\KeyValues;
use app\Redis\master\MasterPropagate;

class Redis {
    private bool   $masterAcked = false;
    private array  $params = [];
    private string $command = "";
    private $requestedSocket;

    public function handle(string $inputStr, $requestedSocket): array {
        $parsedInputs = InputParser::init($inputStr)->parse();
        $this->requestedSocket = $requestedSocket;

        $responses = [];
        foreach($parsedInputs as $inputs) {
            if (empty($inputs)) continue;

            $this->command = strtoupper($inputs[0]);
            $this->params = array_slice($inputs, 1);

            // print_r($this->command . " =>  ");
            // print_r($this->params);
            // print_r("\n");


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
                case "WAIT":
                    $ret = $this->wait();
                    break;
                default:
                    $ret = [];
            }
    
            MasterPropagate::sendParamsToSlave($this->command, $this->params);
            $this->addCommandOffset();

            $responses[] = $ret;
        }

        return $responses;
    }

    private function ping(): array {
        return Config::isMaster() ? [Encoder::encodeSimpleString("PONG")] : [];
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
        return Config::isMaster() ? [Encoder::encodeSimpleString("OK")] : [];
    }

    private function get(): array {
        return [KeyValues::get($this->params[0])];
    }

    private function infos(): array {
        return [Encoder::encodeKeyValueBulkStrings(Config::getAllStrings())];
    }

    private function replconf(): array {
        $ret = [Encoder::encodeSimpleString("OK")];

        $type = $this->params[0];
        switch ($type) {
            case 'listening-port':
                // $slavePort = $this->params[1];
                $slaveConns = Config::getArray(KEY_REPLICA_CONNS);
                $slaveConns[] = $this->requestedSocket;
                Config::setArray(KEY_REPLICA_CONNS, $slaveConns);
                break;

            case 'GETACK':
                $this->masterAcked = true;
                $datas = ['REPLCONF', 'ACK', Config::getString(KEY_MASTER_REPL_OFFSET)];
                $ret = [Encoder::encodeArrayString($datas)];
                break;
        }

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

    public function wait(): array {
        $numreplicas = count(Config::getArray(KEY_REPLICA_CONNS));
        $timeout = $this->params[1];
        return [Encoder::encodeIntegerString($numreplicas)];
    }

    private function addCommandOffset(): void {
        if (!$this->masterAcked)
            return;

        $originInputStr = Encoder::encodeArrayString([$this->command, ...$this->params]);

        $socket = $this->requestedSocket;
        $slaveToMasterSocket = Config::getArray(KEY_MASTER_SOCKET)[0];

        if ( !is_null($slaveToMasterSocket) && ($socket === $slaveToMasterSocket) ) {
            $bytes = strlen($originInputStr);
            $nowOffset = Config::getInt(KEY_MASTER_REPL_OFFSET);
            $nowOffset += $bytes;
            Config::setString(KEY_MASTER_REPL_OFFSET, strval($nowOffset));
        }
    }
}