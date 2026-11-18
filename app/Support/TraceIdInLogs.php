<?php

namespace App\Support;

/**
 * The switch behind the /logs page.
 *
 * OpenTelemetry decides whether to put the trace id into Laravel's log context
 * by reading `opentelemetry.logs.inject_trace_id` while the request is being
 * handled (`Tracer::updateLogContext()`, called from the package's request
 * middleware). Setting that config in AppServiceProvider::boot() happens before
 * any middleware runs, so this switch flips the real behaviour — it is not a
 * re-implementation of it.
 *
 * Stored in a file rather than the cache, because the cache store is the
 * database and the query count printed in the footer has to stay honest.
 */
class TraceIdInLogs
{
    public function enabled(): bool
    {
        if (! is_file($this->path())) {
            return true;
        }

        $decoded = json_decode((string) file_get_contents($this->path()), true);

        if (! is_array($decoded) || ! array_key_exists('enabled', $decoded)) {
            return true;
        }

        return (bool) $decoded['enabled'];
    }

    public function set(bool $enabled): void
    {
        file_put_contents($this->path(), json_encode(['enabled' => $enabled], JSON_PRETTY_PRINT));
    }

    public function toggle(): bool
    {
        $enabled = ! $this->enabled();

        $this->set($enabled);

        return $enabled;
    }

    public function forget(): void
    {
        if (is_file($this->path())) {
            unlink($this->path());
        }
    }

    protected function path(): string
    {
        return storage_path('app/trace-id-in-logs.json');
    }
}
