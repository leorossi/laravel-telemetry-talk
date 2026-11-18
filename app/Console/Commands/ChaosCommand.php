<?php

namespace App\Console\Commands;

use App\Support\Chaos;
use Illuminate\Console\Command;

class ChaosCommand extends Command
{
    protected $signature = 'demo:chaos {key?} {value?} {--reset : Restore every switch to its default}';

    protected $description = 'Show or change the runtime chaos switches used by the demo panel';

    public function handle(Chaos $chaos): int
    {
        if ($this->option('reset')) {
            $chaos->reset();
            $this->components->info('Chaos switches reset to defaults.');
        } elseif ($this->argument('key') !== null && $this->argument('value') !== null) {
            $chaos->set($this->argument('key'), $this->argument('value'));
            $this->components->info("Chaos switch [{$this->argument('key')}] set.");
        }

        $defaults = $chaos->defaults();
        $rows = [];

        foreach ($chaos->all() as $key => $value) {
            $rows[] = [
                $key,
                $this->format($value),
                $value === ($defaults[$key] ?? null) ? '' : 'changed',
                $chaos->label($key),
            ];
        }

        $this->table(['switch', 'value', 'state', 'meaning'], $rows);

        return self::SUCCESS;
    }

    protected function format(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'on' : 'off',
            default => (string) $value,
        };
    }
}
