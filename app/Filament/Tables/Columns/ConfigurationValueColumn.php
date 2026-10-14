<?php

namespace App\Filament\Tables\Columns;

use App\Models\Configuration;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;

/**
 * A configuration's value: an inline toggle for bool configurations, the plain value otherwise.
 * Filament can only hide a column for the whole table, so one column renders both.
 */
class ConfigurationValueColumn extends ToggleColumn
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->disabled(fn (Configuration $record): bool => $record->value_type !== 'bool'
            || ! auth()->user()?->can('update', $record));
    }

    public function toEmbeddedHtml(): string
    {
        if ($this->getRecord()?->value_type === 'bool') {
            return parent::toEmbeddedHtml();
        }

        return TextColumn::make($this->getName())
            ->table($this->getTable())
            ->record($this->getRecord())
            ->toEmbeddedHtml();
    }
}
