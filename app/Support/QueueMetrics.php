<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Prometheus metrics for the queue, read from the same tables Laravel uses.
 *
 * The demo runs the database queue driver, not Horizon. These gauges are the
 * smallest honest answer to "what is the queue doing?": how much work is
 * waiting, what has failed, and how old the oldest waiting job is.
 */
class QueueMetrics
{
    public const NAMESPACE = 'demo';

    public function __construct(protected MetricsRegistry $metrics) {}

    public function expose(): void
    {
        $registry = $this->metrics->registry();

        $depth = DB::table('jobs')
            ->select('queue', DB::raw('count(*) as aggregate'))
            ->groupBy('queue')
            ->pluck('aggregate', 'queue');

        $failed = DB::table('failed_jobs')
            ->select('queue', DB::raw('count(*) as aggregate'))
            ->groupBy('queue')
            ->pluck('aggregate', 'queue');

        $oldest = DB::table('jobs')
            ->select('queue', DB::raw('min(available_at) as oldest'))
            ->groupBy('queue')
            ->pluck('oldest', 'queue');

        // Keep the default series visible even when the queue is empty: a gauge
        // that only appears after the first job is harder to read on a
        // dashboard, and `demo_queue_depth{queue="default"} 0` is a true fact.
        $queues = $depth->keys()
            ->merge($failed->keys())
            ->merge($oldest->keys())
            ->unique()
            ->values();

        if ($queues->isEmpty()) {
            $queues = collect(['default']);
        }

        $depthGauge = $registry->getOrRegisterGauge(
            self::NAMESPACE,
            'queue_depth',
            'Jobs waiting in each queue.',
            ['queue'],
        );

        $failedGauge = $registry->getOrRegisterGauge(
            self::NAMESPACE,
            'failed_jobs',
            'Failed jobs per queue.',
            ['queue'],
        );

        $oldestGauge = $registry->getOrRegisterGauge(
            self::NAMESPACE,
            'queue_oldest_job_age_seconds',
            'Age of the oldest waiting job, per queue.',
            ['queue'],
        );

        foreach ($queues as $queue) {
            $depthGauge->set((int) ($depth[$queue] ?? 0), [$queue]);
            $failedGauge->set((int) ($failed[$queue] ?? 0), [$queue]);

            $availableAt = $oldest[$queue] ?? null;
            $oldestGauge->set(
                $availableAt === null ? 0 : max(0, now()->timestamp - (int) $availableAt),
                [$queue],
            );
        }
    }
}
