<?php

namespace app\Redis\libs;

class Encoder {
    /**
     * @param array $texts, array of strings, key-value
     */
    public static function encodeMultipleBulkStrings(array $texts): string {
        $ret = "";
        foreach($texts as $key => $text) {
            $ret .= "$key:$text\r\n";
        }

        return self::encodeBulkString($ret);
    }

    public static function encodeBulkString(string $text): string {
        $length  = strlen($text);
        return "$" . "$length\r\n" . "$text\r\n";
    }

    public static function encodeSimpleString(string $text): string {
        return "+{$text}\r\n";
    }

    public static function nullString(): string {
        return "$-1\r\n";
    }
}