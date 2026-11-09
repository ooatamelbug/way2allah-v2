<?php

namespace App\Domain\Admin\Actions\Mobile;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Content\Models\Mobile\MobileMaterial;
use Illuminate\Support\Facades\DB;

/**
 * OLD Admin Migration Phase 7 — the staff review of a material. Replaces OLD
 * `DownloadsAddEditorial`, `DownloadsModEditorial`, `DownloadsDelEditorial`:
 * at most one per material; adding records the admin (`adminid` = aid) and
 * the time; editing changes title and text only.
 */
final class MobileEditorialAdmin extends MobileAdminAction
{
    public function save(AdminUser $actor, MobileMaterial $material, string $title, string $text, ?string $ip = null): bool
    {
        $this->authorize($actor, $material);

        return DB::connection('main')->transaction(function () use ($actor, $material, $title, $text, $ip) {
            $main = DB::connection('main');
            $lid = (int) $material->getKey();
            abort_unless($main->table('nuke_downloads_downloads')->where('lid', $lid)->exists(), 404);
            $existing = $main->table('nuke_downloads_editorials')->where('downloadid', $lid)->lockForUpdate()->first();

            if ($existing === null) {
                $main->table('nuke_downloads_editorials')->insert([
                    'downloadid' => $lid, 'adminid' => (string) $actor->aid, 'editorialtimestamp' => date('Y-m-d H:i:s'),
                    'editorialtext' => $text, 'editorialtitle' => $title,
                ]);
                $this->record($actor, 'add_mobile_editorial', 'mobile_material', $lid, self::describe(['editorialtitle' => $title, 'editorialtext' => $text]), $ip);

                return true;
            }

            if ((string) $existing->editorialtitle === $title && (string) $existing->editorialtext === $text) {
                return false;
            }
            $main->table('nuke_downloads_editorials')->where('downloadid', $lid)->update(['editorialtitle' => $title, 'editorialtext' => $text]);
            $this->record($actor, 'edit_mobile_editorial', 'mobile_material', $lid, [
                'before' => self::describe(['editorialtitle' => $existing->editorialtitle, 'editorialtext' => $existing->editorialtext]),
                'after' => self::describe(['editorialtitle' => $title, 'editorialtext' => $text]),
            ], $ip);

            return true;
        });
    }

    public function delete(AdminUser $actor, MobileMaterial $material, ?string $ip = null): bool
    {
        $this->authorize($actor, $material);

        return DB::connection('main')->transaction(function () use ($actor, $material, $ip) {
            $main = DB::connection('main');
            $existing = $main->table('nuke_downloads_editorials')->where('downloadid', $material->getKey())->lockForUpdate()->first();
            if ($existing === null) {
                return false;
            }

            $main->table('nuke_downloads_editorials')->where('downloadid', $existing->downloadid)->delete();
            $this->record($actor, 'del_mobile_editorial', 'mobile_material', (int) $existing->downloadid, [
                'deleted' => self::describe(['editorialtitle' => $existing->editorialtitle, 'editorialtext' => $existing->editorialtext]) + ['adminid' => self::text($existing->adminid)],
            ], $ip);

            return true;
        });
    }
}
