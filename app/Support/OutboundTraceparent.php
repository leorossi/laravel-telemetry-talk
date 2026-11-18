<?php

namespace App\Support;

/**
 * Records the trace context header this app injects into outgoing HTTP requests.
 *
 * OpenTelemetry's client instrumentation adds a `traceparent` header to every
 * outbound call, carrying the trace id so the receiving service can join the
 * same trace. The `/traceparent` page shows the last one that left the app.
 *
 * This is deliberately a plain file rather than the cache: the cache store is
 * the database, and a cache write would add queries to the count the demo prints
 * in the footer. A file keeps the query count honest.
 */
class OutboundTraceparent
{
    /**
     * @param  string|array<int, string>|null  $traceparent
     */
    public function record(string|array|null $traceparent, ?string $url = null): void
    {
        // Laravel's HTTP client exposes headers as an array of values, even when
        // there is only one.
        $traceparent = is_array($traceparent) ? ($traceparent[0] ?? null) : $traceparent;

        if ($traceparent === null || $traceparent === '') {
            return;
        }

        file_put_contents($this->path(), json_encode([
            'traceparent' => $traceparent,
            'url' => $url,
            'recorded_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{traceparent: string, url: ?string, recorded_at: ?string}|null
     */
    public function last(): ?array
    {
        if (! is_file($this->path())) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($this->path()), true);

        if (! is_array($decoded) || ! isset($decoded['traceparent'])) {
            return null;
        }

        return $decoded;
    }

    public function forget(): void
    {
        if (is_file($this->path())) {
            unlink($this->path());
        }
    }

    protected function path(): string
    {
        return storage_path('app/last-traceparent.json');
    }
}
