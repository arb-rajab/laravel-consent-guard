<?php

declare(strict_types=1);

use ArbRajab\ConsentGuard\AuditLog\AuditLogEntry;
use ArbRajab\ConsentGuard\AuditLog\AuditLogger;
use ArbRajab\ConsentGuard\Tests\Fixtures\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->migrateAuditLogTable();
    $this->secureAuditLogTable();
});

afterEach(function () {
    $this->dropAuditLogTable();
});

it('chains the first entry to the genesis hash', function () {
    $entry = app(AuditLogger::class)->record(
        actorType: 'system',
        actorId: null,
        action: 'widget.created',
        subjectType: 'widget',
        subjectId: (string) Str::uuid(),
    );

    expect($entry->sequence)->toBe(1);
    expect($entry->prev_hash)->toBe(AuditLogger::genesisHash());
    expect($entry->entry_hash)->not->toBe(AuditLogger::genesisHash());
});

it('chains each subsequent entry to the previous entry_hash', function () {
    $logger = app(AuditLogger::class);

    $first = $logger->record('system', null, 'widget.created', 'widget', 'w-1');
    $second = $logger->record('system', null, 'widget.updated', 'widget', 'w-1');
    $third = $logger->record('system', null, 'widget.deleted', 'widget', 'w-1');

    expect($second->prev_hash)->toBe($first->entry_hash);
    expect($third->prev_hash)->toBe($second->entry_hash);
    expect($logger->verifyChain())->toBe(['valid' => true, 'brokenAtSequence' => null]);
});

it('round-trips arbitrary metadata through the hash without breaking verification', function () {
    $logger = app(AuditLogger::class);

    $logger->record('user', 'u-42', 'order.shipped', 'order', 'o-1', [
        'carrier' => 'ups',
        'tracking_number' => '1Z999AA10123456784',
        'items' => ['sku-1', 'sku-2'],
    ]);

    expect($logger->verifyChain())->toBe(['valid' => true, 'brokenAtSequence' => null]);
    expect(AuditLogEntry::query()->sole()->metadata)->toBe([
        'carrier' => 'ups',
        'tracking_number' => '1Z999AA10123456784',
        'items' => ['sku-1', 'sku-2'],
    ]);
});

it('detects a single tampered entry via verifyChain', function () {
    $logger = app(AuditLogger::class);

    $logger->record('system', null, 'widget.created', 'widget', 'w-1');
    $logger->record('system', null, 'widget.updated', 'widget', 'w-1');

    // Tamper directly at the DB layer (bypassing AuditLogEntry's append-only
    // guard) via the owning connection — simulating an attacker with direct
    // database access rather than one going through this package's models.
    DB::connection('pgsql_owner')
        ->table((string) config('consent-guard.audit_log.table'))
        ->where('sequence', 1)
        ->update(['action' => 'widget.tampered']);

    expect($logger->verifyChain())->toBe(['valid' => false, 'brokenAtSequence' => 1]);
});

it('refuses to update or delete an AuditLogEntry through the model itself', function () {
    $entry = app(AuditLogger::class)->record('system', null, 'widget.created', 'widget', 'w-1');

    expect(fn () => tap($entry)->update(['action' => 'tampered']))
        ->toThrow(LogicException::class, 'append-only');

    expect(fn () => $entry->delete())
        ->toThrow(LogicException::class, 'cannot be deleted');
});

it('lets any Eloquent model record an audit entry about itself via the trait', function () {
    $order = new Order;

    $entry = $order->recordAuditEntry('order.shipped', ['carrier' => 'ups']);

    expect($entry->subject_type)->toBe(Order::class);
    expect($entry->subject_id)->toBe('order-123');
    expect($entry->action)->toBe('order.shipped');
    expect($entry->metadata)->toBe(['carrier' => 'ups']);
});
