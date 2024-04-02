<?php

namespace app;

class Helpers {
    public static function getSocketID($socket): int {
        if (version_compare(PHP_VERSION, '8.0.0', '>=') )
            return spl_object_id($socket);
        else 
            return (int) $socket;
    }
}