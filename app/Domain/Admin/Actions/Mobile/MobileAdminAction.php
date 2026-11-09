<?php

namespace App\Domain\Admin\Actions\Mobile;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;

/**
 * Phase 7 — shared plumbing of the Mobile Downloads staff actions: one
 * permission (`mobile.managedownloads`, OLD's single module-level gate),
 * the Phase 0 recorder, and markup-safe text for audit metadata.
 */
abstract class MobileAdminAction
{
    public const PERMISSION = 'mobile.managedownloads';

    public function __construct(
        protected readonly AdminRecordAuthorizer $authorizer,
        protected readonly AdminAuditRecorder $audit,
    ) {}

    protected function authorize(AdminUser $actor, ?object $record = null): void
    {
        $this->authorizer->authorize($actor, self::PERMISSION, $record);
    }

    /** @param array<string, mixed> $metadata */
    protected function record(AdminUser $actor, string $action, string $entity, int $id, array $metadata, ?string $ip): void
    {
        $this->audit->record($actor, $action, 'mobile', $entity, $id, $metadata, $ip);
    }

    /** Short text for audit metadata; markup-looking values become a marker. */
    protected static function text(?string $value): string|array
    {
        $value = (string) $value;
        if (preg_match('/<[a-z!\/][^>]*>/i', $value) === 1) {
            return ['redacted' => 'markup', 'length' => mb_strlen($value)];
        }

        return mb_strlen($value) > 200 ? ['truncated' => mb_substr($value, 0, 200), 'length' => mb_strlen($value)] : $value;
    }

    /**
     * Material fields for audit metadata: identifiers and short values only;
     * the description is fingerprinted, never copied.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected static function describe(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            $out[$key] = match ($key) {
                'description', 'cdescription', 'editorialtext' => ['sha256' => hash('sha256', (string) $value), 'length' => mb_strlen((string) $value)],
                'cid', 'parentid', 'hits', 'filesize' => (int) $value,
                default => static::text(is_scalar($value) ? (string) $value : ''),
            };
        }

        return $out;
    }
}
