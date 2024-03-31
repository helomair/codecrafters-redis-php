<?php

namespace app\Redis\Datas;


class StreamData {
    // private int $lastEntryId_MS = 0;
    // private int $lastEntryId_SeqNumber = 0;

    private array $entries = [];

    public function addEntry(string $id, array $values): array {
        $errMsg = "ERR The ID specified in XADD is equal or smaller than the target stream top item";

        if ($id === "0-0") {
            return [ $id, "ERR The ID specified in XADD must be greater than 0-0" ];
        }

        // $ms_seq = explode("-", $id);
        // ! Race Condition.
        if ( $id = $this->validateID($id) ) {
            $errMsg = "";
            $this->entries[$id] = $values;
        } 

        return [ $id, $errMsg ];
    }

    public function get(string $id): array {
        return $this->entries[$id];
    }

    private function validateID(string $id): string{

        $latestID = array_key_last($this->entries) ?? "0-0";

        // ! Need check ms_seq len is 2
        [$newMs, $newSeq] = explode("-", $id);
        [$latestMs, $latestSeq] = explode("-", $latestID);

        $latestMs = intval($latestMs);
        $latestSeq = intval($latestSeq);

        $newMs = intval($newMs);

        if ($newSeq === "*") {
            if ($newMs === $latestMs)
                $newSeq = $latestSeq + 1;
            else 
                $newSeq = 0;
        } else {
            $newSeq = intval($newSeq);
        }

        if ( ($newMs > $latestMs) || ($newMs === $latestMs && $newSeq > $latestSeq) )
            $id = "{$newMs}-{$newSeq}";
        else 
            $id = "";

        return $id;
    }
}