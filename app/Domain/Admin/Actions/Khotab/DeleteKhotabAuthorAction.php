<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use App\Domain\Content\Models\Author;
use Illuminate\Support\Facades\DB;

/**
 * «حذف الداعية» — OLD `KhotabDeleteAuthor` → `ConfirmDeleteAuthor`
 * (`modules/Khotab/admin/index.php:2486-2552`, production working copy).
 *
 * OLD refused while the author still had any item (`author = X`, hidden ones
 * included) and wrote nothing. Otherwise it deleted the author row and nothing
 * else — the author's groups and series stay — then regenerated the authors
 * autocomplete list. Its `nuke_islamic_letters` recount is not reproduced
 * (the table does not exist in production, owner-verified 2026-10-21).
 *
 * Corrections: authorized (`khotab.delauthor`; OLD's `GreenZone('delauthor')`
 * was inert), the check and the delete in one locked transaction, audited.
 */
final class DeleteKhotabAuthorAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabAuthorsAutocomplete $autocomplete,
    ) {}

    /** @return array{deleted: bool, items: int} */
    public function execute(AdminUser $actor, Author $author, ?string $ip = null): array
    {
        $this->authorizer->authorize($actor, 'khotab.delauthor', $author);

        $result = DB::connection('main')->transaction(function () use ($actor, $author, $ip) {
            $main = DB::connection('main');
            $row = $main->table('nuke_islamic_authors')->where('id', $author->id)->lockForUpdate()->first(['id', 'name', 'prename']);
            abort_if($row === null, 404);

            $items = $main->table('nuke_islamic_khotab')->where('author', $row->id)->count();
            if ($items > 0) {
                return ['deleted' => false, 'items' => $items];
            }

            $main->table('nuke_islamic_authors')->where('id', $row->id)->delete();

            $this->audit->record($actor, 'del_author', 'khotab', 'author', (int) $row->id, [
                'name' => AuditText::digest(trim($row->prename.' '.$row->name)),
            ], $ip);

            return ['deleted' => true, 'items' => 0];
        });

        if ($result['deleted']) {
            $this->autocomplete->refresh();
        }

        return $result;
    }
}
