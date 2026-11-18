<?php

namespace App\Http\Controllers;

use App\Support\TraceIdInLogs;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class LogsController extends Controller
{
    /**
     * Every line this page writes carries this marker, so the list below can
     * show just those and not the rest of the application log (scheduler
     * heartbeats, console output, old requests) drowning the demo.
     */
    protected const WRITE_MARKER = 'LogsController::write';

    public function __construct(protected TraceIdInLogs $switch) {}

    public function index(): View
    {
        return view('logs', [
            'injectTraceId' => $this->switch->enabled(),
            'entries' => $this->recentEntries(8),
            'jaegerUrl' => 'http://localhost:16686',
            'logPath' => 'storage/logs/laravel.log',
        ]);
    }

    public function toggle(): RedirectResponse
    {
        $enabled = $this->switch->toggle();

        return back()->with('status', sprintf(
            'Trace id in log lines is now %s.',
            $enabled ? 'ON' : 'OFF',
        ));
    }

    public function write(): RedirectResponse
    {
        Log::info('A log line written on demand from /logs', [
            'source' => self::WRITE_MARKER,
        ]);

        return back()->with('status', sprintf(
            'Wrote a log entry with the trace id %s.',
            $this->switch->enabled() ? 'switched ON' : 'switched OFF',
        ));
    }

    /**
     * The last few entries this page wrote to the application log, newest first.
     *
     * An entry runs from a `[timestamp] env.LEVEL:` header up to the next one, so
     * multi-line stack traces stay attached to the line that produced them.
     *
     * @return array<int, array{header: string, extraLines: int, traceId: ?string}>
     */
    protected function recentEntries(int $limit): array
    {
        $path = storage_path('logs/laravel.log');

        if (! is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        // Only the tail: the file grows to megabytes and we want the last lines.
        $tailBytes = 400 * 1024;

        if (filesize($path) > $tailBytes) {
            fseek($handle, -$tailBytes, SEEK_END);
            fgets($handle); // drop the partial line the seek landed in
        }

        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        $entries = [];

        foreach (preg_split('/^(?=\[\d{4}-\d{2}-\d{2} )/m', $tail) ?: [] as $chunk) {
            if (trim($chunk) === '' || ! str_contains($chunk, self::WRITE_MARKER)) {
                continue;
            }

            $lines = explode("\n", rtrim($chunk));
            $header = $lines[0];
            preg_match('/"trace_id":"([a-f0-9]{32})"/', $header, $matches);

            $entries[] = [
                'header' => $header,
                'extraLines' => max(0, count($lines) - 1),
                'traceId' => $matches[1] ?? null,
            ];
        }

        return array_slice(array_reverse($entries), 0, $limit);
    }
}
