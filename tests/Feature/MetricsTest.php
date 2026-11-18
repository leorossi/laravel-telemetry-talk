<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_exposes_http_and_queue_metrics(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->subSeconds(30)->timestamp,
            'created_at' => now()->subSeconds(30)->timestamp,
        ]);

        $this->get('/fast')->assertOk();

        $metrics = $this->get('/metrics')->assertOk()->getContent();

        $this->assertStringContainsString('demo_http_requests_total', $metrics);
        $this->assertStringContainsString('method="GET"', $metrics);
        $this->assertStringContainsString('route="fast"', $metrics);
        $this->assertStringContainsString('demo_http_request_duration_seconds_bucket', $metrics);
        $this->assertStringContainsString('demo_queue_depth{queue="default"} 1', $metrics);
        $this->assertStringContainsString('demo_failed_jobs{queue="default"} 0', $metrics);
        $this->assertStringContainsString('demo_queue_oldest_job_age_seconds{queue="default"}', $metrics);
    }

    public function test_the_prometheus_scrape_is_not_counted_as_app_traffic(): void
    {
        $this->get('/metrics')->assertOk();
        $this->get('/metrics')->assertOk();

        $metrics = $this->get('/metrics')->assertOk()->getContent();

        $this->assertStringNotContainsString('route="metrics"', $metrics);
    }

    public function test_a_failed_request_is_recorded_with_status_500(): void
    {
        $this->get('/fail')->assertStatus(500);

        $metrics = $this->get('/metrics')->assertOk()->getContent();

        $this->assertStringContainsString('route="fail"', $metrics);
        $this->assertStringContainsString('status="500"', $metrics);
    }
}
