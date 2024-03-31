<?php

namespace app\Redis\Commands;

use app\KeyValues;
use app\Redis\libs\Encoder;

class XreadCommand {
    public static function execute(array $params): array {
        $type = $params[0];
        $ret = [];
        // print_r($params);
        switch ($type) {
            case 'streams':
                $ret = self::parseKeyRangeAndReadStreams(array_slice($params, 1));
                break;
        }

        // print_r($ret);
        // print_r("\n");
        // print_r(Encoder::encodeArrayString($ret));


        return [Encoder::encodeArrayString($ret)];
    }

    private static function parseKeyRangeAndReadStreams(array $params): array {
        $ret = [];

        // param1 param2 start1 start2, jump = 2.
        $paramCounts = count($params);
        $jump = $paramCounts / 2;
        for($i = 0; $i < $paramCounts - $jump; $i++) {
            $key = $params[$i];
            $startID = $params[ $i+$jump ];

            $ret[] = self::readStreams($key, $startID);
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

        $ret = [$key];
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
            $ret[] = [ [$id, $flattenValues] ];
        }
        return $ret;
    }

    private static function makeActualID(string $id): string {
        if(strpos($id, '-') !== false)
            return $id;

        return "{$id}-0";
    }
}