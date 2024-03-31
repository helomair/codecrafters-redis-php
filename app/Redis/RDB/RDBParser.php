<?php

namespace app\Redis\RDB;

use app\Config;
use app\KeyValues;

class RDBParser {
    private const EOF = 'ff';
    private const SELECT_DB = 'fe';
    private const EXPIRE_TIME = 'fd';
    private const EXPIRE_TIME_MS = 'fc';
    private const RESIZE_DB = 'fb';
    private const AUX = 'fa';
    private const OpCodes = [
        self::EOF,
        self::SELECT_DB,
        self::EXPIRE_TIME,
        self::EXPIRE_TIME_MS,
        self::RESIZE_DB,
        self::AUX,
    ];


    private int   $ptr = 0;
    private int   $dbNumber = 0;
    private string $filePath;

    private array $dbContents = [];

    private function __construct(string $filePath) {
        $this->filePath = $filePath;
    }

    public static function parse() {
        $path = Config::getString(KEY_DIR_PATH);
        $filename = Config::getString(KEY_DB_FILENAME);

        if (empty($path) && empty($filename))
            return;

        (new self("{$path}/{$filename}"))->execute();
    }

    private function execute() {
        if (!file_exists($this->filePath))
            return;

        $fileContent = file_get_contents($this->filePath);
        $fileContentHex = bin2hex($fileContent);

        // 1 byte.
        $this->dbContents = str_split($fileContentHex, 2);


        while( ($this->ptr < count($this->dbContents)) && ($this->getNowHexString() !== self::EOF) ) {
            $this->echoNowContent();

            switch ($this->getNowHexString()) {
                case self::SELECT_DB:
                    $this->selectDB();
                    $this->ptrNext();
                    $this->parseKeyValuePairSections();
                    break;
            }

            if ($this->getNowHexString() === self::EOF) {
                echo "EOF!\n";
                break;
            }

            $this->ptr++;
        }
    }

    private function selectDB() {
        echo "\n    Start Select DB\n\n";
        $this->ptrNext();
        $this->dbNumber = $this->parseLengthEncoding();
        echo "\n    End Select DB\n\n";
    }

    private function parseKeyValuePairSections() {
        echo "\n    Start parse DB key-vals, DB: {$this->dbNumber}\n\n";

        $this->parseResizeDB();
        $this->ptrNext();

        $this->parseKeyValuePair();

        echo "\n    End parse DB key-vals\n\n";
    }

    private function parseResizeDB() {
        if ($this->getNowHexString() !== self::RESIZE_DB)
            return;

        $this->ptrNext();

        echo "\n    Start resize DB\n\n";
        $dbHashTableSize = $this->parseLengthEncoding();
        $this->ptrNext();

        $dbExpirlyHashTableSize = $this->parseLengthEncoding();

        echo "hash table size: {$dbHashTableSize}\n";
        echo "expirly hash table size: {$dbExpirlyHashTableSize}\n";

        echo "\n    End resize DB\n\n";
    }

    private function parseKeyValuePair() {
        echo "\n    Start parse Key Value Pair\n\n";

        $this->echoNowContent();

        while( ($nowHex = $this->getNowHexString()) && !in_array($nowHex, self::OpCodes) ) {
            $nowHexString = $this->getNowHexString();
            $nowDec = hexdec($nowHexString);

            echo "Hex: {$nowHexString}, Dec: {$nowDec}\n\n";

            switch ($nowDec) {
                case ValueType::STRING:
                    $key = $this->parseStringEncoding();
                    $value = $this->parseStringEncoding();

                    echo "!!!  Key: {$key}, Value: {$value}  !!!\n";

                    KeyValues::setToSelectedDB($this->dbNumber, $key, $value);
                    break;
            }

            $this->ptrNext();
        }

        echo "\n    End parse Key Value Pair\n\n";
    }

    private function parseStringEncoding(): string {
        echo "\n    Start parse String Encoding\n\n";
        $this->ptrNext();
        $length = $this->parseLengthEncoding();

        echo "parse String length: {$length}\n";

        $this->ptrNext();
        $contents = array_slice($this->dbContents, $this->ptr, $length);
        $str = "";
        foreach($contents as $hex) {
            $str .= chr(hexdec($hex));
        }
        echo "parse String content: {$str}\n";

        // skip the string contents.
        for($i = 0; $i < $length - 1; $i++) {
            $this->ptrNext();
        }

        echo "\n    End parse String Encoding\n\n";

        return $str;
    }

    private function parseLengthEncoding(): int {
        echo "\n    Start parse Length Encoding\n\n";

        $this->echoNowContent();

        $bits = $this->hexToBits( $this->getNowHexString() );
        $firstTowBits = substr($bits, 0, 2);
        $length = 0;

        if ($firstTowBits === '00') {
            $lengthDec = base_convert(substr($bits, 2, 6), 2, 10);
            $length = intval($lengthDec);
        }
        echo "\n    End parse Length Encoding\n\n";

        return $length;
    }

    private function hexToBits(string $hexString) {
        $bits = base_convert($hexString, 16, 2);
        return str_pad($bits, 8, "0", STR_PAD_LEFT);
    }

    private function ptrNext() {
        $this->ptr++;

        if ($this->getNowHexString() === self::EOF) {
            return;
        }

        $this->echoNowContent();
    }

    private function echoNowContent(): void {
        echo "Now ptr: {$this->ptr}, content: {$this->getNowHexString()}\n";
    }

    private function getNowHexString(): ?string {
        return $this->dbContents[$this->ptr] ?? null;
    }
}

// ! Should use ENUM, but tester using PHP7.4 so can't do it here.
class ValueType {
    public const STRING = 0;
    public const LIST = 1;
    public const SET = 2;
    public const SORTED_SET = 3;
    public const HASH = 4;
    public const ZIPMAP = 9;
    public const ZIPLIST = 10;
    public const INTSET = 11;
    public const SORTED_SET_IN_ZIPLIST = 12;
    public const HASHMAP_IN_ZIPLIST = 13;
    public const LIST_IN_QUICKLIST = 14;
}