<?php

declare(strict_types=1);

use ArbRajab\ConsentGuard\Tests\Feature\AuditLog\InteractsWithAuditLogSchema;
use ArbRajab\ConsentGuard\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);
uses(InteractsWithAuditLogSchema::class)->in(__DIR__.'/Feature/AuditLog');
