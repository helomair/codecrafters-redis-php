<?php

namespace app;

class JobQueue {
    private static array $jobs = [];

    public static function add(callable $job, array $jobParams, $socket, int $startAtMs = -1) {
        self::$jobs[] = [
            'job' => $job,
            'params' => $jobParams,
            'socket' => $socket,
            'startAt' => $startAtMs
        ];
    }

    public static function consumeJobs() {
        $jobs = self::$jobs;
        foreach($jobs as $index => $infos) {
            if ($infos['startAt'] > (microtime(true) * 1000))
                continue;

            $ret = call_user_func_array($infos['job'], $infos['params']);
            unset($jobs[$index]);

            if (!$ret) continue;
            socket_write($infos['socket'], $ret);
        }

        self::$jobs = $jobs;
    }


}