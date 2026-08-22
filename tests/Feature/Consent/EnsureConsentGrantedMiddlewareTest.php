<?php

declare(strict_types=1);

use ArbRajab\ConsentGuard\Consent\ConsentManager;
use ArbRajab\ConsentGuard\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->migrateConsentTable();

    Route::middleware(['consent-guard:marketing_email'])
        ->get('/consent-guarded', fn () => response()->json(['ok' => true]));
});

afterEach(function () {
    $this->dropConsentTable();
});

it('denies an unauthenticated request', function () {
    $this->getJson('/consent-guarded')->assertStatus(403);
});

it('denies a request whose user has no recorded consent for the purpose', function () {
    $this->actingAs(new TestUser('u-1'))
        ->getJson('/consent-guarded')
        ->assertStatus(403)
        ->assertJsonPath('purpose', 'marketing_email');
});

it('denies a request whose user withdrew consent', function () {
    $manager = app(ConsentManager::class);
    $manager->grant(TestUser::class, 'u-1', 'marketing_email');
    $manager->withdraw(TestUser::class, 'u-1', 'marketing_email');

    $this->actingAs(new TestUser('u-1'))
        ->getJson('/consent-guarded')
        ->assertStatus(403);
});

it('allows a request whose user has granted, unexpired consent for the purpose', function () {
    app(ConsentManager::class)->grant(TestUser::class, 'u-1', 'marketing_email');

    $this->actingAs(new TestUser('u-1'))
        ->getJson('/consent-guarded')
        ->assertOk()
        ->assertJson(['ok' => true]);
});

it("does not let one user's consent authorize a different user's request", function () {
    app(ConsentManager::class)->grant(TestUser::class, 'u-1', 'marketing_email');

    $this->actingAs(new TestUser('u-2'))
        ->getJson('/consent-guarded')
        ->assertStatus(403);
});
