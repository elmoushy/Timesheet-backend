<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class MemoryOptimizationServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Set unlimited memory limit
        ini_set('memory_limit', -1);

        // Alternative: Set a high limit instead of unlimited
        // ini_set('memory_limit', '1024M');

        // Increase max execution time
        ini_set('max_execution_time', 300);

        // Force garbage collection
        gc_enable();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
