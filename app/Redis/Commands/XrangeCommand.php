<?php

namespace app\Redis\Commands;

use app\KeyValues;
use app\Redis\libs\Encoder;

class XrangeCommand {
    public static function execute(array $params): array {
        print_r($params);
        $key = $params[0];
        $dataSet = KeyValues::get($key);
        $streamData = is_null($dataSet) ? null : $dataSet->getValue();
        if (empty($streamData)) {
            return [];
        }

        $ret    = [];
        $startID = self::makeActualID($params[1]);
        $endID   = self::makeActualID($params[2]);

        [$startMs, $startSeq] = explode('-', $startID);
        [$endMs, $endSeq]     = explode('-', $endID);


        foreach($streamData->getEntries() as $id => $values) {
            [$nowMs, $nowSeq] = explode('-', $id);

            if ($startMs > $nowMs || $endMs < $nowMs) continue;

            // Ms in range, check Seq.
            // start: a-10 vs now: a-0
            if ($startMs === $nowMs && $startSeq > $nowSeq) continue;
            // end: a-0 vs now: a-10
            if ($endMs === $nowMs && $endSeq < $nowSeq) continue;
            
            $flattenValues = [];
            foreach($values as $innerKey => $value) {
                $flattenValues[] = $innerKey;
                $flattenValues[] = $value;
            }
            $ret[] = [ $id, $flattenValues ];
        }

        return [Encoder::encodeArrayString($ret)];
    }

    private static function makeActualID(string $id): string {
        if ($id === '-')
            return '0-0';
        
        if(strpos($id, '-') !== false)
            return $id;

        return "{$id}-0";
    }
}