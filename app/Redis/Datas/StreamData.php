<?php

namespace app\Redis\Datas;


class StreamData {
    private array $entries = [];

    public function __construct(string $id, array $values) {
        $this->addEntry($id, $values);
    }

    public function addEntry(string $id, array $values): void {
        $this->entries[$id] = $values;
    }

    public function get(string $id): array {
        return $this->entries[$id];
    }
}