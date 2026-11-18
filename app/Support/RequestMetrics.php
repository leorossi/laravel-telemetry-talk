<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class RequestMetrics
{
    protected int $queries = 0;

    protected float $startedAt = 0.0;

    protected bool $listening = false;

    public function start(): void
    {
        if (! $this->listening) {
            DB::listen(function (): void {
                $this->queries++;
            });

            $this->listening = true;
        }

        $this->queries = 0;
        $this->startedAt = microtime(true);
    }

    public function queries(): int
    {
        return $this->queries;
    }

    public function durationMs(): float
    {
        return round((microtime(true) - $this->startedAt) * 1000, 1);
    }
}
