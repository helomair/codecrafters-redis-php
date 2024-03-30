<?php

namespace app\Redis\libs;

class Helper {
    public static function generateRandomString(int $length = 40): string {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomString = '';
     
        for ($i = 0; $i < $length; $i++) {
            $index = rand(0, strlen($characters) - 1);
            $randomString .= $characters[$index];
        }
     
        return $randomString;
    }

    public static function isRDBFileContent(string $content): bool {
        $length = strlen($content);

        if ($length < 2) return false;

        $isEndWithNewLine = $content[ $length-1 ] === "\n";
        $isEndWithLineFeed = $content[ $length-2 ] === "\r";

        return !( $isEndWithNewLine && $isEndWithLineFeed );
    }
}