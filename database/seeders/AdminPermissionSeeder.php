<?php

namespace Database\Seeders;

use App\Support\Permission\Permission;
use Illuminate\Database\Seeder;

/**
 * Wave 5, decision-log #9 — one Spatie `Permission` per legacy
 * `admincp/*\/menu.php`'s `$authorization[$module]` key, namespaced
 * `{module}.{key}`. Source: every `menu.php` in the 9 real feature
 * directories, read directly (not inferred). `khotab`/`telawah`'s real
 * content-CRUD authorization keys (~30 of them, `khotab/menu.php`) are
 * excluded — that content-CRUD doesn't exist in this codebase (Business
 * Confirmation #6); `forumConfig` excluded (Blueprint §18 "Never").
 *
 * `khotab.uploaders` (task 5.9) is the one deliberate exception/addition:
 * a real, in-scope capability (`khotab/uploader(s).php`, Upload-Team
 * Tracking) that has no genuine match among `khotab/menu.php`'s 30
 * content-CRUD keys — none of them was ever actually wired to gate this
 * page (same "permission key defined, no corresponding real gate" shape
 * already confirmed for `khotab/`'s other permission keys, Pattern B).
 * Added as its own permission rather than force-fitting an unrelated
 * existing key or leaving this real capability permanently ungated.
 */
class AdminPermissionSeeder extends Seeder
{
    /** @var array<string, list<string>> module => authorization keys, per that module's own menu.php */
    private const MODULE_KEYS = [
        'survey' => ['modsurvey', 'modquestion', 'modanalysis'],
        'chat' => ['listrooms', 'editroom', 'deleteroom', 'listroom'],
        'authors' => ['addstuff', 'editstuff', 'deletestuff', 'liststuff'],
        'backup' => ['allsite', 'backupkhotab', 'backupkhotabmirror', 'backuptelawah', 'backupanasheed', 'backupanasheedmirror'],
        'locations' => ['add_location', 'del_location'],
        'soundcloud' => ['update_soundcloud'],
        'youtube' => ['update_youtube'],
        'questionnaire' => ['listquest', 'deletequest', 'listallquest'],
        'broadcasting' => ['addstream', 'editstream'],
        'khotab' => ['uploaders'],
    ];

    public function run(): void
    {
        foreach (self::MODULE_KEYS as $module => $keys) {
            foreach ($keys as $key) {
                Permission::firstOrCreate(['name' => "{$module}.{$key}", 'guard_name' => 'admin']);
            }
        }
    }
}
