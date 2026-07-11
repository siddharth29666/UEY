<?php

namespace App\Services;

use App\Models\SchedulerLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SchedulerService
{
    /**
     * Run a scheduled command with full auditing, logging, and timing metrics.
     *
     * @param  string  $command  The name of the console command.
     * @param  callable  $callback  The actual task logic.
     * @return mixed
     *
     * @throws \Throwable
     */
    public function runCommand(string $command, callable $callback)
    {
        $startedAt = Carbon::now();

        // 1. Create a log entry for the started command
        $logEntry = SchedulerLog::create([
            'command' => $command,
            'status' => 'running',
            'started_at' => $startedAt,
        ]);

        try {
            // 2. Execute the command callback
            $result = $callback();

            $finishedAt = Carbon::now();
            $executionTime = $startedAt->diffInMicroseconds($finishedAt) / 1000000.0;

            // 3. Mark success
            $logEntry->update([
                'status' => 'success',
                'finished_at' => $finishedAt,
                'execution_time' => $executionTime,
                'message' => is_string($result) ? $result : 'Command executed successfully.',
            ]);

            return $result;
        } catch (\Throwable $e) {
            $finishedAt = Carbon::now();
            $executionTime = $startedAt->diffInMicroseconds($finishedAt) / 1000000.0;

            // 4. Mark failure
            $logEntry->update([
                'status' => 'failed',
                'finished_at' => $finishedAt,
                'execution_time' => $executionTime,
                'message' => $e->getMessage()."\n".$e->getTraceAsString(),
            ]);

            Log::error("Scheduled command {$command} failed: ".$e->getMessage());

            throw $e;
        }
    }
}
