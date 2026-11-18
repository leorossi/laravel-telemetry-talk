<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\PDO as PdoStorage;

/**
 * The one Prometheus registry this process uses.
 *
 * The Prometheus PHP client keeps its counters in shared storage rather than in
 * the process, because a PHP process only lives for one request. Here that
 * storage is the Postgres connection the app already has: the client creates
 * its own `prometheus_*` tables next to the application tables.
 *
 * Every metric family in the demo goes through this class, so /metrics renders
 * one consistent snapshot and the reset button can wipe everything in one go.
 */
class MetricsRegistry
{
    protected ?CollectorRegistry $registry = null;

    public function registry(): CollectorRegistry
    {
        return $this->registry ??= new CollectorRegistry(new PdoStorage(
            DB::connection()->getPdo()
        ));
    }

    /**
     * Forget every counter, gauge and histogram recorded so far.
     */
    public function wipe(): void
    {
        $this->registry()->wipeStorage();
    }
}
