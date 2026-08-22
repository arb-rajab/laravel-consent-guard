<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard;

use ArbRajab\ConsentGuard\AuditLog\AuditLogger;
use ArbRajab\ConsentGuard\AuditLog\Console\SecureAuditLogCommand;
use ArbRajab\ConsentGuard\Consent\Console\SweepExpiredConsentCommand;
use ArbRajab\ConsentGuard\Consent\Contracts\ConsentRepository;
use ArbRajab\ConsentGuard\Consent\EloquentConsentRepository;
use ArbRajab\ConsentGuard\Consent\Http\Middleware\EnsureConsentGranted;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class ConsentGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/consent-guard.php', 'consent-guard');

        $this->app->singleton(AuditLogger::class);

        // Deliberately not a singleton: ConsentManager's only dependency is
        // ConsentRepository, and it must always be constructed against
        // whatever that binding currently resolves to (e.g. a test rebinds
        // it to a fake mid-test) rather than caching the repository that
        // was current the first time something resolved ConsentManager.
        $this->app->bind(ConsentRepository::class, EloquentConsentRepository::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('consent-guard', EnsureConsentGranted::class);

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
            SweepExpiredConsentCommand::class,
        ]);
    }
}
