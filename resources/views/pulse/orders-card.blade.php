<x-pulse::card :cols="$cols" :rows="$rows" :class="$class">
    <x-pulse::card-header
        name="Orders"
        x-bind:title="`Time: {{ number_format($time) }}ms; Run at: ${formatDate('{{ $runAt }}')};`"
        details="past {{ $this->periodForHumans() }}"
    >
        <x-slot:icon>
            <x-pulse::icons.arrow-trending-up />
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="$expand" wire:poll.5s="">
        <div class="grid grid-cols-2 gap-6">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Orders</p>
                <p class="text-xl font-bold text-gray-700 dark:text-gray-100">{{ number_format($orders) }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Revenue</p>
                <p class="text-xl font-bold text-gray-700 dark:text-gray-100">€ {{ number_format($revenueCents / 100, 2) }}</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
            <span>Average order <b class="font-semibold">€ {{ number_format($averageCents / 100, 2) }}</b></span>
            @if ($lastAt)
                <span>Last order {{ $lastAt }}</span>
            @endif
        </div>

        @if (count($recent) > 0)
            <div class="mt-3 flex flex-col gap-2 text-xs">
                @foreach (array_reverse($recent) as $item)
                    <div class="flex items-center justify-between gap-2" wire:key="order-{{ $item['reference'] }}">
                        <span class="font-mono truncate text-gray-600 dark:text-gray-300">{{ $item['reference'] }}</span>
                        <span class="text-gray-500 dark:text-gray-400">€ {{ number_format($item['total_cents'] / 100, 2) }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">No orders yet. Create one on <b class="font-semibold">/orders</b>.</p>
        @endif
    </x-pulse::scroll>
</x-pulse::card>