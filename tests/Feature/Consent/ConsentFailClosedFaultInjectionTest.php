<?php

declare(strict_types=1);

use ArbRajab\ConsentGuard\Consent\ConsentManager;
use ArbRajab\ConsentGuard\Consent\Contracts\ConsentRepository;
use ArbRajab\ConsentGuard\Consent\EloquentConsentRepository;
use ArbRajab\ConsentGuard\Consent\Exceptions\ConsentRequiredException;
use ArbRajab\ConsentGuard\Tests\Fixtures\Person;
use ArbRajab\ConsentGuard\Tests\Fixtures\TestUser;
use ArbRajab\ConsentGuard\Tests\Fixtures\ThrowingConsentRepository;
use Illuminate\Support\Facades\Route;

/**
 * The fail-closed proof required by this session's brief, matching the
 * principle documented in privacy-forge's ADR-0006: when the system
 * cannot determine consent status — here, because the lookup itself
 * throws — it must deny, never allow. The critical case below grants real,
 * valid consent *first*, then breaks only the lookup, so a pass can't be
 * explained by "there was nothing to grant anyway." If this package ever
 * regressed to fail-open (e.g. someone "simplifies" ConsentManager's
 * try/catch into just letting the exception propagate past the middleware
 * into a default-allow, or catches it and returns true), this test fails.
 */
beforeEach(function () {
    $this->migrateConsentTable();
    $this->migratePeopleTable();

    Route::middleware(['consent-guard:marketing_email'])
        ->get('/consent-guarded', fn () => response()->json(['ok' => true]));
});

afterEach(function () {
    $this->dropPeopleTable();
    $this->dropConsentTable();
});

it('positive control: allows the request when the lookup succeeds and consent is genuinely granted', function () {
    app(ConsentManager::class)->grant(TestUser::class, 'u-1', 'marketing_email');

    $this->actingAs(new TestUser('u-1'))
        ->getJson('/consent-guarded')
        ->assertOk();
});

it('ConsentManager::isGranted() returns false, and does not throw, when the repository lookup itself throws', function () {
    app(ConsentManager::class)->grant(TestUser::class, 'u-1', 'marketing_email');

    $this->app->bind(ConsentRepository::class, ThrowingConsentRepository::class);

    expect(app(ConsentManager::class)->isGranted(TestUser::class, 'u-1', 'marketing_email'))->toBeFalse();
});

it('denies the request — rather than allowing it or bubbling a 500 — when the consent lookup throws, even though consent was actually granted', function () {
    app(ConsentManager::class)->grant(TestUser::class, 'u-1', 'marketing_email');

    $this->app->bind(ConsentRepository::class, ThrowingConsentRepository::class);

    $this->actingAs(new TestUser('u-1'))
        ->getJson('/consent-guarded')
        ->assertStatus(403);
});

/**
 * The ConsentRequired cast's write path (set()) goes through the exact same
 * ConsentManager::isGranted() choke point as the middleware — this pair
 * proves that directly rather than leaving it inferred from the tests
 * above. The positive control grants real, valid consent first, so the
 * denial below can't be explained by "there was nothing to grant anyway."
 */
it('ConsentRequired cast positive control: allows the write when the lookup succeeds and consent is genuinely granted', function () {
    $person = Person::create(['name' => 'Ada']);
    app(ConsentManager::class)->grant(Person::class, $person->id, 'background_check');

    $person->update(['ssn' => '123-45-6789']);

    expect($person->fresh()->ssn)->toBe('123-45-6789');
});

it('ConsentRequired cast denies the write — rather than allowing it or bubbling an unrelated crash — when the consent lookup throws, even though consent was actually granted', function () {
    $person = Person::create(['name' => 'Ada']);
    app(ConsentManager::class)->grant(Person::class, $person->id, 'background_check');

    $this->app->bind(ConsentRepository::class, ThrowingConsentRepository::class);

    expect(fn () => $person->update(['ssn' => '123-45-6789']))
        ->toThrow(ConsentRequiredException::class, 'requires valid, non-withdrawn consent');

    $this->app->bind(ConsentRepository::class, EloquentConsentRepository::class);
    expect($person->fresh()->ssn)->toBeNull();
});
