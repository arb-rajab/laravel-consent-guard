<?php

declare(strict_types=1);

use ArbRajab\ConsentGuard\AuditLog\AuditLogEntry;
use ArbRajab\ConsentGuard\AuditLog\AuditLogger;
use Illuminate\Support\Str;

// AuditLogger::record() serializes writers with a single, fixed, global
// pg_advisory_xact_lock key (config('consent-guard.audit_log.lock_key')) —
// not partitioned by actor/subject/anything else. This test proves that
// lock actually does its job under genuine concurrency: separate, real OS
// processes, not just sequential calls from one PHP process racing an
// event loop. A single process issuing "concurrent-looking" calls in a
// loop would never exercise pg_advisory_xact_lock at all, since nothing
// would actually contend for it — Postgres locks serialize separate
// sessions, not separate function calls within one session.
beforeEach(function () {
    if (! extension_loaded('pcntl') || ! extension_loaded('posix')) {
        $this->markTestSkipped('pcntl/posix extensions are not available in this environment (Unix-only; see .github/workflows/ci.yml).');
    }

    $this->migrateAuditLogTable();
    $this->secureAuditLogTable();
});

afterEach(function () {
    $this->dropAuditLogTable();
});

it('serializes genuinely concurrent writers into one unbroken, gapless chain', function () {
    $workerCount = 8;
    $marker = 'test.concurrency.'.getmypid();
    $pids = [];

    for ($i = 0; $i < $workerCount; $i++) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('pcntl_fork failed — cannot run this concurrency test.');
        }

        if ($pid === 0) {
            // Child process. It inherited a copy of the parent's PDO
            // connection — same underlying kernel socket, not a fresh one.
            // Two things must both be avoided:
            //
            // 1. Never disconnect/purge the inherited connection in the
            //    child — for pdo_pgsql that triggers a real libpq
            //    PQfinish() (an actual Terminate protocol message) over
            //    what is, at the kernel level, the same duplicated
            //    connection every forked copy shares, killing the
            //    *parent's* connection too.
            // 2. Never let the child reach normal PHP shutdown either (a
            //    plain exit(), or falling off the end of the callback) —
            //    Zend's request-shutdown sequence destructs every
            //    remaining object, including that same inherited
            //    connection, triggering the identical PQfinish().
            //
            // Fix: leave the inherited connections completely alone, do
            // the real write through separately-named connections that
            // have never existed before in this process (so they open
            // genuinely new, independent TCP connections), and terminate
            // via SIGKILL — a raw signal, not exit() — so Zend's
            // destructor sweep never runs for this process at all.
            config(['database.connections.pgsql_concurrency_child' => config('database.connections.pgsql')]);
            config(['consent-guard.audit_log.connection' => 'pgsql_concurrency_child']);

            try {
                app(AuditLogger::class)->record(
                    actorType: 'system',
                    actorId: null,
                    action: $marker,
                    subjectType: 'test_resource',
                    subjectId: (string) Str::uuid(),
                );
            } catch (Throwable $e) {
                fwrite(STDERR, "child {$i} failed: {$e->getMessage()}".PHP_EOL);
            }

            posix_kill(posix_getpid(), SIGKILL);
        }

        $pids[] = $pid;
    }

    // Every child self-terminates via SIGKILL, so the only thing to check
    // here is that each was reaped — whether the write itself succeeded
    // and produced a correctly-chained entry is verified below, against
    // the database's real state.
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
        expect(pcntl_wifsignaled($status) && pcntl_wtermsig($status) === SIGKILL)
            ->toBeTrue("Child process {$pid} did not terminate the expected way (status: {$status}).");
    }

    $entries = AuditLogEntry::query()
        ->where('action', $marker)
        ->orderBy('sequence')
        ->get();

    expect($entries)->toHaveCount($workerCount, 'Expected every concurrent writer to have committed exactly one entry.');

    // Gapless: Postgres's SEQUENCE guarantees uniqueness regardless of the
    // advisory lock, so this alone would pass even with the lock removed —
    // it is not, by itself, evidence the lock works. Asserted anyway as a
    // sanity check that no writer's insert silently vanished.
    $sequences = $entries->pluck('sequence')->values();

    for ($i = 1; $i < $sequences->count(); $i++) {
        expect($sequences[$i])->toBe($sequences[$i - 1] + 1);
    }

    // The actual proof: replay this batch against the entry immediately
    // preceding it in the chain, confirming no two concurrent writers
    // forked off the same prev_hash. Without the advisory lock, two
    // concurrent transactions can both read the same "current latest" row
    // before either commits — Postgres's SEQUENCE still hands them
    // distinct, ordered numbers (that part can't fork), but both would
    // then compute their entry_hash from the *same* prevHash, so the
    // later-by-sequence entry's prev_hash would not match the entry_hash
    // of the row immediately before it.
    $anchor = AuditLogEntry::query()->where('sequence', $sequences->first() - 1)->first();
    $expectedPrevHash = $anchor?->entry_hash ?? AuditLogger::genesisHash();

    foreach ($entries as $entry) {
        expect($entry->prev_hash)->toBe(
            $expectedPrevHash,
            "Entry at sequence {$entry->sequence} has prev_hash that doesn't match the entry immediately before it — the chain forked under concurrency."
        );
        $expectedPrevHash = $entry->entry_hash;
    }

    expect(app(AuditLogger::class)->verifyChain())->toBe(['valid' => true, 'brokenAtSequence' => null]);
});
