<?php

namespace app\Redis\Datas;


class StreamData {
    private int $lastEntryId_MS = 0;
    private int $lastEntryId_SeqNumber = 0;

    private array $entries = [];

    public function addEntry(string $id, array $values): array {
        $errMsg = "ERR The ID specified in XADD is equal or smaller than the target stream top item";

        if ($id === "0-0") {
            return [ $id, "ERR The ID specified in XADD must be greater than 0-0" ];
        }

        [$newMs, $newSeq] = $this->getNewMsAndSeq($id);

        // ! Race Condition.

        // $newMs = intval($newMs);

        // if ($newSeq === "*") {
        //     if ($newMs === $this->lastEntryId_MS)
        //         $newSeq = $this->lastEntryId_SeqNumber + 1;
        //     else 
        //         $newSeq = 0;
        // } else {
        //     $newSeq = intval($newSeq);
        // }

        if ( 
            ($newMs > $this->lastEntryId_MS) ||
            ($newMs === $this->lastEntryId_MS && $newSeq > $this->lastEntryId_SeqNumber) 
        ) {
            $errMsg = "";
            $id = "{$newMs}-{$newSeq}";

            $this->entries[$id] = $values;
            $this->lastEntryId_MS = $newMs;
            $this->lastEntryId_SeqNumber = $newSeq;
        } 

        return [$id, $errMsg];
    }

    public function get(string $id): array {
        return $this->entries[$id];
    }

    private function getNewMsAndSeq(string $id): array {
        $ms_seq = explode("-", $id);

        // ! Race Condition.

        // print_r(microtime(true));
        $newMs = ($ms_seq[0] === "*") ? round(microtime(true) * 1000) : intval($ms_seq[0]);

        $newSeq = $ms_seq[1] ?? "*";
        if ($newSeq === "*") {
            if ($newMs === $this->lastEntryId_MS)
                $newSeq = $this->lastEntryId_SeqNumber + 1;
            else 
                $newSeq = 0;
        } else {
            $newSeq = intval($newSeq);
        }

        return [$newMs, $newSeq];
    }
}