<?php

namespace App\Http\Middleware;

use App\Support\RequestMetrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureRequestMetrics
{
    public function __construct(protected RequestMetrics $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->metrics->start();

        return $next($request);
    }
}
