<?php

namespace app\Redis\Commands;

use app\Config;
use app\Redis\libs\Encoder;

class PsyncCommand implements CommandInterface {
    public static function execute(array $params = []): string {
        $replid = Config::getString(KEY_MASTER_REPLID);
        $offset = Config::getString(KEY_MASTER_REPL_OFFSET);

        $fullSync = Encoder::encodeSimpleString("FULLRESYNC {$replid} {$offset}");

        $fileContent = "UkVESVMwMDEx+glyZWRpcy12ZXIFNy4yLjD6CnJlZGlzLWJpdHPAQPoFY3RpbWXCbQi8ZfoIdXNlZC1tZW3CsMQQAPoIYW9mLWJhc2XAAP/wbjv+wP9aog==";
        $fullSyncFile = Encoder::encodeFileString(base64_decode($fileContent));

        return $fullSync . $fullSyncFile;
    }
}