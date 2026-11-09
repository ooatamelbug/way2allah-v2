<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Author;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «إضافة مجموعة» on an author page — OLD `KhotabAddGroup` → `ConfirmAddGroup`
 * (`modules/Khotab/admin/index.php:2581-2709`, production working copy).
 *
 * OLD refused a title already used by a group of the same author and media
 * type («عفواً .... لن تتمكن من إضافة مجموعة …»). Otherwise it inserted
 * author_id, title, description, meta_keywords, meta_description,
 * meta_index, meta_follow, vedio, channel_id (the posted channel, not checked
 * against `nuke_sat_channels`) and des; every other column — `count`
 * included — keeps its default 0.
 *
 * Corrections: authorized (`khotab.addgroup`; OLD's `GreenZone('addgroup')`
 * was inert), the duplicate check and the insert in one transaction, the id
 * taken from the insert (OLD re-selected by title), audited.
 */
final class InsertKhotabAuthorGroupAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated form, OLD field names
     *
     * @throws ValidationException OLD's duplicate-title refusal
     */
    public function execute(AdminUser $actor, Author $author, int $vedio, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.addgroup', $author);

        return DB::connection('main')->transaction(function () use ($actor, $author, $vedio, $data, $ip) {
            $main = DB::connection('main');
            $authorId = (int) $main->table('nuke_islamic_authors')->where('id', $author->id)->lockForUpdate()->value('id');
            abort_if($authorId < 1, 404);

            $title = (string) ($data['Title'] ?? '');
            if ($main->table('nuke_islamic_groups')->where('title', $title)->where('author_id', $authorId)->where('vedio', $vedio)->exists()) {
                throw ValidationException::withMessages(['Title' => "عفواً .... لن تتمكن من إضافة مجموعة '' {$title} '' إلى قاعدة بيانات الموقع - يوجد بالموقع نفس البيانات الجديدة التى حاولت إدخالها"]);
            }

            $channel = (int) ($data['Channel'] ?? 0);
            $id = (int) $main->table('nuke_islamic_groups')->insertGetId([
                'author_id' => $authorId,
                'title' => $title,
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'vedio' => $vedio,
                'channel_id' => $channel,
                'des' => (string) ($data['Des'] ?? ''),
            ]);

            $this->audit->record($actor, 'add_group', 'khotab', 'group', $id, [
                'author_id' => $authorId,
                'vedio' => $vedio,
                'channel_id' => $channel,
                'title' => AuditText::digest($title),
            ], $ip);

            return $id;
        });
    }
}
