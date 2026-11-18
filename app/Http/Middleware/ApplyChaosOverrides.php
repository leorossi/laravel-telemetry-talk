<?php

namespace App\Http\Middleware;

use App\Support\Chaos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyChaosOverrides
{
    public function __construct(protected Chaos $chaos) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->chaos->override($request->query());

        return $next($request);
    }
}
