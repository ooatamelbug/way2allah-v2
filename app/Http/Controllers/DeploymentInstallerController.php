<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

/**
 * cPanel No-SSH Deployment Repackaging — a browser-driven, one-time
 * equivalent of the CLI deployment sequence
 * (`migrate --force` / `RoleSeeder` / `AdminPermissionSeeder` /
 * `admin:sync-permissions` / cache commands) for an owner whose cPanel
 * account has no SSH, no Terminal, and no way to run `php artisan`
 * manually. This is deployment infrastructure, not an application
 * feature — it exists to be used exactly once per deployment and then
 * permanently disabled (see `isLocked()`).
 *
 * Defense in depth, all independently required (§6/§7 of the owner's
 * spec):
 * 1. `DEPLOY_INSTALLER_ENABLED=true` in `.env` — off by default in
 *    `.env.production.example`; the owner must deliberately opt in.
 * 2. `DEPLOY_INSTALLER_TOKEN` — a long random secret, compared with
 *    `hash_equals()` (constant-time, not `===`) so a timing attack can't
 *    narrow it down character-by-character. Never logged, never echoed
 *    back, never included in any packaged ZIP (the real value only ever
 *    exists in the owner's own `.env`).
 * 3. The lock file (`storage/app/deployment-installed.lock`) — created
 *    only after every step succeeds. Once it exists, both `show()` and
 *    `install()` return 410 Gone unconditionally, before even checking
 *    the token — a stolen/leaked token is useless post-lock.
 * 4. The hardcoded step sequence — no arbitrary Artisan command can be
 *    requested through this endpoint; `install()` calls exactly 8 named
 *    operations, always in this order, nothing else.
 *
 * Idempotency: every step here is safe to re-run from scratch after a
 * partial failure — `migrate` skips already-applied migrations (tracked
 * in its own table), both seeders use `firstOrCreate` exclusively (no
 * bare `::create()` calls, verified), `admin:sync-permissions` is
 * already idempotent by design (decision-log #32), and the cache
 * commands simply overwrite. No step here depends on a previous run
 * having reached any particular point.
 */
class DeploymentInstallerController extends Controller
{
    private const LOCK_RELATIVE_PATH = 'deployment-installed.lock';

    /** @var list<array{key: string, label: string}> */
    private const STEPS = [
        ['key' => 'db', 'label' => 'التحقق من الاتصال بقواعد البيانات'],
        ['key' => 'migrate', 'label' => 'تشغيل الترحيلات (migrations)'],
        ['key' => 'roles', 'label' => 'تهيئة الأدوار (RoleSeeder)'],
        ['key' => 'permissions', 'label' => 'تهيئة صلاحيات لوحة التحكم (AdminPermissionSeeder)'],
        ['key' => 'sync', 'label' => 'مزامنة صلاحيات المشرفين الحقيقيين (admin:sync-permissions)'],
        ['key' => 'optimize_clear', 'label' => 'تفريغ أي ذاكرة تخزين مؤقت قديمة'],
        ['key' => 'config_cache', 'label' => 'تخزين الإعدادات مؤقتًا (config:cache)'],
        ['key' => 'route_cache', 'label' => 'تخزين المسارات مؤقتًا (route:cache)'],
        ['key' => 'view_cache', 'label' => 'تخزين القوالب مؤقتًا (view:cache)'],
    ];

    public function show(Request $request): View|RedirectResponse
    {
        $this->abortIfUnavailable();

        return view('deploy.install');
    }

    public function install(Request $request): View
    {
        $this->abortIfUnavailable();

        $providedToken = (string) $request->input('token', '');
        $realToken = (string) config('deploy.installer_token', '');

        // An empty configured token can never "match" — closes the
        // otherwise-plausible empty-vs-empty bypass.
        if ($realToken === '' || $providedToken === '' || ! hash_equals($realToken, $providedToken)) {
            return view('deploy.install', [
                'tokenError' => 'رمز التثبيت غير صحيح. تحقق من DEPLOY_INSTALLER_TOKEN في ملف .env.',
            ]);
        }

        $results = [];
        $failedAt = null;

        foreach (self::STEPS as $step) {
            if ($failedAt !== null) {
                $results[] = ['label' => $step['label'], 'status' => 'skipped'];

                continue;
            }

            try {
                $this->runStep($step['key']);
                $results[] = ['label' => $step['label'], 'status' => 'ok'];
            } catch (Throwable $e) {
                // The real exception (which can legitimately contain a DSN,
                // a table name, or other server-internal detail) is logged
                // server-side only — never rendered into the HTTP response.
                Log::error("Deployment installer step '{$step['key']}' failed", [
                    'exception' => $e::class,
                ]);
                $results[] = ['label' => $step['label'], 'status' => 'failed'];
                $failedAt = $step['key'];
            }
        }

        if ($failedAt === null) {
            Storage::disk('local')->put(self::LOCK_RELATIVE_PATH, now()->toIso8601String()."\n");
        }

        return view('deploy.install', [
            'results' => $results,
            'succeeded' => $failedAt === null,
        ]);
    }

    private function runStep(string $key): void
    {
        match ($key) {
            'db' => $this->checkDatabaseConnections(),
            'migrate' => Artisan::call('migrate', ['--force' => true]),
            'roles' => Artisan::call('db:seed', ['--force' => true, '--class' => 'Database\\Seeders\\RoleSeeder']),
            'permissions' => Artisan::call('db:seed', ['--force' => true, '--class' => 'Database\\Seeders\\AdminPermissionSeeder']),
            // No --force here — the real command has no such flag (verified
            // directly against the console signature; it is not a
            // ConfirmableTrait command and never prompts).
            'sync' => Artisan::call('admin:sync-permissions'),
            'optimize_clear' => Artisan::call('optimize:clear'),
            'config_cache' => Artisan::call('config:cache'),
            'route_cache' => Artisan::call('route:cache'),
            'view_cache' => Artisan::call('view:cache'),
            default => throw new \LogicException("Unknown deployment step: {$key}"),
        };
    }

    /**
     * The 'main' connection (real legacy content) must be reachable for
     * this deployment to mean anything; 'vbulletin'/'flashchat' are
     * allowed to be unreachable (already-established graceful-degradation
     * behavior, e.g. RoleSeeder's own handling) so they are not checked
     * here — only the connection every other step actually depends on.
     */
    private function checkDatabaseConnections(): void
    {
        config(['database.default' => config('database.default')]);
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        \Illuminate\Support\Facades\DB::connection('main')->getPdo();
    }

    private function abortIfUnavailable(): void
    {
        if ($this->isLocked()) {
            abort(410, 'تم تعطيل هذه الأداة بشكل نهائي بعد اكتمال التثبيت.');
        }

        if (! config('deploy.installer_enabled', false)) {
            abort(404);
        }
    }

    private function isLocked(): bool
    {
        return Storage::disk('local')->exists(self::LOCK_RELATIVE_PATH);
    }
}
