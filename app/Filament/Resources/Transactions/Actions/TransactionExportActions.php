<?php

namespace App\Filament\Resources\Transactions\Actions;

use App\Exports\TransactionsExport;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Export actions for the transactions table: the header action exports everything matching the
 * current filters and search, the bulk action exports only the selected rows.
 */
class TransactionExportActions
{
    private const array FORMATS = [
        ExcelFormat::XLSX => 'Excel (.xlsx)',
        ExcelFormat::CSV => 'CSV (.csv)',
    ];

    public static function export(): Action
    {
        return Action::make('export')
            ->label('Export')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->modalHeading('Export transactions')
            ->modalDescription('Exports every transaction matching the current filters and search.')
            ->modalSubmitActionLabel('Export')
            ->authorize(fn (): bool => Gate::allows('export', Transaction::class))
            ->schema(self::schema())
            ->action(fn (array $data, HasTable $livewire): BinaryFileResponse => self::download(
                $livewire->getFilteredSortedTableQuery(),
                $data,
            ));
    }

    public static function exportBulk(): BulkAction
    {
        return BulkAction::make('export')
            ->label('Export selected')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->modalHeading('Export selected transactions')
            ->modalSubmitActionLabel('Export')
            ->authorize(fn (): bool => Gate::allows('export', Transaction::class))
            ->schema(self::schema())
            // Query the selection instead of loading it, so "select all" across pages stays cheap.
            ->action(fn (array $data, HasTable $livewire): BinaryFileResponse => self::download(
                $livewire->getSelectedTableRecordsQuery(shouldFetchSelectedRecords: false),
                $data,
            ))
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return array<int, Repeater|Radio>
     */
    private static function schema(): array
    {
        return [
            // A simple repeater holds an ordered list of column keys: drag to reorder, delete to drop a column.
            Repeater::make('columns')
                ->label('Columns')
                ->helperText('Columns appear in the sheet in this order. Drag to reorder, remove the ones you do not need.')
                ->simple(
                    Select::make('column')
                        ->options(TransactionsExport::COLUMNS)
                        ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                        ->distinct()
                        ->required(),
                )
                ->default(array_keys(TransactionsExport::COLUMNS))
                ->addActionLabel('Add column')
                ->minItems(1)
                ->maxItems(count(TransactionsExport::COLUMNS))
                ->required()
                ->hintActions([
                    Action::make('selectAllColumns')
                        ->label('Select all')
                        ->link()
                        ->action(fn (Repeater $component) => $component->rawState(self::repeaterItems(array_keys(TransactionsExport::COLUMNS)))),
                    Action::make('clearColumns')
                        ->label('Clear all')
                        ->link()
                        ->color('danger')
                        ->action(fn (Repeater $component) => $component->rawState([])),
                ]),
            Radio::make('format')
                ->label('Format')
                ->options(self::FORMATS)
                ->default(ExcelFormat::XLSX)
                ->inline()
                ->required(),
        ];
    }

    /**
     * Builds a simple repeater's raw state (uuid-keyed items) from a list of column keys.
     *
     * @param  list<string>  $columns
     * @return array<string, array{column: string}>
     */
    private static function repeaterItems(array $columns): array
    {
        return collect($columns)
            ->mapWithKeys(fn (string $column): array => [(string) Str::uuid() => ['column' => $column]])
            ->all();
    }

    /**
     * @param  Builder<Transaction>  $query
     * @param  array<string, mixed>  $data  The export modal's form state.
     */
    private static function download(Builder $query, array $data): BinaryFileResponse
    {
        $format = is_string($data['format'] ?? null) && array_key_exists($data['format'], self::FORMATS)
            ? $data['format']
            : ExcelFormat::XLSX;
        $columns = array_values(array_filter((array) ($data['columns'] ?? []), is_string(...)));
        $extension = strtolower($format);

        return Excel::download(
            new TransactionsExport($query, $columns),
            'transactions-'.now()->format('Y-m-d-His').'.'.$extension,
            $format,
        );
    }
}
