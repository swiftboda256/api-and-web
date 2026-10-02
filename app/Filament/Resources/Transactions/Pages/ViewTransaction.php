<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\Actions\TransactionActions;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Transaction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected string $view = 'filament.resources.transactions.pages.view-transaction';

    #[Url(history: true)]
    public string $tab = 'transaction';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorizeAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->hasRecord() ? 'Transaction #'.$this->getRecord()->getKey() : parent::getTitle();
    }

    #[Computed]
    public function transaction(): Transaction
    {
        return Transaction::query()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['user', 'wallet', 'creator', 'updater', 'deleter'])
            ->where('id', $this->getRecord()->getKey())
            ->firstOrFail();
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    protected function getHeaderActions(): array
    {
        return [
            TransactionActions::checkStatus()
                ->after(fn () => $this->refreshTransaction()),
        ];
    }

    private function refreshTransaction(): void
    {
        $this->getRecord()->refresh();

        unset($this->transaction);
    }
}
