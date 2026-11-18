<?php

namespace App\Http\Controllers;

use App\Services\OrderReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CachedReportController extends Controller
{
    public const CACHE_KEY = 'demo:report:dashboard';

    public function __construct(protected OrderReportService $reports) {}

    public function __invoke(Request $request): View
    {
        if ($request->boolean('refresh')) {
            Cache::forget(self::CACHE_KEY);
        }

        $hit = Cache::has(self::CACHE_KEY);
        $computeMs = null;

        $report = Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds((int) config('telemetry-demo.cache_ttl_seconds')),
            function () use (&$computeMs): array {
                $startedAt = microtime(true);
                $report = $this->reports->dashboard();
                $computeMs = round((microtime(true) - $startedAt) * 1000, 1);

                return $report;
            },
        );

        return view('demo.fast', [
            'hit' => $hit,
            'computeMs' => $computeMs,
            'report' => $report,
            'cacheKey' => self::CACHE_KEY,
            'cacheStore' => Cache::getStore()::class,
        ]);
    }
}
