<?php

namespace App\Providers;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;
use Yajra\Oci8\Connectors\OracleConnector;
use Yajra\Oci8\Oci8Connection;

class OracleDatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('db.connector.oracle', function ($app) {
            return new OracleConnector();
        });

        $this->app->bind('db.connection.oracle', function ($app, $parameters) {
            $connection = $parameters['connection'] ?? null;
            $config = $parameters['config'] ?? [];

            $db = new Oci8Connection(
                $connection,
                $config['database'] ?? '',
                $config['prefix'] ?? '',
                $config
            );

            return $db;
        });
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('oracle', function ($config, $name) {
                $config['name'] = $name;
                $connector = $this->app->make('db.connector.oracle');
                $connection = $connector->connect($config);

                return $this->app->make('db.connection.oracle', [
                    'connection' => $connection,
                    'config' => $config
                ]);
            });
        });
    }
}
