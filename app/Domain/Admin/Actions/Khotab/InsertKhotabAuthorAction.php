<?php

namespace App\Domain\Admin\Actions\Khotab;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Support\AdminAuditRecorder;
use App\Domain\Admin\Support\AdminRecordAuthorizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «أضـــف شـــيـــخ» on Khotab Main — OLD `KhotabInsertAuthor` →
 * `ConfirmInsertAuthor` (`modules/Khotab/admin/index.php:2303-2355`,
 * production working copy).
 *
 * OLD refused a name already used together with the same title
 * («عفواً .... لن تتمكن من إضافة …»); the same name under another title is a
 * new author. Otherwise it inserted name, prename, description,
 * meta_keywords, meta_description, meta_index, meta_follow, block, time and
 * des — every counter and `hidden` keep their default 0 — and regenerated
 * the authors autocomplete list ({@see KhotabAuthorsAutocomplete}).
 *
 * Corrections (owner, 2026-10-21): OLD then called `$db->sql_nextid()`, which
 * production's MySQL driver (`db/mysql_modern.php`, owner-verified checksum)
 * does not define — a fatal error after the insert, so the list refresh, the
 * log and the redirect never ran. Here they do. Bound parameters replace
 * OLD's raw SQL, so a name with an apostrophe is stored instead of failing.
 * No `nuke_islamic_letters` recount (absent in production). Authorized
 * (`khotab.addauthor`; OLD's `GreenZone('addauthor')` was inert), one
 * transaction, audited. OLD's image field was never read and is not offered.
 */
final class InsertKhotabAuthorAction
{
    public function __construct(
        private readonly AdminRecordAuthorizer $authorizer,
        private readonly AdminAuditRecorder $audit,
        private readonly KhotabAuthorsAutocomplete $autocomplete,
    ) {}

    /**
     * @param  array<string, mixed>  $data  the validated form, OLD field names
     *
     * @throws ValidationException OLD's duplicate refusal
     */
    public function execute(AdminUser $actor, array $data, ?string $ip = null): int
    {
        $this->authorizer->authorize($actor, 'khotab.addauthor');

        $name = (string) ($data['Name'] ?? '');
        $prename = (string) ($data['PreName'] ?? '');

        $id = DB::connection('main')->transaction(function () use ($actor, $data, $name, $prename, $ip) {
            $main = DB::connection('main');

            if ($main->table('nuke_islamic_authors')->where('name', $name)->where('prename', $prename)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['Name' => "عفواً .... لن تتمكن من إضافة '' {$prename} {$name} '' إلى قاعدة بيانات الموقع - يوجد بالموقع نفس البيانات الجديدة التى حاولت إدخالها"]);
            }

            $id = (int) $main->table('nuke_islamic_authors')->insertGetId([
                'name' => $name,
                'prename' => $prename,
                'description' => (string) ($data['description'] ?? ''),
                'meta_keywords' => (string) ($data['meta_keywords'] ?? ''),
                'meta_description' => (string) ($data['meta_description'] ?? ''),
                'meta_index' => empty($data['meta_index']) ? 0 : 1,
                'meta_follow' => empty($data['meta_follow']) ? 0 : 1,
                'block' => (string) ($data['block'] ?? ''),
                'time' => time(),
                'des' => (string) ($data['Des'] ?? ''),
            ]);

            $this->audit->record($actor, 'add_author', 'khotab', 'author', $id, [
                'name' => AuditText::digest(trim($prename.' '.$name)),
            ], $ip);

            return $id;
        });

        $this->autocomplete->refresh();

        return $id;
    }
}
