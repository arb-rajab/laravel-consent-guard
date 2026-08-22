<?php

declare(strict_types=1);

namespace ArbRajab\ConsentGuard\AuditLog;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only by construction: save()/delete() are overridden so nothing can
 * bypass AuditLogger::record() and write an un-chained or edited entry
 * directly through the model. This blocks accidental misuse from this
 * package's own PHP layer — it is not the tamper-evidence guarantee itself.
 * The guarantee that a compromised or misbehaving *application* runtime
 * connection cannot edit history even via raw SQL is enforced by Postgres
 * itself, via consent-guard:secure-audit-log revoking UPDATE/DELETE at the
 * database level. Both layers matter; neither alone is sufficient (an owner
 * role can always GRANT itself back the privilege this model refuses to use).
 *
 * @property string $id
 * @property int $sequence
 * @property string|null $actor_type
 * @property string|null $actor_id
 * @property string $action
 * @property string $subject_type
 * @property string $subject_id
 * @property array<string, mixed>|null $metadata
 * @property string|null $prev_hash
 * @property string $entry_hash
 * @property Carbon $created_at
 */
class AuditLogEntry extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'sequence',
        'actor_type',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'prev_hash',
        'entry_hash',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return (string) config('consent-guard.audit_log.table', 'audit_log_entries');
    }

    public function getConnectionName(): ?string
    {
        return config('consent-guard.audit_log.connection');
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('AuditLogEntry rows are append-only; they cannot be modified after creation.');
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('AuditLogEntry rows cannot be deleted; the audit trail is retained indefinitely by design.');
    }
}
