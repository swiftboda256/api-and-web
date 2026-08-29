<?php

namespace App\Providers;

use App\Models\Configuration;
use App\Models\Rating;
use App\Observers\RatingObserver;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\YoPaymentService;
use App\Services\Sms\Contracts\SmsGateway;
use App\Services\Sms\EgoSmsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, EgoSmsGateway::class);
        $this->app->bind(PaymentGateway::class, YoPaymentService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureBlueprintMacros();

        Rating::observe(RatingObserver::class);

        if (! $this->app->runningInConsole()) {
            $this->configureSanctumTokenExpiration();
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Set Sanctum's token expiration (in minutes) from the "configurations" table
     * instead of a static config/env value, so it can be changed without a deploy.
     */
    protected function configureSanctumTokenExpiration(): void
    {
        $days = (int) Configuration::get('sanctum_token_expiration_days', 30);

        config(['sanctum.expiration' => $days > 0 ? $days * 24 * 60 : null]);
    }

    /**
     * Register a Blueprint macro so migrations can add the standard
     * timestamps + created_by/updated_by/deleted_by + soft-delete columns in one call.
     */
    protected function configureBlueprintMacros(): void
    {
        Blueprint::macro('auditColumns', function (): void {
            /** @var Blueprint $this */
            $this->timestamps();
            $this->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $this->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $this->softDeletes();
        });
    }
}
