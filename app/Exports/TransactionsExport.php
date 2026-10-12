<?php

namespace App\Exports;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports transactions with a caller-chosen subset of columns, in the caller's order.
 */
class TransactionsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public const array COLUMNS = [
        'id' => 'ID',
        'user' => 'User',
        'phone' => 'Phone',
        'method' => 'Method',
        'direction' => 'Direction',
        'transaction_type' => 'Type',
        'amount' => 'Amount',
        'currency_code' => 'Currency',
        'balance_before' => 'Balance before',
        'balance_after' => 'Balance after',
        'status' => 'Status',
        'failure_reason' => 'Failure reason',
        'narration' => 'Narration',
        'gateway' => 'Gateway',
        'gateway_reference' => 'Gateway reference',
        'external_reference' => 'External reference',
        'network_reference' => 'Network reference',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
        'creator' => 'Created by',
    ];

    /** @var list<string> */
    private array $columns;

    /**
     * @param  Builder<Transaction>  $query
     * @param  list<string>  $columns
     */
    public function __construct(private readonly Builder $query, array $columns)
    {
        // Keep the caller's order, dropping unknown and duplicate columns.
        $this->columns = array_values(array_unique(array_filter(
            $columns,
            fn (string $column): bool => array_key_exists($column, self::COLUMNS),
        )));
    }

    /**
     * @return Builder<Transaction>
     */
    public function query(): Builder
    {
        return $this->query->with(['user', 'creator']);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_map(fn (string $column): string => self::COLUMNS[$column], $this->columns);
    }

    /**
     * @param  Transaction  $row
     * @return list<mixed>
     */
    public function map($row): array
    {
        return array_map(fn (string $column): mixed => match ($column) {
            'user' => $row->user?->name,
            'creator' => $row->creator?->name,
            'method', 'direction', 'transaction_type', 'status' => filled($row->{$column}) ? str($row->{$column})->headline()->value() : null,
            'amount', 'balance_before', 'balance_after' => $row->{$column} !== null ? (float) $row->{$column} : null,
            'created_at', 'updated_at' => $row->{$column}?->format('Y-m-d H:i:s'),
            default => $row->{$column},
        }, $this->columns);
    }
}
