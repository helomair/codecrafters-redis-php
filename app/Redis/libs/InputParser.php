<?php

namespace app\Redis\libs;

class InputParser {
    private string $originInputString;
    private array $inputs;
    private array $resultParams = [];
    private const STRING_BEGIN_SYMBOLS = [ '*', '+', '$' ];

    private function __construct(string $input) {
        $this->originInputString = $input;
        $this->inputs = explode("\r\n", $this->originInputString);
    }
    public static function init(string $originInputString) {
        return (new self($originInputString));
    }

    public function parse() {
        for($i = 0; $i < count($this->inputs); $i++) {
            $input = $this->inputs[$i];

            if (empty($input)) continue;

            $symbol = $input[0];
            switch ($symbol) {
                case '*':
                    $itemCounts = intval($input[1]) * 2; // $? PARAM
                    $this->parseArray(array_slice($this->inputs, $i + 1, $itemCounts));
                    $i += $itemCounts;
                    break;

                case '+':
                    $this->parseSimpleString($input);
                    break;

                case '$':
                    $this->parseBulkString($i);
                    break;
            }
        }

        return $this->resultParams;
    }

    private function parseArray(array $items) {
        // ['$?', 'PARAM1', '$?', 'PARAM2', ...]
        $params = [];
        foreach($items as $inputStr) {
            if (preg_match("/\\$\d+/", $inputStr, $m)) continue;
            $params[] = $inputStr;
        }
        $this->resultParams[] = $params;
    }

    private function parseSimpleString(string $input) {
        $input = substr($input, 1); // Remove first '+' symbol.

        $params = [];
        foreach(explode(" ", $input) as $str) {
            $params[] = $str;
        }

        $this->resultParams[] = $params;
    }

    private function parseBulkString(int &$index) {
        // ['$?', 'STRING1'] or ['$?', 'STRING1INPUT'] if is RDB file
        $lengthCheck = intval(str_replace("$", '', $this->inputs[$index]));
        $contentLength = strlen($this->inputs[ $index + 1 ]);

        // ! Do nothing for BulkString right now.
        if ($contentLength <= $lengthCheck)
            return;

        $fileContent = $this->inputs[$index + 1];
        $newText = "";
        for($i = $contentLength - 1; $i >= 0; $i--) {
            $char = $fileContent[$i];
            $newText = $char . $newText;
            if (in_array($char, self::STRING_BEGIN_SYMBOLS))
                break;
        }
        $this->inputs[$index + 1] = $newText;

        $index --; // Rerun this input.
    }
}