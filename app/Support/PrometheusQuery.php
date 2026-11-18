<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * A very small read-only client for the Prometheus HTTP API.
 *
 * The /orders page uses it to show the windowed numbers without sending the
 * presenter to another tab. Prometheus answers with a single scalar per query.
 */
class PrometheusQuery
{
    public function __construct(protected string $baseUrl) {}

    /**
     * Run an instant query and return the first scalar value, or null when
     * Prometheus is unreachable or has no data yet.
     */
    public function scalar(string $query): ?float
    {
        try {
            $response = Http::timeout(2)->get(rtrim($this->baseUrl, '/').'/api/v1/query', [
                'query' => $query,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $result = $response->json('data.result');

            if (! is_array($result) || $result === []) {
                return null;
            }

            $value = $result[0]['value'][1] ?? null;

            return is_numeric($value) ? (float) $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Ask Prometheus to forget a set of series.
     *
     * Needed after wiping the app's counters: to Prometheus a counter that drops
     * to zero looks like a process restart, and increase() would then invent a
     * spike. Deleting the series makes it start clean.
     *
     * Requires --web.enable-admin-api on Prometheus.
     */
    public function deleteSeries(string $match): bool
    {
        try {
            // asForm() matters: Laravel's HTTP client sends JSON by default, and
            // the admin API only reads form or query parameters. Sent as JSON it
            // quietly matches nothing and still answers 204.
            return Http::timeout(2)
                ->asForm()
                ->post(rtrim($this->baseUrl, '/').'/api/v1/admin/tsdb/delete_series', [
                    'match[]' => $match,
                ])
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }
}
