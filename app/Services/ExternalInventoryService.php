<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ExternalInventoryService
{
    public function stockFor(string $sku): array
    {
        return $this->client()
            ->get("/api/stock/{$sku}")
            ->throw()
            ->json();
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl(config('telemetry-demo.external.base_url'))
            ->timeout((int) config('telemetry-demo.external.timeout'))
            ->retry((int) config('telemetry-demo.external.retries'), 200, throw: true);
    }
}
