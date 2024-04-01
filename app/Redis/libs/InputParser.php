<?php

namespace app\Redis\libs;

class InputParser {
    private int    $ptr = 0;
    private string $originInputString;
    private array  $inputs;
    private array  $resultParams = [];
    private const  STRING_BEGIN_SYMBOLS = [ '*', '+', '$' ];

    private function __construct(string $input) {
        $this->originInputString = $input;
        $this->inputs = explode("\r\n", $this->originInputString);
    }
    public static function init(string $originInputString) {
        return (new self($originInputString));
    }

    public function parse() {
        while($this->ptr < count($this->inputs)) {
            $inputStr = $this->getNowStr();

            if (empty($inputStr)) {
                $this->ptr++;
                continue;
            }

            $symbol = $inputStr[0];
            switch ($symbol) {
                case '*':
                    $this->parseArray();
                    break;

                case '+':
                    $this->parseSimpleString();
                    break;

                case '$':
                    $this->parseBulkString();
                    break;
            }

            $this->ptr++;
        }

        return $this->resultParams;
    }

    private function getNowStr(): string {
        return $this->inputs[$this->ptr];
    }

    private function parseArray() {
        //? parse *3, an item is "$5\r\nPARAM\r\n"
        $counts = intval(substr($this->getNowStr(), 1)) * 2;
        $items = array_slice($this->inputs, $this->ptr + 1, $counts);

        $params = [];
        foreach($items as $inputStr) {
            if (preg_match("/\\$\d+/", $inputStr, $m)) continue;
            $params[] = $inputStr;
        }
        $this->resultParams[] = $params;

        $this->ptr += $counts;
    }

    private function parseSimpleString() {
        $input = $this->getNowStr();
        $params = [];
        foreach(explode(" ", substr($input, 1)) as $str) { // Remove first '+' symbol.
            $params[] = $str;
        }

        $this->resultParams[] = $params;
    }

    private function parseBulkString() {
        if ($this->isRegularBulkString()) // ! Do nothing for BulkString right now.
            return;

        $this->tryRemoveRDBFileContentAndRerunInput();
    }

    private function isRegularBulkString() {
        $input = $this->getNowStr();
        $lengthCheck = intval(str_replace("$", '', $input));

        $this->ptr++;
        $nextInput = $this->getNowStr();
        $stringLength = strlen($nextInput);
        $this->ptr--;

        return ($lengthCheck >= $stringLength);
    }

    private function tryRemoveRDBFileContentAndRerunInput() {
        // ? Since RDBFile content is bulk string but not end with \r\n,
        // ?   there might be $85\r\n{RDBFile_content...}*3\r\n...
        // ?   next command is spliced at the end.
        $this->ptr++;

        $fileContent = $this->getNowStr();
        $length = strlen($fileContent);
        $newText = "";
        for($i = $length - 1; $i >= 0; $i--) {
            $char = $fileContent[$i];
            $newText = $char . $newText;

            if (in_array($char, self::STRING_BEGIN_SYMBOLS))
                break;
        }

        if (!empty($newText)) {
            $this->inputs[ $this->ptr ] = $newText;
            $this->ptr --; // Rerun this input.
        }
    }
}