<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleInputException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Read-only audit of the five media roots (P0 split layout, owner-approved 2026-11-09).
 *
 * In the split layout (application in `laravel-app/`, web root in `newapp/`) `public_path()` is the web root
 * only for HTTP requests (the front controller calls `usePublicPath(__DIR__)`); Artisan, queue workers and
 * `config:cache` (which builds a fresh application) fall back to `laravel-app/public`. The five roots must
 * therefore be explicit absolute paths in `.env`. This command reports what each root resolves to in the
 * current process and whether the five agree with one `<web root>/{media, images/cds_image2,
 * w2a_autocomplete}` structure.
 *
 * Never creates directories, changes permissions, copies, moves or deletes files, clears caches or runs
 * migrations — only stat-style calls (`file_exists`, `is_dir`, `is_readable`, `is_writable`, `is_link`,
 * `realpath`). Prints no environment variables beyond the five root values.
 *
 * Exit codes: 0 = no confirmed findings; 1 = confirmed findings in this environment; 64 = usage error.
 */
class CheckMediaRoots extends Command
{
    public const EXIT_CLEAN = 0;

    public const EXIT_FINDINGS = 1;

    public const EXIT_USAGE = 64;

    /** config key => [environment variable, path relative to the web root] */
    public const ROOTS = [
        'admin_khotab.media_root' => ['KHOTAB_MEDIA_ROOT', 'media'],
        'admin_anasheed.media_root' => ['ANASHEED_MEDIA_ROOT', 'media'],
        'admin_cds.media_root' => ['W2ACD_MEDIA_ROOT', 'media'],
        'admin_cds.image_root' => ['W2ACD_IMAGE_ROOT', 'images/cds_image2'],
        'media.autocomplete_root' => ['AUTOCOMPLETE_ROOT', 'w2a_autocomplete'],
    ];

    /** What this command cannot see from its own process — always to be verified on the production server. */
    public const PRODUCTION_VERIFICATION = [
        'The web server document root is the web root derived above (e.g. /home/USER/newapp).',
        'The PHP user that serves web requests (not only this CLI user) can read every root and write the AdminCP upload roots.',
        'open_basedir (if set) permits every root and, where a root is a symlink, its canonical target.',
        'After any .env change: php artisan config:clear, then php artisan config:cache, then re-run this command.',
        'Any files found under laravel-app/public/ are misplaced uploads: reconcile them only with owner approval (this command never moves files).',
    ];

    protected $signature = 'media:check-roots
        {--json : Machine-readable JSON output}
        {--expect-web-root= : Absolute web root the five roots must sit under (e.g. /home/USER/newapp)}';

