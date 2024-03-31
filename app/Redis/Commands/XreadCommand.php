<?php

namespace app\Redis\Commands;

use app\KeyValues;
use app\Redis\libs\Encoder;

class XreadCommand {
    public static function execute(array $params): array {
        $type = $params[0];
        $ret = [];
        print_r($params);
        switch ($type) {
            case 'streams':
                $ret = self::readStreams($params);
                break;
        }

        return $ret;
    }

    private static function readStreams(array $params) {
        $key = $params[1];
        $dataSet = KeyValues::get($key);
        $streamData = is_null($dataSet) ? null : $dataSet->getValue();
        if (empty($streamData)) {
            return [];
        }

        $startID = self::makeActualID($params[2]);

        [$startMs, $startSeq] = explode('-', $startID);

        $ret = [];
        $thisKeyStreams = [$key];
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
            $thisKeyStreams[] = [ [$id, $flattenValues] ];
        }

        $ret[] = $thisKeyStreams;

        print_r($ret);
        print_r("\n");
        print_r(Encoder::encodeArrayString($ret));

        return [Encoder::encodeArrayString($ret)];
    }

    private static function makeActualID(string $id): string {
        if(strpos($id, '-') !== false)
            return $id;

        return "{$id}-0";
    }
}