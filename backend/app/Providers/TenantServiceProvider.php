<?php

namespace App\Providers;

use App\Services\TenantService;
use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    /**
     * Registra i servizi
     */
    public function register(): void
    {
        // Registra TenantService come singleton
        $this->app->singleton(TenantService::class, function ($app) {
            return new TenantService();
        });

        // Alias per accesso più semplice
        $this->app->alias(TenantService::class, 'tenant');
    }

    /**
     * Bootstrap dei servizi
     */
    public function boot(): void
    {
        // Registra i middleware
        $router = $this->app['router'];

        $router->aliasMiddleware('tenant', \App\Http\Middleware\TenantMiddleware::class);
        $router->aliasMiddleware('tenant.admin', \App\Http\Middleware\TenantAdminMiddleware::class);

        // Pubblica la configurazione
        $this->publishes([
            __DIR__.'/../../config/tenant.php' => config_path('tenant.php'),
        ], 'tenant-config');

        // Carica le migrazioni
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Configura i binding del container per i repository
        $this->registerRepositoryBindings();
    }

    /**
     * Registra i binding dei repository
     */
    protected function registerRepositoryBindings(): void
    {
        // Esempio di binding repository
        // $this->app->bind(
        //     \App\Repositories\Contracts\UserRepositoryInterface::class,
        //     \App\Repositories\UserRepository::class
        // );
    }
}