    protected $description = 'Read-only audit of the five configured media roots (split application / web-root layout)';

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::run($input, $output);
        } catch (ConsoleInputException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');

            return self::EXIT_USAGE;
        }
    }

    public function handle(): int
    {
        $expected = $this->option('expect-web-root');
        if ($expected !== null && ! str_starts_with($expected, '/')) {
            $this->error('--expect-web-root must be an absolute path.');

            return self::EXIT_USAGE;
        }
        $expected = $expected !== null ? $this->normalise($expected) : null;

        $findings = [];
        $informational = [];
        $roots = [];
        $webRoots = [];

        foreach (self::ROOTS as $key => [$variable, $relative]) {
            $value = config($key);
            $path = is_string($value) ? $value : '';
            $normalised = $path !== '' ? $this->normalise($path) : '';
            $absolute = str_starts_with($path, '/');
            $exists = $path !== '' && file_exists($path);
            $isDirectory = $exists && is_dir($path);
            $symlink = $normalised !== '' && is_link($normalised);
            $canonical = $exists ? realpath($path) : false;

            $root = [
                'config' => $key,
                'variable' => $variable,
                'path' => $path,
                'absolute' => $absolute,
                'exists' => $exists,
                'directory' => $isDirectory,
                'readable' => $isDirectory && is_readable($path),
                'writable' => $isDirectory && is_writable($path),
                'symlink' => $symlink,
                'canonical' => $canonical === false ? null : $canonical,
            ];
            $roots[] = $root;

            if ($path === '') {
                $findings[] = "{$variable} ({$key}) is not configured.";

                continue;
            }
            if (! $absolute) {
                $findings[] = "{$variable} ({$key}) is not an absolute path.";
            }
            if (! $exists) {
                $findings[] = "{$variable} ({$key}) does not exist.";
            } elseif (! $isDirectory) {
                $findings[] = "{$variable} ({$key}) is not a directory.";
            } else {
                if (! $root['readable']) {
                    $findings[] = "{$variable} ({$key}) is not readable by this process.";
                }
                if (! $root['writable']) {
                    $findings[] = "{$variable} ({$key}) is not writable by this process.";
                }
            }
            if ($symlink) {
                $informational[] = "{$variable} ({$key}) is a symlink to {$root['canonical']} (the no-SSH deployment guide expects real directories).";
            }

            $suffix = '/'.$relative;
            if (str_ends_with($normalised, $suffix)) {
                $webRoots[$variable] = substr($normalised, 0, -strlen($suffix));
            } else {
                $findings[] = "{$variable} ({$key}) does not end in /{$relative}, so it does not follow the <web root>/{$relative} structure.";
            }
        }

        $distinct = array_values(array_unique($webRoots));
        $webRoot = count($distinct) === 1 ? $distinct[0] : null;
        if (count($distinct) > 1) {
            $findings[] = 'The roots disagree on the web root: '.implode(', ', array_map(fn ($v, $r) => "{$v} → {$r}", array_keys($webRoots), $webRoots)).'.';
        }
        if ($expected !== null && $webRoot !== null && $webRoot !== $expected) {
            $findings[] = "The roots sit under {$webRoot}, not the expected web root {$expected}.";
        }

        $appPublic = $this->normalise(base_path('public'));
        $appPublicExists = is_dir($appPublic);
        foreach ($roots as $root) {
            if ($root['path'] !== '' && ! $appPublicExists && str_starts_with($this->normalise($root['path']).'/', $appPublic.'/')) {
                $findings[] = "{$root['variable']} points inside the application's own public/ directory, which does not exist here (split layout): set it explicitly to the web root.";
            }
        }
        if ($appPublicExists && $webRoot !== null && $webRoot !== $appPublic) {
            $informational[] = 'The application directory has its own public/ while the web root is elsewhere: inspect it for misplaced uploads (owner-approved reconciliation only).';
        }

        $configCached = $this->laravel->configurationIsCached();
        $informational[] = $configCached
            ? 'Configuration is cached: the values above come from the cache. Re-cache after any .env change.'
            : 'Configuration is not cached: the values above come from .env and config/ for this CLI process.';

        $exit = $findings === [] ? self::EXIT_CLEAN : self::EXIT_FINDINGS;
        $report = [
            'status' => $exit === self::EXIT_CLEAN ? 'clean' : 'findings',
            'exit_code' => $exit,
            'config_cached' => $configCached,
            'process_public_path' => public_path(),
            'application_public_directory_exists' => $appPublicExists,
            'web_root' => $webRoot,
            'expected_web_root' => $expected,
            'roots' => $roots,
            'findings' => $findings,
            'informational' => $informational,
            'production_verification' => self::PRODUCTION_VERIFICATION,
            'note' => 'Read-only. Results describe this process and this machine; nothing was created, changed, copied, moved or deleted.',
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));

            return $exit;
        }

        $this->renderHuman($report);

        return $exit;
    }

    private function normalise(string $path): string
    {
        $trimmed = rtrim($path, '/');

        return $trimmed === '' ? '/' : $trimmed;
    }

    /** @param array<string, mixed> $report */
    private function renderHuman(array $report): void
    {
        $yes = fn (bool $v) => $v ? 'yes' : 'no';

        $this->line('Media root audit (read-only)');
        $this->line('Process public_path(): '.$report['process_public_path']);
        $this->line('Configuration cached: '.$yes($report['config_cached']));
        $this->line('Derived web root: '.($report['web_root'] ?? '(none — roots disagree or do not follow the structure)'));
        $this->newLine();

        $this->table(
            ['variable', 'path', 'absolute', 'exists', 'readable', 'writable', 'symlink', 'canonical'],
            array_map(fn (array $r) => [
                $r['variable'], $r['path'], $yes($r['absolute']), $yes($r['exists'] && $r['directory']),
                $yes($r['readable']), $yes($r['writable']), $yes($r['symlink']), (string) ($r['canonical'] ?? '—'),
            ], $report['roots']),
        );

        $this->line('Confirmed findings (this environment):');
        foreach ($report['findings'] ?: ['none'] as $line) {
            $this->line('  - '.$line);
        }
        $this->newLine();
        $this->line('Informational:');
        foreach ($report['informational'] as $line) {
            $this->line('  - '.$line);
        }
        $this->newLine();
        $this->line('Production verification required (not checkable from this process):');
        foreach ($report['production_verification'] as $line) {
            $this->line('  - '.$line);
        }
        $this->newLine();
        $this->line($report['note']);
        $this->line('Exit code: '.$report['exit_code']);
    }
}
