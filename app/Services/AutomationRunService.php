<?php

namespace App\Services;

use App\Models\AutomationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AutomationRunService
{
    public function execute(string $job, string $periodKey, array $parameters, bool $force, callable $callback): array
    {
        $lock = Cache::lock('automation:'.hash('sha256', $job.'|'.$periodKey), 7200);
        if (! $lock->get()) {
            throw new \RuntimeException("{$job} is already running for {$periodKey}.");
        }

        try {
            return $this->executeLocked($job, $periodKey, $parameters, $force, $callback);
        } finally {
            $lock->release();
        }
    }

    private function executeLocked(string $job, string $periodKey, array $parameters, bool $force, callable $callback): array
    {
        $run = DB::transaction(function () use ($job, $periodKey, $parameters, $force) {
            $run = AutomationRun::query()->where(['job' => $job, 'period_key' => $periodKey])->lockForUpdate()->first();
            if ($run?->status === 'completed' && ! $force) return null;
            if ($run?->status === 'running' && $run->started_at?->gt(now()->subHours(2))) {
                throw new \RuntimeException("{$job} is already running for {$periodKey}.");
            }
            if (! $run) $run = new AutomationRun(['job' => $job, 'period_key' => $periodKey]);
            $run->fill([
                'status' => 'running', 'started_at' => now('UTC'), 'finished_at' => null,
                'attempt' => $run->exists ? $run->attempt + 1 : 1, 'parameters' => $parameters,
                'result' => null, 'error_message' => null, 'error_trace' => null,
            ])->save();
            return $run;
        });
        if (! $run) return ['skipped' => true, 'reason' => 'already_completed'];

        try {
            $result = $callback();
            $run->update(['status' => 'completed', 'finished_at' => now('UTC'), 'result' => $result]);
            return [...$result, 'run_id' => $run->id, 'skipped' => false];
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed', 'finished_at' => now('UTC'), 'error_message' => $exception->getMessage(),
                'error_trace' => mb_substr($exception->getTraceAsString(), 0, 10000),
            ]);
            throw $exception;
        }
    }
}
