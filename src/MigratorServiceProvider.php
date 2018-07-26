<?php

namespace Migrator;

use Illuminate\Support\ServiceProvider;

class MigratorServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->commands([
            MigratorCommand::class
        ]);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->loadMigrationsFrom(realpath(__DIR__.'/../migrations'));
    }
}
