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

        $this->slaveHandshake();
    }

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


    private function slaveHandshake() {
        if (Config::get('role') !== 'slave')
            return;

        $masterHost = Config::get('master_host');
        $masterPort = intval(Config::get('master_port'));

        if (empty($masterHost) || empty($masterPort)) {
            echo "Role is slave but master host or port not provided" . PHP_EOL;
            return;
        }

        // PING to master
        if (($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
            echo "socket_create() failed in slaveHandShake : " . socket_strerror(socket_last_error()) . PHP_EOL;
            exit(1);
        }

        if (!socket_connect($socket, $masterHost, $masterPort)) {
            echo "socket_connect() failed in slaveHandShake : " . socket_strerror(socket_last_error()) . PHP_EOL;
        } else {
            # Step1: ping
            $message = Encoder::encodeArrayString(['ping']);
            socket_write($socket, $message);
            $firstStepResponse = socket_read($socket, 1024);

            # Step2: REPLCONF listening-port <PORT>
            $message = Encoder::encodeArrayString(['REPLCONF', 'listening-port', Config::get('port')]);
            socket_write($socket, $message);
            $secondStepResponse = socket_read($socket, 1024);

            # Step3: REPLCONF capa psync2
            $message = Encoder::encodeArrayString(['REPLCONF', 'capa', 'psync2']);
            socket_write($socket, $message);
            $thridStepResponse = socket_read($socket, 1024);

            # Step4: First connection, PSYNC ? -1, (PSYNC <replication ID> <offset>)
            $message = Encoder::encodeArrayString(['PSYNC', '?', '-1']);
            socket_write($socket, $message);
            $forthStepResponse = socket_read($socket, 1024);

            socket_close($socket);
        }
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
}
