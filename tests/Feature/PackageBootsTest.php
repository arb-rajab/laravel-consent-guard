<?php

declare(strict_types=1);

use ArbRajab\ConsentGuard\ConsentGuardServiceProvider;
use Illuminate\Foundation\Application;

test('the Testbench harness boots a Laravel application with the package service provider registered', function () {
    expect($this->app)->toBeInstanceOf(Application::class);
    expect($this->app->getProviders(ConsentGuardServiceProvider::class))->not->toBeEmpty();
});
