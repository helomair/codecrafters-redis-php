<?php

namespace app\Helpers;

class Helpers {
    public static function makeBulkString(string $text): string {
        $length  = strlen($text);
        return "$$length\r\n$text\r\n";
    }
}