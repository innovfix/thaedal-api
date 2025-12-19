<?php

namespace App\Providers;

use App\Services\FcmService;
use App\Services\RazorpayService;
use App\Services\SmsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsService::class, function ($app) {
            return new SmsService();
        });

        $this->app->singleton(RazorpayService::class, function ($app) {
            return new RazorpayService();
        });

        $this->app->singleton(FcmService::class, function ($app) {
            return new FcmService();
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

