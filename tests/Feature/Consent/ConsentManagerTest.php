<?php

declare(strict_types=1);

use ArbRajab\ConsentGuard\Consent\ConsentManager;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->migrateConsentTable();
});

afterEach(function () {
    $this->dropConsentTable();
});

it('is not granted when no consent record exists at all', function () {
    expect(app(ConsentManager::class)->isGranted('user', 'u-1', 'marketing_email'))->toBeFalse();
});

it('is granted after grant() with no expiry', function () {
    $manager = app(ConsentManager::class);
    $manager->grant('user', 'u-1', 'marketing_email');

    expect($manager->isGranted('user', 'u-1', 'marketing_email'))->toBeTrue();
});

it('is not granted after withdraw()', function () {
    $manager = app(ConsentManager::class);
    $manager->grant('user', 'u-1', 'marketing_email');
    $manager->withdraw('user', 'u-1', 'marketing_email');

    expect($manager->isGranted('user', 'u-1', 'marketing_email'))->toBeFalse();
});

it('is granted again after withdraw() then grant() re-grants the same subject/purpose', function () {
    $manager = app(ConsentManager::class);
    $manager->grant('user', 'u-1', 'marketing_email');
    $manager->withdraw('user', 'u-1', 'marketing_email');
    $manager->grant('user', 'u-1', 'marketing_email');

    expect($manager->isGranted('user', 'u-1', 'marketing_email'))->toBeTrue();
});

it('is not granted once expires_at is in the past', function () {
    $manager = app(ConsentManager::class);
    $manager->grant('user', 'u-1', 'marketing_email', Carbon::now()->subMinute());

    expect($manager->isGranted('user', 'u-1', 'marketing_email'))->toBeFalse();
});

it('is granted while expires_at is still in the future', function () {
    $manager = app(ConsentManager::class);
    $manager->grant('user', 'u-1', 'marketing_email', Carbon::now()->addDay());

    expect($manager->isGranted('user', 'u-1', 'marketing_email'))->toBeTrue();
});

it('keeps consent for different purposes independent', function () {
    $manager = app(ConsentManager::class);
    $manager->grant('user', 'u-1', 'marketing_email');

    expect($manager->isGranted('user', 'u-1', 'marketing_email'))->toBeTrue();
    expect($manager->isGranted('user', 'u-1', 'analytics_tracking'))->toBeFalse();
});

it('keeps consent for different subjects independent', function () {
    $manager = app(ConsentManager::class);
    $manager->grant('user', 'u-1', 'marketing_email');

    expect($manager->isGranted('user', 'u-1', 'marketing_email'))->toBeTrue();
    expect($manager->isGranted('user', 'u-2', 'marketing_email'))->toBeFalse();
});
