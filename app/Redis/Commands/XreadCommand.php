<?php

namespace app\Redis\Commands;

use app\Config;
use app\JobQueue;
use app\KeyValues;
use app\Redis\Datas\StreamData;
use app\Redis\libs\Encoder;

class XreadCommand {
    public static function execute(array $params): ?string {
        return  self::handleSubCommands($params[0], array_slice($params, 1));        
    }

    public static function handleSubCommands(string $type, array $params): ?string {
        $ret = "";
        switch ($type) {
            case 'block':
                $entryCount = (intval($params[0]) === 0) ? self::countEntries($params[2]) : -1;
                $ret = self::handleBlock($params, Config::getSocket(KEY_NOW_RUNNING_SOCKET), $entryCount);
                break;
            case 'streams':
                $ret = self::parseKeyRangeAndReadStreams($params);
                $ret = Encoder::encodeArrayString($ret);
                break;
        }

        return $ret;
    }

    public static function handleBlock(array $params, $socket, int $lastStreamEntryCount = -1) {
        $sleepTimeMs = intval($params[0]);
        $startAtMs = ($sleepTimeMs > 0) ? (round(microtime(true) * 1000) + $sleepTimeMs) : -1;

        $entryCount = self::countEntries($params[2]); 

        if (($sleepTimeMs === 0) && ($lastStreamEntryCount > (-1)) && ($lastStreamEntryCount === $entryCount)) {
            JobQueue::add(
                [self::class, 'handleBlock'], 
                [$params, $socket, $lastStreamEntryCount],
                $socket, 
                $startAtMs
            );
        }
        else {
            JobQueue::add(
                [self::class, 'handleSubCommands'], 
                ['streams', array_slice($params, 2)],
                $socket, 
                $startAtMs
            );
        }

        return null;
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

            print_r("Streams: ");
            print_r($streams);

            if (empty($streams)) continue;

            $ret[] = $streams;
        }

        return $ret;
    }

    private static function readStreams(string $key, string $startID): array {
        $streamData = KeyValues::getStreamData($key);
        if (empty($streamData)) {
            return [];
        }

        $ret = [];
        if ($startID !== '$') {
            $ret = self::getAvailableEntries($streamData, $startID);
        } else {
            $entries = $streamData->getEntries();
            $id = array_key_last($entries);
            $lastEntry = $entries[$id]; 
            $ret[] = [$id, self::arrayFlatten($lastEntry)];    
        }

        return (empty($ret)) ? [] : [$key, $ret];
    }

    private static function getAvailableEntries(StreamData $streamData, string $startID): array {
        $startID = self::makeActualID($startID);

        [$startMs, $startSeq] = explode('-', $startID);

        $ret = [];
        foreach($streamData->getEntries() as $id => $values) {
            [$nowMs, $nowSeq] = explode('-', $id);

            if ( ($startMs > $nowMs) )
                continue;

            // ? Ms in range, check Seq.
            // ? start: a-10 vs now: a-0, start: a-10 vs now: a-10
            if ($startMs === $nowMs && $startSeq >= $nowSeq) 
                continue;

            $ret[] = [$id, self::arrayFlatten($values)];
        }

        return $ret;
    }

    private static function arrayFlatten(array $values): array {
        $flattenValues = [];
        foreach($values as $innerKey => $value) {
            $flattenValues[] = $innerKey;
            $flattenValues[] = $value;
        }

        return $flattenValues;
    }

    private static function makeActualID(string $id): string {
        if(strpos($id, '-') !== false)
            return $id;

        return "{$id}-0";
    }

    private static function countEntries(string $key): int {
        $streamData = KeyValues::getStreamData($key);
        return $streamData->getEntryCounts();
    }
}