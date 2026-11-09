<?php

namespace App\Domain\Admin\Actions\Advfiles;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\KhotabItem;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 5 — edit a published item's promotional file.
 * Replaces OLD `w2a_edit_advfile('update')` + `ConfirmEditAdvFile()`
 * (`modules/advfiles/admin/index.php:111-128`): `credit`, `code` and
 * `audio_url` are rewritten; the workflow state, the counters and the
 * thumbnail flags are untouched.
 *
 * Requires a published item whose payload row exists — OLD's UPDATE matched
 * nothing otherwise. Only changed fields are written; an unchanged
 * submission writes nothing and records no audit row.
 */
final class UpdateAdvfileAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array{credit: int, code: string, audio_url: string}  $payload
     * @return bool whether anything changed
     */
    public function execute(AdminUser $actor, KhotabItem $item, array $payload, ?string $ip = null): bool
    {
        $this->authorizer->authorize($actor, 'khotab.addadv', $item);

        return DB::connection('main')->transaction(function () use ($actor, $item, $payload, $ip) {
            $main = DB::connection('main');
            $row = AdvfileItemLock::lock($item);

            if (! AdvfileState::isPublished((int) $row->advfile)) {
                throw new AdvfileTransitionRefused('لا يوجد ملف دعائي منشور لهذه المادة');
            }
            $file = $main->table('nuke_islamic_advfile')->where('id', $row->id)->lockForUpdate()->first(['id', 'credit', 'code', 'audio_url']);
            if ($file === null) {
                throw new AdvfileTransitionRefused('المادة منشورة لكن لا توجد بيانات ملف دعائي لتعديلها');
            }

            $current = ['credit' => (int) $file->credit, 'code' => (string) $file->code, 'audio_url' => (string) $file->audio_url];
            $changes = array_filter($payload, fn ($value, $key) => $current[$key] !== $value, ARRAY_FILTER_USE_BOTH);

            if ($changes === []) {
                return false;
            }

            $main->table('nuke_islamic_advfile')->where('id', $row->id)->update($changes);

            $audit = fn (array $values) => array_map(
                fn ($key) => match ($key) {
                    'code' => AdvfileAuditValue::code($values[$key]),
                    'audio_url' => AdvfileAuditValue::text($values[$key]),
                    default => $values[$key],
                },
                array_combine(array_keys($changes), array_keys($changes)),
            );

            $this->audit->record($actor, 'edit_advfile', 'khotab', 'item', (int) $row->id, [
                'before' => $audit($current),
                'after' => $audit($payload),
            ], $ip);

            return true;
        });
    }
}
