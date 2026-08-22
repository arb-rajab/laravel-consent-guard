<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard;

use ArbRajab\ConsentGuard\AuditLog\AuditLogger;
use ArbRajab\ConsentGuard\AuditLog\Console\SecureAuditLogCommand;
use Illuminate\Support\ServiceProvider;

class ConsentGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/consent-guard.php', 'consent-guard');

        $this->app->singleton(AuditLogger::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/consent-guard.php' => $this->app->configPath('consent-guard.php'),
        ], 'consent-guard-config');

        $this->publishesMigrations([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'consent-guard-migrations');

        $this->commands([
            SecureAuditLogCommand::class,
        ]);
    }
}
