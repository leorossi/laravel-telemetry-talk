<x-pulse>
    <livewire:pulse.servers cols="8"  rows="1"/>

    {{-- Custom card: App\Livewire\Pulse\OrdersCard reads the entries written by
         App\Pulse\Recorders\Orders. --}}
    <livewire:pulse.orders cols="4" rows="1" />

    <livewire:pulse.usage cols="4" rows="1" />

    <livewire:pulse.queues cols="4" />

    <livewire:pulse.cache cols="4" />

    <livewire:pulse.slow-jobs cols="6" />

    <livewire:pulse.slow-queries cols="6" />

    <livewire:pulse.slow-requests cols="6" />

    <livewire:pulse.slow-outgoing-requests cols="6" />

    <livewire:pulse.exceptions cols="full" />
</x-pulse>
