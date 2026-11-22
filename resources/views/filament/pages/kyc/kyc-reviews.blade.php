<x-filament-panels::page>
    @php($counts = $this->tabCounts)

    <x-filament::tabs label="KYC review tabs">
        @foreach (\App\Filament\Pages\Kyc\KycReviews::TAB_LABELS as $key => $label)
            <x-filament::tabs.item
                :active="$activeTab === $key"
                wire:click="setActiveTab('{{ $key }}')"
                :badge="$counts[$key] ?? 0"
                :badge-color="$key === 'awaiting' && ($counts[$key] ?? 0) > 0 ? 'warning' : 'gray'"
            >
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    {{ $this->getTable()->render() }}
</x-filament-panels::page>
