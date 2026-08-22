<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\AuditLog\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Installable privilege-separation step for the audit log (R-01-style
 * fix): revokes UPDATE/DELETE on the audit log table from the
 * application's runtime role at the Postgres level, so tampering is
 * rejected by the database itself, not merely unexposed by the app.
 *
 * Must run via a connection authenticated as a role that OWNS the audit
 * log table and is genuinely different from the role being restricted.
 * A table owner can always GRANT privileges back to itself regardless of
 * the ACL's current contents, so "the app's own role revokes its own
 * UPDATE/DELETE" is not a real protection against a compromised or
 * buggy application connection — only a non-owning role's grant can be
 * relied on, since GRANT requires ownership, superuser, or an existing
 * grant option, none of which such a role has.
 */
class SecureAuditLogCommand extends Command
{
    protected $signature = 'consent-guard:secure-audit-log
        {--role= : The Postgres role the application connects as at runtime (defaults to the audit connection\'s configured username)}
        {--owner-connection= : The Laravel database connection to run this through; must own the audit log table and be a different role than --role}';

    protected $description = "Revoke UPDATE/DELETE on the audit log table from the application's runtime role, enforced by Postgres itself.";

    public function handle(): int
    {
        $ownerConnectionName = $this->option('owner-connection')
            ?? config('consent-guard.audit_log.owner_connection');

        if (! $ownerConnectionName) {
            $this->components->error(
                'No owner connection configured. Pass --owner-connection or set '.
                'consent-guard.audit_log.owner_connection to a Laravel database connection '.
                'authenticated as the role that owns the audit log table.'
            );

            return self::FAILURE;
        }

        $appConnectionName = config('consent-guard.audit_log.connection');
        $role = $this->option('role')
            ?? config("database.connections.{$appConnectionName}.username");

        if (! $role) {
            $this->components->error('Could not determine the runtime role to restrict. Pass --role explicitly.');

            return self::FAILURE;
        }

        $owner = DB::connection($ownerConnectionName);
        $currentRole = $owner->selectOne('SELECT current_user AS role')->role;

        if ($currentRole === $role) {
            $this->components->error(
                "Refusing to proceed: the owner connection ('{$ownerConnectionName}') is itself connected as ".
                "'{$role}', the same role this command is trying to restrict. A table owner can GRANT privileges ".
                'back to itself at will, which would silently defeat this protection. Run this command via a '.
                'connection authenticated as a genuinely different Postgres role that owns the audit log table '.
                '(typically the role your migrations already run as).'
            );

            return self::FAILURE;
        }

        $table = (string) config('consent-guard.audit_log.table', 'audit_log_entries');
        $sequence = $table.'_sequence_seq';

        $owner->statement(sprintf('GRANT SELECT, INSERT ON %s TO %s', $this->quoteIdent($table), $this->quoteIdent($role)));
        $owner->statement(sprintf('REVOKE UPDATE, DELETE ON %s FROM %s', $this->quoteIdent($table), $this->quoteIdent($role)));
        $owner->statement(sprintf('GRANT USAGE, SELECT ON SEQUENCE %s TO %s', $this->quoteIdent($sequence), $this->quoteIdent($role)));

        $this->components->info("Privilege separation applied: '{$role}' now has SELECT/INSERT only on \"{$table}\" — UPDATE/DELETE revoked at the database level.");

        return self::SUCCESS;
    }

    private function quoteIdent(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
