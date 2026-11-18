<?php

namespace App\Http\Controllers;

use App\Support\Chaos;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChaosPanelController extends Controller
{
    public const PRESETS = [
        'external_latency_ms' => [0, 500, 2000, 5000],
        'db_sleep_ms' => [0, 250, 1000, 3000],
        'job_failure_rate' => [0, 25, 50, 100],
    ];

    public function __construct(protected Chaos $chaos) {}

    public function index(): View
    {
        return view('chaos', [
            'values' => $this->chaos->all(),
            'defaults' => $this->chaos->defaults(),
            'changed' => $this->chaos->changed(),
            'presets' => self::PRESETS,
        ]);
    }

    public function apply(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string'],
            'value' => ['required'],
        ]);

        $this->chaos->set($validated['key'], $validated['value']);

        $value = $this->chaos->get($validated['key']);

        return back()->with('status', sprintf(
            'Chaos switch [%s] is now [%s].',
            $validated['key'],
            is_bool($value) ? ($value ? 'on' : 'off') : $value,
        ));
    }

    public function reset(): RedirectResponse
    {
        $this->chaos->reset();

        return redirect()->route('demo.chaos')->with('status', 'Every chaos switch is back to its default.');
    }
}
