<?php

namespace Tests\Feature;

use App\Support\Chaos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChaosPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_renders(): void
    {
        $this->get('/chaos')->assertOk()->assertSee('external_latency_ms');
    }

    public function test_a_switch_can_be_applied_and_reset(): void
    {
        $this->post('/chaos/apply', ['key' => 'external_latency_ms', 'value' => '2000'])
            ->assertRedirect();

        $this->assertSame(2000, (new Chaos)->get('external_latency_ms'));

        $this->post('/chaos/reset')->assertRedirect(route('demo.chaos'));

        $this->assertSame(0, (new Chaos)->get('external_latency_ms'));
    }

    public function test_unknown_switches_are_ignored(): void
    {
        $this->post('/chaos/apply', ['key' => 'drop_tables', 'value' => '1'])->assertRedirect();

        $this->assertSame([], (new Chaos)->changed());
    }

    public function test_query_parameters_override_switches_for_one_request_only(): void
    {
        $this->get('/chaos?external_latency_ms=5000')->assertOk();

        $this->assertSame(5000, app(Chaos::class)->get('external_latency_ms'));
        $this->assertSame(0, (new Chaos)->get('external_latency_ms'));
    }
}
