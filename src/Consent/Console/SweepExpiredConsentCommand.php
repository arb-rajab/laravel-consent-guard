<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\Consent\Console;

use ArbRajab\ConsentGuard\Consent\ConsentRecord;
use ArbRajab\ConsentGuard\Consent\Events\ConsentGracePeriodElapsed;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Retention sweep for consent-gated data. This package has no idea what a
 * host app's own gated fields/rows actually are, so it cannot delete or
 * anonymize anything on the host's behalf — instead, for every
 * ConsentRecord whose withdrawal or expiry is older than its configured
 * grace period, it dispatches ConsentGracePeriodElapsed and lets the host
 * app's own listener decide what "act on it" means for its own schema.
 * Optionally (config('consent-guard.consent.purge_expired_records')) it
 * also deletes the ConsentRecord row itself after dispatching — that's
 * safe for this package to do unconditionally on request, since
 * ConsentRecord is this package's own row, not the host's gated data.
 */
class SweepExpiredConsentCommand extends Command
{
    protected $signature = 'consent-guard:sweep-expired-consent
        {--dry-run : Report what would be swept without dispatching events or purging records}';

    protected $description = 'Dispatch ConsentGracePeriodElapsed for consent records whose withdrawal/expiry is past its configured grace period, so the host app can act on its own gated data.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $defaultGraceDays = (int) config('consent-guard.consent.default_grace_period_days', 30);
        $purgeAfterSweep = (bool) config('consent-guard.consent.purge_expired_records', false);
        $now = Carbon::now();
        $swept = 0;

        ConsentRecord::query()
            ->where(function ($query) {
                $query->whereNotNull('withdrawn_at')->orWhereNotNull('expires_at');
            })
            ->orderBy('id')
            ->cursor()
            ->each(function (ConsentRecord $record) use ($now, $defaultGraceDays, $dryRun, $purgeAfterSweep, &$swept) {
                $graceDays = (int) config("consent-guard.consent.purposes.{$record->purpose}.grace_period_days", $defaultGraceDays);
                $referenceDate = $record->withdrawn_at ?? $record->expires_at;

                if ($referenceDate === null || $referenceDate->copy()->addDays($graceDays)->isAfter($now)) {
                    return;
                }

                $swept++;

                $this->line(sprintf(
                    'Grace period elapsed for %s#%s / "%s" (reference: %s, grace: %dd).',
                    $record->subject_type,
                    $record->subject_id,
                    $record->purpose,
                    $referenceDate->toDateTimeString(),
                    $graceDays,
                ));

                if ($dryRun) {
                    return;
                }

                event(new ConsentGracePeriodElapsed(
                    $record->subject_type,
                    $record->subject_id,
                    $record->purpose,
                    $record,
                ));

                if ($purgeAfterSweep) {
                    $record->delete();
                }
            });

        $this->components->info($dryRun
            ? "{$swept} consent record(s) past their grace period (dry run — nothing dispatched or purged)."
            : "{$swept} consent record(s) swept.");

        return self::SUCCESS;
    }
}
