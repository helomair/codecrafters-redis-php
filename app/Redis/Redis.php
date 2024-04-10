<?php

namespace app\Redis;

use app\Config;
use app\Redis\libs\Encoder;
use app\Redis\libs\InputParser;
use app\Redis\master\MasterPropagate;


class Redis {
    private array  $params = [];
    private string $command = "";
    // private $requestedSocket;

    private const COMMANDS_NO_OFFSET = ['Fullresync'];

    public function handle(string $inputStr): ?array {
        $parsedInputs = InputParser::init($inputStr)->parse();

        $responses = [];
        foreach($parsedInputs as $inputs) {
            if (empty($inputs)) continue;

            $this->command = ucfirst( strtolower($inputs[0]) );
            $this->params = array_slice($inputs, 1);

            if (!is_null( $response = $this->commandExecution() ))
                $responses[] = $response;
    
            MasterPropagate::sendParamsToSlave($this->command, $this->params);
            $this->addCommandOffset();

        }

        return $responses;
    }

    private function commandExecution(): ?string {
        $commandClass = "app\\Redis\\Commands\\{$this->command}Command";

        $ret = "";
        if( class_exists($commandClass) ) {
            $ret = $commandClass::execute($this->params);
        }

        return $ret;
    }

    private function addCommandOffset(): void {
        if (in_array($this->command, self::COMMANDS_NO_OFFSET))
            return;

        $originInputStr = Encoder::encodeArrayString([$this->command, ...$this->params]);

        $socket = Config::getSocket(KEY_NOW_RUNNING_SOCKET);
        $slaveToMasterSocket = Config::getSocket(KEY_MASTER_SOCKET);

        // ! Race Condition
        if ( !is_null($slaveToMasterSocket) && ($socket === $slaveToMasterSocket) ) {
            $bytes = strlen($originInputStr);
            $nowOffset = Config::getInt(KEY_MASTER_REPL_OFFSET);
            $nowOffset += $bytes;
            Config::setString(KEY_MASTER_REPL_OFFSET, strval($nowOffset));
        }
    }
}