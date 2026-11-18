<?php

namespace App\Http\Controllers;

use App\Support\Chaos;
use App\Support\SimulatedExternalService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function __invoke(Chaos $chaos, SimulatedExternalService $external): View
    {
        return view('home', [
            'endpoints' => [
                [
                    'title' => 'Fast endpoint',
                    'url' => route('demo.fast'),
                    'method' => 'GET /fast',
                    'note' => 'Served from cache. The baseline nothing else should beat.',
                ],
                [
                    'title' => 'Slow endpoint',
                    'url' => route('demo.slow'),
                    'method' => 'GET /slow',
                    'note' => 'N+1 queries plus a slow call to the external inventory API.',
                ],
                [
                    'title' => 'Cache miss then hit',
                    'url' => route('demo.cache'),
                    'method' => 'GET /cache',
                    'note' => 'Clears the key, pays for the recompute, then serves the hit.',
                ],
                [
                    'title' => 'Queued jobs',
                    'url' => route('demo.jobs'),
                    'method' => 'GET /jobs',
                    'note' => 'Dispatches a chain of three jobs and a batch of eight.',
                ],
                [
                    'title' => 'Failing endpoint',
                    'url' => route('demo.fail'),
                    'method' => 'GET /fail',
                    'note' => 'Throws on purpose so every lens has an error to capture.',
                ],
                [
                    'title' => 'Chaos panel',
                    'url' => route('demo.chaos'),
                    'method' => 'GET /chaos',
                    'note' => 'The remote control: latency, failures and N+1, live.',
                ],
            ],
            'chaos' => $chaos->changed(),
            'heartbeat' => Cache::get('demo:heartbeat'),
            'externalHost' => $external->host(),
            'pulseUrl' => '/pulse',
            'jaegerUrl' => 'http://localhost:16686',
            'grafanaUrl' => config('telemetry-demo.grafana.public_url'),
            'grafanaDashboardUrl' => config('telemetry-demo.grafana.dashboard_url'),
        ]);
    }
}
