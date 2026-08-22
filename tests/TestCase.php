<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Tests;

use ArbRajab\ConsentGuard\ConsentGuardServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ConsentGuardServiceProvider::class];
    }
}
