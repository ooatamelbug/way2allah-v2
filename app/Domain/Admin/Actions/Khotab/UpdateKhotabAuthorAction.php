<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Author;
use Illuminate\Support\Facades\DB;

/**
 * «تعديل بيانات الداعية» — OLD `KhotabEditAuthor` → `ConfirmEditAuthor`
 * (`modules/Khotab/admin/index.php:2357-2484`, production working copy).
 *
 * OLD's writes: name, prename, description, meta_keywords, meta_description,
 * meta_index, meta_follow, block, hidden (`C1`) and des; then the authors
 * autocomplete list is regenerated ({@see KhotabAuthorsAutocomplete}).
 *
 * Not reproduced: OLD's `ListAuthors()` recount of `nuke_islamic_letters` —
 * that table does not exist in the production database (owner-verified,
 * `SHOW TABLES LIKE 'nuke_islamic_letters'` = 0 rows, 2026-10-21).
 *
 * Corrections: authorized (`khotab.editauthor`; OLD's
 * `GreenZone('editauthor')` was inert), one locked transaction, audited with
 * free text digested.
 */
final class UpdateKhotabAuthorAction
{
    private const TEXT = ['name', 'prename', 'description', 'meta_keywords', 'meta_description', 'block', 'des'];

    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabAuthorsAutocomplete $autocomplete,
    ) {}

    /** @param array<string, mixed> $data the validated form, OLD field names */
    public function execute(AdminUser $actor, Author $author, array $data, ?string $ip = null): void
    {
        $this->authorizer->authorize($actor, 'khotab.editauthor', $author);

        DB::connection('main')->transaction(function () use ($actor, $author, $data, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_authors')->where('id', $author->id)->lockForUpdate()->first();
            abort_if($row === null, 404);

            $after = [
                'name' => (string) ($data['Name'] ?? ''),
                'prename' => (string) ($data['PreName'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'block' => (string) ($data['block'] ?? ''),
                'hidden' => empty($data['C1']) ? 0 : 1,
                'des' => (string) ($data['Des'] ?? ''),
            ];

            $before = [];
            foreach (array_keys($after) as $column) {
                $before[$column] = $row->{$column} ?? null;
            }

            $main->table('nuke_islamic_authors')->where('id', $row->id)->update($after);

            $this->audit->record($actor, 'edit_author', 'khotab', 'author', (int) $row->id, [
                'changes' => AuditText::changes($before, $after, self::TEXT),
            ], $ip);
        });

        $this->autocomplete->refresh();
    }
}
