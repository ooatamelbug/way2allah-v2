<?php

namespace Tests\Support;

use App\Domain\Admin\Support\Constraints\KhotabMediaScopeConstraint;
use App\Support\Permission\Permission;

/**
 * Khotab audio / video scope (2026-11-17) in test fixtures: an admin given a section-scoped Khotab key and no explicit
 * section is a both-sections moderator — exactly what every test written before the scope existed assumed. A test
 * that passes `khotab.media_audio` / `khotab.media_video` itself gets only what it passed.
 */
final class KhotabMediaScope
{
    /** @param list<string> $permissions @return list<string> */
    public static function withBothSectionsIfUnspecified(array $permissions): array
    {
        $sections = [KhotabMediaScopeConstraint::AUDIO, KhotabMediaScopeConstraint::VIDEO];
        if (array_intersect($permissions, KhotabMediaScopeConstraint::SCOPED) === [] || array_intersect($permissions, $sections) !== []) {
            return $permissions;
        }
        foreach ($sections as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'admin']);
        }

        return array_values(array_merge($permissions, $sections));
    }
}
