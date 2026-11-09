<?php

namespace App\Console\Commands;

use App\Domain\Admin\Support\AdminGrantInspector;
use Illuminate\Console\Command;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleInputException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Read-only audit of admin grants (owner-approved 2026-11-08). Never inserts, updates, deletes, syncs or
 * clears anything and never runs a seeder — only SELECT statements through AdminGrantInspector, so it can run
 * with read-only database credentials (SELECT on the Spatie tables, `nuke_authors(id, uid, aid, radminsuper)`
 * and, for forum-guard grants, the forum `user.userid`).
 *
 * Confirmed findings (exit 1): admin role / direct-permission assignments whose `model_id` is no longer a
 * `nuke_authors.id`; assignments on an unexpected model type; forum-guard assignments whose forum user is
 * missing; duplicate usernames (as the database collation compares them); duplicate non-zero forum uids.
 *
 * Informational (no effect on the exit code): `super-admin` role holders; drift between the legacy
 * `radminsuper` flag and the Spatie `super-admin` role (Laravel authorises by the role); admins holding grants
 * with `uid = 0` (weak identity).
 *
 * Not detectable: whether an admin row's identity changed over time (a reused id, a renamed `aid`) — there is
 * no historical identity data, so such cases are reported only when they surface as one of the above.
 *
 * Exit codes: 0 = no confirmed findings and every check ran; 1 = confirmed findings (takes precedence);
 * 2 = incomplete verification (a check could not run); 64 = usage error.
 */
class AuditAdminGrants extends Command
{
    public const EXIT_CLEAN = 0;

    public const EXIT_FINDINGS = 1;

    public const EXIT_INCOMPLETE = 2;

    public const EXIT_USAGE = 64;

    protected $signature = 'admin:audit-grants {--json : Machine-readable JSON output}';

