<?php

namespace App\Providers;

use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\MockPaymentGateway;
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
        $this->app->bind(PaymentGateway::class, MockPaymentGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureBlueprintMacros();
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
