<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentStatusQuery;
use App\Services\ManualShippingCalculator;
use App\Services\ManualTransferGateway;
use App\Services\MercadoPagoGateway;
use App\Services\ShippingCalculator;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(TelescopeApplicationServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->app->bind(ShippingCalculator::class, ManualShippingCalculator::class);
        $this->app->bind(PaymentGateway::class, ManualTransferGateway::class);
        $this->app->bind(MercadoPagoGateway::class);
        $this->app->bind(PaymentStatusQuery::class, MercadoPagoGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
