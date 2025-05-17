<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Yajra\Oci8\Oci8ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register the Oracle service provider
        $this->app->register(Oci8ServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure the Oracle driver grammar is loaded
        if (DB::connection() instanceof \Illuminate\Database\OracleConnection) {
            Schema::defaultStringLength(191);
        }
    }
}
