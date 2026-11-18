<?php

namespace App\Http\Middleware;

use App\Support\HttpMetrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Record one Prometheus sample per HTTP request.
 *
 * The timing stops before the metric itself is written, so the observation does
 * not include the cost of recording it. A telemetry failure is reported, never
 * allowed to turn a working response into a 500.
 */
class RecordHttpMetrics
{
    public function __construct(protected HttpMetrics $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->record($request, 500, $startedAt);

            throw $exception;
        }

        $this->record($request, $response->getStatusCode(), $startedAt);

        return $response;
    }

    protected function record(Request $request, int $status, float $startedAt): void
    {
        try {
            $this->metrics->record($request, $status, microtime(true) - $startedAt);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
