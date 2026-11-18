<?php

namespace Tests\Feature;

use App\Support\Chaos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConsoleTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_heartbeat_rolls_up_the_dataset(): void
    {
        $this->artisan('demo:heartbeat')->assertSuccessful();

        $snapshot = Cache::get('demo:heartbeat');

        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('orders', $snapshot);
        $this->assertArrayHasKey('ran_at', $snapshot);
    }

    public function test_chaos_command_can_show_set_and_reset(): void
    {
        $this->artisan('demo:chaos')->assertSuccessful();

        $this->artisan('demo:chaos', ['key' => 'db_sleep_ms', 'value' => '1000'])->assertSuccessful();
        $this->assertSame(1000, (new Chaos)->get('db_sleep_ms'));

        $this->artisan('demo:chaos', ['--reset' => true])->assertSuccessful();
        $this->assertSame(0, (new Chaos)->get('db_sleep_ms'));
    }
}
