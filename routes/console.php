<?php

use App\Console\Commands\TelemetryHeartbeatCommand;
use Illuminate\Support\Facades\Schedule;

Schedule::command(TelemetryHeartbeatCommand::class)->everyMinute()->withoutOverlapping();
