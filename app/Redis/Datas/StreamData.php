<?php

namespace app\Redis\Datas;


class StreamData {
    private int $lastEntryId_MS = 0;
    private int $lastEntryId_SeqNumber = 0;

    private array $entries = [];

    public function __construct(string $id, array $values) {
        $this->addEntry($id, $values);
    }

    public function addEntry(string $id, array $values): string {
        $errMsg = "ERR The ID specified in XADD is equal or smaller than the target stream top item";

        if ($id === "0-0") {
            return "ERR The ID specified in XADD must be greater than 0-0";
        }

        $ms_seq = explode("-", $id);

        // ! Need check ms_seq len is 2.
        // ! Race Condition.

        $newMs = intval($ms_seq[0]);
        $newSeq = intval($ms_seq[1]);
        if ( 
            ($newMs > $this->lastEntryId_MS) ||
            ($newMs === $this->lastEntryId_MS && $newSeq > $this->lastEntryId_SeqNumber) 
        ) {
            $errMsg = "";
        } 

        if (empty($errMsg)) {
            $this->entries[$id] = $values;
            $this->lastEntryId_MS = $newMs;
            $this->lastEntryId_SeqNumber = $newSeq;
        }

        return $errMsg;
    }

    public function get(string $id): array {
        return $this->entries[$id];
    }
}