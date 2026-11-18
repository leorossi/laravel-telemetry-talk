<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class Chaos
{
    public const BOOLEANS = ['n_plus_one', 'external_failure'];

    public const INTEGERS = ['external_latency_ms', 'db_sleep_ms', 'job_failure_rate'];

    public const MAX_INTEGER = 30000;

    protected array $overrides = [];

    public function defaults(): array
    {
        return config('telemetry-demo.chaos');
    }

    public function all(): array
    {
        return array_merge($this->defaults(), $this->stored(), $this->overrides);
    }

    public function get(string $key, mixed $fallback = null): mixed
    {
        return $this->all()[$key] ?? $fallback;
    }

    public function override(array $input): void
    {
        foreach ($input as $key => $value) {
            if ($this->knows($key)) {
                $this->overrides[$key] = $this->coerce($key, $value);
            }
        }
    }

    public function set(string $key, mixed $value): void
    {
        if (! $this->knows($key)) {
            return;
        }

        $stored = $this->stored();
        $stored[$key] = $this->coerce($key, $value);

        Cache::forever($this->cacheKey(), $stored);
    }

    public function reset(): void
    {
        Cache::forget($this->cacheKey());
    }

    public function changed(): array
    {
        $defaults = $this->defaults();

        return array_filter(
            $this->all(),
            fn (mixed $value, string $key): bool => $value !== ($defaults[$key] ?? null),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public function label(string $key): string
    {
        return config("telemetry-demo.chaos_labels.{$key}", $key);
    }

    protected function knows(string $key): bool
    {
        return in_array($key, [...self::BOOLEANS, ...self::INTEGERS], true);
    }

    protected function coerce(string $key, mixed $value): bool|int
    {
        if (in_array($key, self::BOOLEANS, true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return max(0, min(self::MAX_INTEGER, (int) $value));
    }

    protected function stored(): array
    {
        $stored = Cache::get($this->cacheKey(), []);

        return is_array($stored) ? $stored : [];
    }

    protected function cacheKey(): string
    {
        return config('telemetry-demo.chaos_cache_key');
    }
}
