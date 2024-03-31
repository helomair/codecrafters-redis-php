<?php

namespace app\Redis;

use app\Config;
use app\Redis\libs\Encoder;
use app\Redis\libs\InputParser;

use app\Redis\Commands\GetCommand;

use app\Redis\Commands\SetCommand;
use app\Redis\Commands\EchoCommand;
use app\Redis\Commands\InfoCommand;
use app\Redis\Commands\KeysCommand;
use app\Redis\Commands\PingCommand;
use app\Redis\Commands\TypeCommand;
use app\Redis\Commands\WaitCommand;
use app\Redis\Commands\XaddCommand;
use app\Redis\Commands\PsyncCommand;
use app\Redis\Commands\XreadCommand;
use app\Redis\Commands\ConfigCommand;
use app\Redis\Commands\XrangeCommand;
use app\Redis\master\MasterPropagate;
use app\Redis\Commands\ReplconfCommand;

class Redis {
    private array  $params = [];
    private string $command = "";
    // private $requestedSocket;

    private const COMMANDS_NO_OFFSET = ['FULLRESYNC'];

    public function handle(string $inputStr): ?array {
        $parsedInputs = InputParser::init($inputStr)->parse();

        $responses = [];
        foreach($parsedInputs as $inputs) {
            if (empty($inputs)) continue;

            $this->command = strtoupper($inputs[0]);
            $this->params = array_slice($inputs, 1);

            // print_r($this->command . " =>  ");
            // print_r($this->params);
            // print_r("\n");

            if (!is_null( $response = $this->commandExecution() ))
                $responses[] = $response;
    
            MasterPropagate::sendParamsToSlave($this->command, $this->params);
            $this->addCommandOffset();
        }

        return $responses;
    }

    private function commandExecution(): ?array {
        $ret = [];
        switch ($this->command) {
            case "PING":
                $ret = PingCommand::execute();
                break;
            case "ECHO":
                $ret = EchoCommand::execute($this->params);
                break;
            case "SET":
                $ret = SetCommand::execute($this->params);
                break;
            case "GET":
                $ret = GetCommand::execute($this->params);
                break;
            case "INFO":
                $ret = InfoCommand::execute();
                break;
            case "REPLCONF":
                $ret = ReplconfCommand::execute($this->params);
                break;
            case "PSYNC":
                $ret = PsyncCommand::execute(); // array
                break;
            case "WAIT":
                $ret = WaitCommand::execute($this->params);
                break;
            case "CONFIG":
                $ret = ConfigCommand::execute($this->params);
                break;
            case "KEYS":
                $ret = KeysCommand::execute($this->params);
                break;
            case "TYPE":
                $ret = TypeCommand::execute($this->params);
                break;
            case "XADD":
                $ret = XaddCommand::execute($this->params);
                break;
            case "XRANGE":
                $ret = XrangeCommand::execute($this->params);
                break;
            case "XREAD":
                $ret = XreadCommand::execute($this->params);
                break;
        }

        if (is_null($ret)) return null;
        
        return is_array($ret) ? $ret : [$ret];
    }

    private function addCommandOffset(): void {
        if (in_array($this->command, self::COMMANDS_NO_OFFSET))
            return;

        $originInputStr = Encoder::encodeArrayString([$this->command, ...$this->params]);

        $socket = Config::getSocket(KEY_NOW_RUNNING_SOCKET);
        $slaveToMasterSocket = Config::getSocket(KEY_MASTER_SOCKET);

        if ( !is_null($slaveToMasterSocket) && ($socket === $slaveToMasterSocket) ) {
            $bytes = strlen($originInputStr);
            $nowOffset = Config::getInt(KEY_MASTER_REPL_OFFSET);
            $nowOffset += $bytes;
            Config::setString(KEY_MASTER_REPL_OFFSET, strval($nowOffset));
        }
    }
}