    protected $description = 'Read-only audit of AdminCP / forum grants: orphans, unexpected model types, duplicate identities, super-admin holders';

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ConsoleInputException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return self::EXIT_USAGE;
        }
    }

    public function handle(AdminGrantInspector $inspector): int
    {
        $confirmed = [];
        $informational = [];
        $incomplete = [];

        $admins = $this->attempt($incomplete, 'admin rows (main.nuke_authors)', fn () => $inspector->admins());
        $roles = $this->attempt($incomplete, 'role assignments (model_has_roles)', fn () => $inspector->assignments('roles'));
        $permissions = $this->attempt($incomplete, 'direct permissions (model_has_permissions)', fn () => $inspector->assignments('permissions'));

        $adminType = AdminGrantInspector::adminMorphClass();
        $forumType = AdminGrantInspector::forumMorphClass();

        if ($admins !== null && $roles !== null && $permissions !== null) {
            $adminById = [];
            foreach ($admins as $admin) {
                $adminById[$admin['id']] = $admin;
            }

            foreach (['roles' => $roles, 'permissions' => $permissions] as $kind => $rows) {
                foreach ($rows as $row) {
                    if ($row['model_type'] === $adminType && ! isset($adminById[$row['model_id']])) {
                        $confirmed[$kind === 'roles' ? 'orphan_admin_roles' : 'orphan_admin_permissions'][] = $row;
                    } elseif ($row['model_type'] !== $adminType && $row['model_type'] !== $forumType) {
                        $confirmed['unexpected_model_types'][] = $row + ['table' => $kind === 'roles' ? 'model_has_roles' : 'model_has_permissions'];
                    }
                }
            }

            // Super-admin holders (admin guard) and radminsuper drift.
            $superAdminIds = [];
            foreach ($roles as $row) {
                if ($row['model_type'] === $adminType && $row['name'] === 'super-admin' && isset($adminById[$row['model_id']])) {
                    $superAdminIds[$row['model_id']] = true;
                }
            }
            foreach (array_keys($superAdminIds) as $id) {
                $informational['super_admin_holders'][] = $this->identity($adminById[$id]);
            }
            foreach ($adminById as $id => $admin) {
                $hasRole = isset($superAdminIds[$id]);
                if ($hasRole !== ($admin['radminsuper'] === 1)) {
                    $informational['radminsuper_role_drift'][] = $this->identity($admin) + ['super_admin_role' => $hasRole];
                }
            }

            // Weak identity: admins with grants but no forum uid.
            $withGrants = [];
            foreach (array_merge($roles, $permissions) as $row) {
                if ($row['model_type'] === $adminType && isset($adminById[$row['model_id']])) {
                    $withGrants[$row['model_id']] = true;
                }
            }
            foreach (array_keys($withGrants) as $id) {
                if ($adminById[$id]['uid'] === 0) {
                    $informational['weak_identity_uid_zero'][] = $this->identity($adminById[$id]);
                }
            }

            // Forum-guard grants: the forum user must still exist.
            $forumIds = [];
            foreach (array_merge($roles, $permissions) as $row) {
                if ($row['model_type'] === $forumType) {
                    $forumIds[$row['model_id']] = true;
                }
            }
            if ($forumIds !== []) {
                $existing = $this->attempt($incomplete, 'forum users for forum-guard grants (vbulletin.user)', fn () => $inspector->existingForumUserIds(array_keys($forumIds)));
                if ($existing !== null) {
                    foreach (array_diff(array_keys($forumIds), $existing) as $missing) {
                        $confirmed['forum_grants_missing_user'][] = ['model_id' => (int) $missing];
                    }
                }
            }
        }

        $duplicateNames = $this->attempt($incomplete, 'duplicate usernames (nuke_authors.aid)', fn () => $inspector->duplicateUsernames());
        if ($duplicateNames) {
            $confirmed['duplicate_usernames'] = $duplicateNames;
        }
        $duplicateUids = $this->attempt($incomplete, 'duplicate forum uids (nuke_authors.uid)', fn () => $inspector->duplicateForumIds());
        if ($duplicateUids) {
            $confirmed['duplicate_forum_uids'] = $duplicateUids;
        }

        $exit = $confirmed !== [] ? self::EXIT_FINDINGS : ($incomplete !== [] ? self::EXIT_INCOMPLETE : self::EXIT_CLEAN);
        $note = 'Historical identity changes (a reused admin id, a renamed aid) cannot be detected: no historical identity data exists. Nothing was modified.';

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'status' => [self::EXIT_CLEAN => 'clean', self::EXIT_FINDINGS => 'findings', self::EXIT_INCOMPLETE => 'incomplete'][$exit],
                'exit_code' => $exit,
                'confirmed' => (object) $confirmed,
                'informational' => (object) $informational,
                'incomplete' => $incomplete,
                'note' => $note,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $exit;
        }

        $this->renderHuman($confirmed, $informational, $incomplete, $note, $exit);

        return $exit;
    }

    /**
     * @template T
     *
     * @param  list<string>  $incomplete
     * @param  callable(): T  $check
     * @return T|null
     */
    private function attempt(array &$incomplete, string $label, callable $check): mixed
    {
        try {
            return $check();
        } catch (Throwable $e) {
            $incomplete[] = $label.': could not be read ('.$e::class.')';

            return null;
        }
    }

    /** @param array{id: int, uid: int, aid: string, radminsuper: int} $admin */
    private function identity(array $admin): array
    {
        return ['id' => $admin['id'], 'aid' => $admin['aid'], 'uid' => $admin['uid'], 'radminsuper' => $admin['radminsuper']];
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $confirmed
     * @param  array<string, list<array<string, mixed>>>  $informational
     * @param  list<string>  $incomplete
     */
    private function renderHuman(array $confirmed, array $informational, array $incomplete, string $note, int $exit): void
    {
        $this->line('Admin grant audit (read-only)');
        $this->newLine();

        $this->line('Confirmed findings:');
        if ($confirmed === []) {
            $this->line('  none');
        }
        foreach ($confirmed as $category => $rows) {
            $this->line("  {$category}: ".count($rows));
            $this->table(array_keys($rows[0]), array_map(fn ($r) => array_map(fn ($v) => is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v, $r), $rows));
        }

        $this->newLine();
        $this->line('Informational:');
        if ($informational === []) {
            $this->line('  none');
        }
        foreach ($informational as $category => $rows) {
            $this->line("  {$category}: ".count($rows));
            $this->table(array_keys($rows[0]), array_map(fn ($r) => array_map(fn ($v) => is_bool($v) ? ($v ? 'yes' : 'no') : (string) $v, $r), $rows));
        }

        if ($incomplete !== []) {
            $this->newLine();
            $this->line('Incomplete verification:');
            foreach ($incomplete as $message) {
                $this->line('  - '.$message);
            }
        }

        $this->newLine();
        $this->line($note);
        $this->line('Exit code: '.$exit);
    }
}
