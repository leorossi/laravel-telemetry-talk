<?php

namespace App\Http\Controllers;

use App\Support\OutboundTraceparent;
use Illuminate\Contracts\View\View;
use Keepsuit\LaravelOpenTelemetry\Facades\Tracer;

class TraceparentController extends Controller
{
    public function __invoke(OutboundTraceparent $outbound): View
    {
        $last = $outbound->last();

        return view('demo.traceparent', [
            'traceparent' => $last['traceparent'] ?? null,
            'outboundUrl' => $last['url'] ?? null,
            'recordedAt' => $last['recorded_at'] ?? null,
            'currentTraceId' => $this->currentTraceId(),
            'jaegerUrl' => 'http://localhost:16686',
        ]);
    }

    /**
     * The trace id of this page's own request, if tracing is on.
     */
    protected function currentTraceId(): ?string
    {
        try {
            return Tracer::traceId();
        } catch (\Throwable) {
            return null;
        }
    }
}
