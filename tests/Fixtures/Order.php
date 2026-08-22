<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Tests\Fixtures;

use ArbRajab\ConsentGuard\AuditLog\Concerns\HasTamperEvidentAuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Stand-in for "any Eloquent model in a host application" — deliberately
 * has no table of its own and is never persisted; it exists only to prove
 * HasTamperEvidentAuditLog works on an arbitrary named model.
 */
class Order extends Model
{
    use HasTamperEvidentAuditLog;

    protected $table = 'orders';

    public function getKey(): string
    {
        return 'order-123';
    }
}
