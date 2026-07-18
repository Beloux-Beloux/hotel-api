<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\PriorityCalculationService;
use App\Services\ReservationService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PriorityCalculationService::class, function ($app) {
            return new PriorityCalculationService($app->make(ReservationService::class));
        });

        $this->app->singleton(ReservationService::class, function ($app) {
            return new ReservationService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
