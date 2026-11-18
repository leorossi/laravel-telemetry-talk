<?php

namespace App\Http\Controllers;

use App\Services\OrderReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheDemoController extends Controller
{
    public const CACHE_KEY = 'demo:cache-pattern';

    public function __construct(protected OrderReportService $reports) {}

    public function __invoke(Request $request): View
    {
        if ($request->boolean('clear')) {
            Cache::forget(self::CACHE_KEY);
        }

        $hit = Cache::has(self::CACHE_KEY);
        $computeMs = null;

        $series = Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds((int) config('telemetry-demo.cache_ttl_seconds')),
            function () use (&$computeMs): array {
                $startedAt = microtime(true);
                $series = $this->reports->revenueSeries(60);
                $computeMs = round((microtime(true) - $startedAt) * 1000, 1);

                return $series;
            },
        );

        return view('demo.cache', [
            'hit' => $hit,
            'computeMs' => $computeMs,
            'series' => $series,
            'cacheKey' => self::CACHE_KEY,
            'cacheStore' => Cache::getStore()::class,
        ]);
    }
}
