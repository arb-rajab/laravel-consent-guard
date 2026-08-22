<?php

declare(strict_types=1);

use ArbRajab\ConsentGuard\Consent\ConsentRecord;
use ArbRajab\ConsentGuard\Consent\Events\ConsentGracePeriodElapsed;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->migrateConsentTable();
    config(['consent-guard.consent.default_grace_period_days' => 30]);
});

afterEach(function () {
    $this->dropConsentTable();
});

it('dispatches ConsentGracePeriodElapsed for a withdrawn record past its grace period', function () {
    Event::fake([ConsentGracePeriodElapsed::class]);

    $record = ConsentRecord::create([
        'subject_type' => 'user',
        'subject_id' => 'u-1',
        'purpose' => 'marketing_email',
        'granted_at' => now()->subDays(60),
        'withdrawn_at' => now()->subDays(45),
    ]);

    $this->artisan('consent-guard:sweep-expired-consent')->assertExitCode(0);

    Event::assertDispatched(ConsentGracePeriodElapsed::class, fn ($event) => $event->subjectType === 'user'
        && $event->subjectId === 'u-1'
        && $event->purpose === 'marketing_email'
        && $event->consentRecord->is($record));
});

it('does not dispatch for a withdrawn record still inside its grace period', function () {
    Event::fake([ConsentGracePeriodElapsed::class]);

    ConsentRecord::create([
        'subject_type' => 'user',
        'subject_id' => 'u-1',
        'purpose' => 'marketing_email',
        'granted_at' => now()->subDays(10),
        'withdrawn_at' => now()->subDays(5),
    ]);

    $this->artisan('consent-guard:sweep-expired-consent')->assertExitCode(0);

    Event::assertNotDispatched(ConsentGracePeriodElapsed::class);
});

it('does not dispatch for a record that is still actively granted', function () {
    Event::fake([ConsentGracePeriodElapsed::class]);

    ConsentRecord::create([
        'subject_type' => 'user',
        'subject_id' => 'u-1',
        'purpose' => 'marketing_email',
        'granted_at' => now()->subDays(90),
    ]);

    $this->artisan('consent-guard:sweep-expired-consent')->assertExitCode(0);

    Event::assertNotDispatched(ConsentGracePeriodElapsed::class);
});

it('respects a per-purpose grace period override over the default', function () {
    Event::fake([ConsentGracePeriodElapsed::class]);
    config(['consent-guard.consent.purposes.marketing_email.grace_period_days' => 3]);

    ConsentRecord::create([
        'subject_type' => 'user',
        'subject_id' => 'u-1',
        'purpose' => 'marketing_email',
        'granted_at' => now()->subDays(10),
        'withdrawn_at' => now()->subDays(5),
    ]);

    $this->artisan('consent-guard:sweep-expired-consent')->assertExitCode(0);

    Event::assertDispatched(ConsentGracePeriodElapsed::class);
});

it('dry-run reports without dispatching or purging', function () {
    Event::fake([ConsentGracePeriodElapsed::class]);
    config(['consent-guard.consent.purge_expired_records' => true]);

    $record = ConsentRecord::create([
        'subject_type' => 'user',
        'subject_id' => 'u-1',
        'purpose' => 'marketing_email',
        'granted_at' => now()->subDays(60),
        'withdrawn_at' => now()->subDays(45),
    ]);

    $this->artisan('consent-guard:sweep-expired-consent', ['--dry-run' => true])->assertExitCode(0);

    Event::assertNotDispatched(ConsentGracePeriodElapsed::class);
    expect(ConsentRecord::query()->find($record->id))->not->toBeNull();
});

it('purges the consent record after dispatching when purge_expired_records is enabled', function () {
    config(['consent-guard.consent.purge_expired_records' => true]);

    $record = ConsentRecord::create([
        'subject_type' => 'user',
        'subject_id' => 'u-1',
        'purpose' => 'marketing_email',
        'granted_at' => now()->subDays(60),
        'withdrawn_at' => now()->subDays(45),
    ]);

    $this->artisan('consent-guard:sweep-expired-consent')->assertExitCode(0);

    expect(ConsentRecord::query()->find($record->id))->toBeNull();
});

it('leaves the consent record in place after dispatching when purge_expired_records is disabled', function () {
    config(['consent-guard.consent.purge_expired_records' => false]);

    $record = ConsentRecord::create([
        'subject_type' => 'user',
        'subject_id' => 'u-1',
        'purpose' => 'marketing_email',
        'granted_at' => now()->subDays(60),
        'withdrawn_at' => now()->subDays(45),
    ]);

    $this->artisan('consent-guard:sweep-expired-consent')->assertExitCode(0);

    expect(ConsentRecord::query()->find($record->id))->not->toBeNull();
});

it('sweeps an expired-but-never-withdrawn record past its grace period', function () {
    Event::fake([ConsentGracePeriodElapsed::class]);

    ConsentRecord::create([
        'subject_type' => 'user',
        'subject_id' => 'u-1',
        'purpose' => 'trial_data_processing',
        'granted_at' => now()->subDays(90),
        'expires_at' => now()->subDays(45),
    ]);

    $this->artisan('consent-guard:sweep-expired-consent')->assertExitCode(0);

    Event::assertDispatched(ConsentGracePeriodElapsed::class, fn ($event) => $event->purpose === 'trial_data_processing');
});
