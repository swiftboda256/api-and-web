<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\WelcomeWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;

/**
 * The stock dashboard. An admin whose role has none of the widget permissions (View:<WidgetClass>)
 * gets just the greeting banner (no quick links) instead of a blank page.
 */
class Dashboard extends BaseDashboard
{
    public function content(Schema $schema): Schema
    {
        if ($this->hasVisibleWidgets()) {
            return parent::content($schema);
        }

        return $schema->components([
            Livewire::make(WelcomeWidget::class, ['greetingOnly' => true])->key('welcome-greeting-only'),
        ]);
    }

    public function hasVisibleWidgets(): bool
    {
        return $this->filterVisibleWidgets($this->getWidgets()) !== [];
    }
}
