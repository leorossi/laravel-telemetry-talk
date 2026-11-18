<?php

namespace App\Http\Middleware;

use App\Support\Chaos;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class InjectDatabaseLatency
{
    public function __construct(protected Chaos $chaos) {}

    public function handle(Request $request, Closure $next): Response
    {
        $sleepMs = (int) $this->chaos->get('db_sleep_ms', 0);

        if ($sleepMs > 0
            && DB::connection()->getDriverName() === 'pgsql'
            && ! $request->is('chaos*', 'pulse*', 'traceparent*', 'logs*', 'metrics*', 'orders*', 'nightwatch*', 'up')) {
            DB::select('select pg_sleep(?::double precision)', [$sleepMs / 1000]);
        }

        return $next($request);
    }
}
