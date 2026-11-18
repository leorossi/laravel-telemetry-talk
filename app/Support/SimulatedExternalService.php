<?php

namespace App\Support;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SimulatedExternalService
{
    public function __construct(protected Chaos $chaos, protected OutboundTraceparent $outbound) {}

    public function register(): void
    {
        Http::fake([
            $this->host().'/*' => fn (Request $request): PromiseInterface => $this->respond($request),
        ]);
    }

    public function host(): string
    {
        return (string) parse_url((string) config('telemetry-demo.external.base_url'), PHP_URL_HOST);
    }

    protected function respond(Request $request): PromiseInterface
    {
        // The client instrumentation has already injected `traceparent` by the
        // time the fake handler runs, so this is the header the external service
        // would have received. /traceparent shows it.
        $this->outbound->record($request->header('traceparent'), $request->url());

        $latencyMs = (int) $this->chaos->get('external_latency_ms', 0);

        if ($latencyMs > 0) {
            usleep($latencyMs * 1000);
        }

        if ($this->chaos->get('external_failure', false)) {
            return Http::response(['error' => 'inventory service unavailable'], 503);
        }

        $sku = Str::afterLast(parse_url($request->url(), PHP_URL_PATH) ?: '', '/');

        return Http::response([
            'sku' => $sku,
            'warehouse' => 'ams-1',
            'on_hand' => crc32($sku) % 480,
            'reserved' => crc32($sku) % 60,
            'checked_at' => now()->toIso8601String(),
        ], 200, ['X-Inventory-Region' => 'eu-west']);
    }
}
