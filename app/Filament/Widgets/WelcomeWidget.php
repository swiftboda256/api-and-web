<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Riders\RidersIndex;
use App\Filament\Resources\Configurations\ConfigurationResource;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\PricingRules\PricingRuleResource;
use App\Filament\Resources\PromoCodes\PromoCodeResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Resources\Trips\TripResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Wallets\WalletResource;
use App\Filament\Resources\Zones\ZoneResource;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Widgets\Widget;

/**
 * Greeting + quick links to the main admin sections. A link only shows when the admin can open it.
 */
class WelcomeWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.welcome-widget';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $hour = now()->hour;

        /** @var list<class-string<resource>|class-string<Page>> $destinations */
        $destinations = [
            TripResource::class,
            RidersIndex::class,
            CustomerResource::class,
            UserResource::class,
            TransactionResource::class,
            WalletResource::class,
            PricingRuleResource::class,
            PromoCodeResource::class,
            ZoneResource::class,
            ConfigurationResource::class,
        ];

        $links = collect($destinations)
            ->filter(fn (string $class): bool => is_subclass_of($class, Resource::class) ? $class::canViewAny() : $class::canAccess())
            ->map(fn (string $class): array => [
                'label' => $class::getNavigationLabel(),
                'icon' => $class::getNavigationIcon(),
                'url' => $class::getUrl(),
            ])
            ->values()
            ->all();

        return [
            'greeting' => match (true) {
                $hour < 12 => 'Good morning',
                $hour < 17 => 'Good afternoon',
                default => 'Good evening',
            },
            'name' => Filament::auth()->user()?->first_name,
            'date' => now()->format('l, j F Y'),
            'links' => $links,
        ];
    }
}
