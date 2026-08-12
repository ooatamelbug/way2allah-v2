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
 * `khotab.uploaders` (task 5.9) is one deliberate exception/addition:
 * a real, in-scope capability (`khotab/uploader(s).php`, Upload-Team
 * Tracking) that has no genuine match among `khotab/menu.php`'s 30
 * content-CRUD keys — none of them was ever actually wired to gate this
 * page (same "permission key defined, no corresponding real gate" shape
 * already confirmed for `khotab/`'s other permission keys, Pattern B).
 * Added as its own permission rather than force-fitting an unrelated
 * existing key or leaving this real capability permanently ungated.
 *
 * `khotab.repair`/`telawah.repair` (task 6.7) are a second exception,
 * for the mirror/link-quality stats-and-repair tool (`stats.php`,
 * `stats_khotab.php`, `stats_khotab_200mb.php`, `telawah/stats.php`) —
 * unlike the CRUD keys, this real capability doesn't need Business
 * Confirmation #6 (it's a straightforward port of already-working
 * legacy logic, not a "design fresh vs. reference" question).
 * `khotab.repair` is a genuine match, un-excluded from the otherwise-
 * excluded 30-key `khotab/menu.php` array specifically because it's
 * real and in-scope. `telawah/menu.php`'s own small array (4 keys, all
 * author-account-shaped) has no fitting match at all, so `telawah.repair`
 * is minted fresh, by direct analogy to `khotab.repair` — same shape as
 * the `khotab.uploaders` precedent above.
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
        'khotab' => ['uploaders', 'repair'],
        'telawah' => ['repair'],
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
