<?php

namespace App\Http\Controllers;

use App\Support\OrderMetrics;
use App\Support\QueueMetrics;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;

class MetricsController extends Controller
{
    public function __invoke(OrderMetrics $orders, QueueMetrics $queue): Response
    {
        // Gauges are point-in-time values, so they are refreshed on every
        // scrape. Counters and histograms are written as the app handles
        // requests; this endpoint only renders what is already stored.
        $queue->expose();

        return response($orders->render(), 200, [
            'Content-Type' => RenderTextFormat::MIME_TYPE,
        ]);
    }
}
