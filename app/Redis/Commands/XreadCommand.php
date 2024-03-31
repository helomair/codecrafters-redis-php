<?php

namespace app\Redis\Commands;

use app\Config;
use app\JobQueue;
use app\KeyValues;
use app\Redis\libs\Encoder;

class XreadCommand {
    public static function execute(array $params): ?string {
        return  self::handleSubCommands($params[0], array_slice($params, 1));        
    }

    public static function handleSubCommands(string $type, array $params): ?string {
        $ret = "";
        switch ($type) {
            case 'streams':
                $ret = self::parseKeyRangeAndReadStreams($params);
                $ret = Encoder::encodeArrayString($ret);
                break;
            case 'block':
                $sleepTimeMs = intval($params[0]);
                JobQueue::add(
                    [self::class, 'handleSubCommands'], // callable
                    ['streams', array_slice($params, 2)],
                    Config::getSocket(KEY_NOW_RUNNING_SOCKET),
                    round(microtime(true) * 1000) + $sleepTimeMs
                );

                $ret = null;
                break;
        }

        print_r($ret);
        print_r("\n");

        return $ret;
    }

    private static function parseKeyRangeAndReadStreams(array $params): array {
        $ret = [];

        // param1 param2 start1 start2, jump = 2.
        $paramCounts = count($params);
        $jump = $paramCounts / 2;
        for($i = 0; $i < $paramCounts - $jump; $i++) {
            $key = $params[$i];
            $startID = $params[ $i+$jump ];
            $streams = self::readStreams($key, $startID);

            if (empty($streams)) continue;

            $ret[] = $streams;
        }

        return $ret;
    }

    private static function readStreams(string $key, string $startID) {
        $dataSet = KeyValues::get($key);
        $streamData = is_null($dataSet) ? null : $dataSet->getValue();
        if (empty($streamData)) {
            return [];
        }

        $startID = self::makeActualID($startID);

        [$startMs, $startSeq] = explode('-', $startID);

        $ret = [];
        foreach($streamData->getEntries() as $id => $values) {
            [$nowMs, $nowSeq] = explode('-', $id);

            if ( ($startMs > $nowMs) )
                continue;

            // Ms in range, check Seq.
            // start: a-10 vs now: a-0, start: a-10 vs now: a-10
            if ($startMs === $nowMs && $startSeq >= $nowSeq) 
                continue;
            
            $flattenValues = [];
            foreach($values as $innerKey => $value) {
                $flattenValues[] = $innerKey;
                $flattenValues[] = $value;
            }
            $ret[] = [$id, $flattenValues];
        }

        return (empty($ret)) ? [] : [$key, $ret];
    }

    private static function makeActualID(string $id): string {
        if(strpos($id, '-') !== false)
            return $id;

        return "{$id}-0";
    }
}