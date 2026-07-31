<?php

namespace App\Filament\Pages\Riders;

use App\Models\User;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class RidersIndex extends Page
{
    use HasPageShield;
    use WithPagination;

    protected string $view = 'filament.pages.riders.riders-index';

    protected static ?string $slug = 'riders';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Riders';

    protected static ?string $title = 'Riders';

    #[Url(history: false)]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function riders(): LengthAwarePaginator
    {
        $search = trim($this->search);

        return User::query()
            ->whereHas('riderProfile')
            ->with('riderProfile')
            ->when($search !== '', function ($query) use ($search): void {
                $like = "%{$search}%";

                $query->where(function ($query) use ($like): void {
                    $query->where('first_name', 'ilike', $like)
                        ->orWhere('last_name', 'ilike', $like)
                        ->orWhereHas('riderProfile', fn ($query) => $query->where('rider_ref', 'ilike', $like));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15);
    }
}
