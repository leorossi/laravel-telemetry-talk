<?php

namespace App\Jobs;

use App\Services\ExternalInventoryService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Sleep;

class SyncInventoryJob implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public string $sku) {}

    public function handle(ExternalInventoryService $inventory): void
    {
        Sleep::for(300)->milliseconds();

        Cache::put("demo:inventory:{$this->sku}", $inventory->stockFor($this->sku), now()->addMinutes(10));
    }
}
