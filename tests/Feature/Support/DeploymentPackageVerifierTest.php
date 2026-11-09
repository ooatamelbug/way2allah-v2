<?php

/*
 * P0 packaging safeguards (owner-approved 2026-11-09): `deploy:verify-package` refuses a split-layout package that
 * would replace the production front controller with the local one, overwrite runtime autocomplete lists, or ship
 * bootstrap cache artifacts — and never modifies the package. Correction (2026-11-09, D1 / D2 / D4): every entry
 * name is validated before the protected-path rules (unsafe_entry_name), duplicates (duplicate_entry) and symlinks
 * (symlink_entry) are refused, and --full requires the canonical front controller.
 *
 * Archives are built byte-for-byte by dpvRawZip() so that names ZipArchive itself would normalise or refuse
 * (duplicates, backslashes, absolute paths, dot segments, symlink mode bits) reach the verifier exactly as written.
 */

use App\Console\Commands\VerifyDeploymentPackage;
use Illuminate\Support\Facades\Artisan;

const DPV_FILE = 0100644;
const DPV_DIR = 040755;
const DPV_LINK = 0120777;

function dpvTemp(): string
{
    $dir = sys_get_temp_dir().'/dpv-'.bin2hex(random_bytes(6));
    mkdir($dir, 0775, true);

    return (string) realpath($dir);
}

function dpvStage(array $files): string
{
    $root = dpvTemp();
    foreach ($files as $name => $contents) {
        @mkdir(dirname($root.'/'.$name), 0775, true);
        file_put_contents($root.'/'.$name, $contents);
    }

    return $root;
}

/**
 * A stored (uncompressed) ZIP written byte-for-byte, Unix "made by", with exact names and mode bits.
 *
 * @param  list<array{0: string, 1: string, 2?: int}>  $entries  [name, data, unix mode]
 */
function dpvRawZip(array $entries): string
{
    $path = sys_get_temp_dir().'/dpv-'.bin2hex(random_bytes(6)).'.zip';
    $local = '';
    $central = '';
    foreach ($entries as $entry) {
        [$name, $data] = $entry;
        $mode = $entry[2] ?? (str_ends_with($name, '/') ? DPV_DIR : DPV_FILE);
        $offset = strlen($local);
        $crc = crc32($data);
        $length = strlen($data);
        $local .= pack('VvvvvvVVVvv', 0x04034B50, 20, 0, 0, 0, 0x21, $crc, $length, $length, strlen($name), 0).$name.$data;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014B50, (3 << 8) | 20, 20, 0, 0, 0, 0x21, $crc, $length, $length, strlen($name), 0, 0, 0, 0, $mode << 16, $offset).$name;
    }
    $count = count($entries);
    file_put_contents($path, $local.$central.pack('VvvvvVVv', 0x06054B50, 0, 0, $count, $count, strlen($central), strlen($local), 0));

    return $path;
}

/** @param array<string, string> $files */
function dpvZip(array $files): string
{
    return dpvRawZip(array_map(fn ($name, $data) => [$name, $data], array_keys($files), array_values($files)));
}

function dpvRemove(string $path): void
{
    if (is_link($path) || is_file($path)) {
        @unlink($path);

        return;
    }
    if (! is_dir($path)) {
        return;
    }
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            dpvRemove($path.'/'.$entry);
        }
    }
    @rmdir($path);
}

function dpvTemplate(): string
{
    return (string) file_get_contents(base_path(VerifyDeploymentPackage::TEMPLATE));
}

function dpvLocalIndex(): string
{
    return (string) file_get_contents(public_path('index.php'));
}

function dpvVerify(string $package, bool $full = false): array
{
    $exit = Artisan::call('deploy:verify-package', ['package' => $package, '--json' => true] + ($full ? ['--full' => true] : []));

    return [$exit, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
}

function dpvRules(array $report): array
{
    return array_values(array_unique(array_column($report['violations'], 'rule')));
}

/** Verifies a raw ZIP and removes it. */
function dpvZipResult(array $entries, bool $full = false): array
{
    $zip = dpvRawZip($entries);
    try {
        return dpvVerify($zip, $full);
    } finally {
        @unlink($zip);
    }
}

// ================================================================ canonical template

it('the canonical split front controller points at ../laravel-app and is not the local public/index.php', function () {
    expect(dpvTemplate())->toContain("require __DIR__.'/../laravel-app/vendor/autoload.php';")
        ->toContain("require_once __DIR__.'/../laravel-app/bootstrap/app.php';")
        ->toContain("__DIR__.'/../laravel-app/storage/framework/maintenance.php'")
        ->toContain('$app->usePublicPath(__DIR__);')
        ->not->toBe(dpvLocalIndex());
});

// ================================================================ 1–9: unsafe entry names (D1, absolute paths)

it('refuses unsafe entry names before any protected-path rule, never normalising them', function (string $name) {
    [$exit, $report] = dpvZipResult([[$name, dpvLocalIndex()]]);

    expect($exit)->toBe(VerifyDeploymentPackage::EXIT_VIOLATIONS)
        ->and(dpvRules($report))->toBe(['unsafe_entry_name']);
})->with([
    '1. newapp/./index.php' => ['newapp/./index.php'],
    '2. newapp//index.php' => ['newapp//index.php'],
    '3. newapp/x/../index.php' => ['newapp/x/../index.php'],
    '4. backslash separators' => ['newapp\\index.php'],
    '4b. mixed backslash' => ['newapp/w2a_autocomplete\\authors.txt'],
    '5. autocomplete bypass (dot)' => ['newapp/./w2a_autocomplete/authors.txt'],
    '5b. autocomplete bypass (double slash)' => ['newapp//w2a_autocomplete/authors.txt'],
    '6. bootstrap cache bypass (double slash)' => ['laravel-app/bootstrap//cache/config.php'],
    '6b. bootstrap cache bypass (via newapp/..)' => ['newapp/../laravel-app/bootstrap/cache/config.php'],
    '7. absolute path' => ['/newapp/index.php'],
    '7b. absolute outside path' => ['/etc/cron.d/x'],
    '8. leading ../' => ['../newapp/index.php'],
    '9. embedded ../' => ['laravel-app/app/../../etc/passwd'],
    'NUL byte' => ["newapp/index.php\0.txt"],
    'NUL byte, hidden by libzip' => ["newapp/w2a_autocomplete/authors.txt\0"],
    'unexpected top level' => ['public/index.php'],
    'top-level file' => ['index.php'],
    'empty name' => [''],
]);

it('refuses a trailing slash on an entry whose mode bits say regular file', function () {
    [$exit, $report] = dpvZipResult([['newapp/index.php/', dpvLocalIndex(), DPV_FILE]]);

    expect($exit)->toBe(VerifyDeploymentPackage::EXIT_VIOLATIONS)->and(dpvRules($report))->toBe(['unsafe_entry_name']);
});

it('a directory entry without a trailing slash (directory mode bits) is still checked as a directory', function () {
    [, $report] = dpvZipResult([['newapp/w2a_autocomplete/nested', '', DPV_DIR], ['newapp/index.php', '', DPV_DIR]]);

    expect(dpvRules($report))->toEqualCanonicalizing(['runtime_autocomplete_file', 'front_controller_not_split']);
});

it('reports ZIP64 / truncated / prepended archives as unreadable rather than partially checked', function () {
    $zip = dpvRawZip([['newapp/css/a.css', 'x']]);
    $bytes = (string) file_get_contents($zip);

    file_put_contents($zip, 'PREPENDED'.$bytes);
    expect(dpvVerify($zip)[0])->toBe(VerifyDeploymentPackage::EXIT_UNREADABLE);

    file_put_contents($zip, substr($bytes, 0, -5));
    expect(dpvVerify($zip)[0])->toBe(VerifyDeploymentPackage::EXIT_UNREADABLE);

    // Bytes hidden between the central directory and its end record: the layout no longer adds up.
    $end = strrpos($bytes, "PK\x05\x06");
    file_put_contents($zip, substr($bytes, 0, $end).'HIDDEN'.substr($bytes, $end));
    expect(dpvVerify($zip)[0])->toBe(VerifyDeploymentPackage::EXIT_UNREADABLE);
    @unlink($zip);
});

it('directory markers cannot bypass protected paths', function () {
    [$exit, $report] = dpvZipResult([
        ['newapp/', ''],
        ['newapp/w2a_autocomplete/', ''],                 // the protected directory itself: harmless marker
        ['newapp/w2a_autocomplete/nested/', ''],          // inside it: refused
        ['laravel-app/bootstrap/cache/', ''],
        ['laravel-app/bootstrap/cache/x/', ''],
        ['laravel-app/public/', ''],
        ['newapp/index.php/', ''],                       // a directory where the front controller belongs
        ['newapp/assets//', ''],                          // two trailing slashes: an empty segment
    ]);

    $byEntry = collect($report['violations'])->groupBy('entry')->map(fn ($v) => $v->pluck('rule')->all())->all();

    expect($exit)->toBe(VerifyDeploymentPackage::EXIT_VIOLATIONS)
        ->and($byEntry)->toMatchArray([
            'newapp/w2a_autocomplete/nested' => ['runtime_autocomplete_file'],
            'laravel-app/bootstrap/cache/x' => ['bootstrap_cache_artifact'],
            'laravel-app/public' => ['application_public_file'],
            'newapp/index.php' => ['front_controller_not_split'],
            'newapp/assets//' => ['unsafe_entry_name'],
        ])
        ->and($byEntry)->not->toHaveKey('newapp/')->not->toHaveKey('newapp/w2a_autocomplete')->not->toHaveKey('laravel-app/bootstrap/cache');
});

// ================================================================ 10: duplicates (D2)

it('10. refuses duplicate entries — a canonical index followed by the local one cannot pass', function () {
    [$exit, $report] = dpvZipResult([
        ['newapp/index.php', dpvTemplate()],
        ['newapp/index.php', dpvLocalIndex()],
    ]);

    expect($exit)->toBe(VerifyDeploymentPackage::EXIT_VIOLATIONS)
        ->and(dpvRules($report))->toBe(['duplicate_entry']);
});

it('refuses duplicate entries anywhere, including two canonical copies and a file/directory clash', function () {
    [, $twoCanonical] = dpvZipResult([['newapp/index.php', dpvTemplate()], ['newapp/index.php', dpvTemplate()]]);
    [, $clash] = dpvZipResult([['newapp/assets/', ''], ['newapp/assets', 'x']]);
    [, $other] = dpvZipResult([['laravel-app/app/A.php', 'a'], ['laravel-app/app/A.php', 'b']]);

    expect(dpvRules($twoCanonical))->toBe(['duplicate_entry'])
        ->and(dpvRules($clash))->toBe(['duplicate_entry'])
        ->and(dpvRules($other))->toBe(['duplicate_entry']);
});

// ================================================================ 11–13: symlinks (D4)

it('11. refuses a ZIP symlink entry identified by its Unix file-type bits', function () {
    [$exit, $report] = dpvZipResult([
        ['newapp/media', '/home/someone/elsewhere', DPV_LINK],
        ['newapp/index.php', '/etc/passwd', DPV_LINK],
    ]);

    expect($exit)->toBe(VerifyDeploymentPackage::EXIT_VIOLATIONS)
        ->and(dpvRules($report))->toBe(['symlink_entry'])
        ->and($report['violations'])->toHaveCount(2);
});

it('a ZIP symlink at the front controller is not mistaken for a missing one in --full mode', function () {
    [, $report] = dpvZipResult([['newapp/index.php', 'index.real.php', DPV_LINK]], full: true);

    expect(dpvRules($report))->toBe(['symlink_entry']);
});

it('12. refuses a staged-file symlink without reading through it', function () {
    $outside = dpvTemp();
    file_put_contents($outside.'/secret.txt', 'outside');
    $package = dpvStage(['newapp/css/a.css' => 'x']);
    symlink($outside.'/secret.txt', $package.'/newapp/index.php');
    chmod($outside.'/secret.txt', 0000);              // reading through the link would fail; it is never read

    [$exit, $report] = dpvVerify($package, full: true);

    expect($exit)->toBe(VerifyDeploymentPackage::EXIT_VIOLATIONS)
        ->and(dpvRules($report))->toBe(['symlink_entry'])
        ->and($report['violations'][0]['entry'])->toBe('newapp/index.php');
    chmod($outside.'/secret.txt', 0644);
    dpvRemove($package);
    dpvRemove($outside);
});

it('13. refuses a staged-directory symlink: it is reported, not silently skipped or descended into', function () {
    $outside = dpvTemp();
    file_put_contents($outside.'/authors.txt', 'live');
    $package = dpvStage(['newapp/index.php' => dpvTemplate()]);
    symlink($outside, $package.'/newapp/w2a_autocomplete');

    [$exit, $report] = dpvVerify($package);

    expect($exit)->toBe(VerifyDeploymentPackage::EXIT_VIOLATIONS)
        ->and(dpvRules($report))->toBe(['symlink_entry'])
        ->and(array_column($report['violations'], 'entry'))->toBe(['newapp/w2a_autocomplete']);
    dpvRemove($package);
    dpvRemove($outside);
});

it('refuses a package path that is itself a symlink', function () {
    $real = dpvStage(['newapp/index.php' => dpvTemplate()]);
    $link = sys_get_temp_dir().'/dpv-link-'.bin2hex(random_bytes(6));
    symlink($real, $link);

    expect(dpvVerify($link)[0])->toBe(VerifyDeploymentPackage::EXIT_UNREADABLE);
    @unlink($link);
    dpvRemove($real);
});

// ================================================================ 14–17: incremental vs full

it('14. a valid incremental package without a front controller passes (default mode)', function () {
    foreach ([dpvStage(['laravel-app/resources/views/home.blade.php' => 'x', 'newapp/css/a.css' => 'y']), dpvZip(['laravel-app/resources/views/home.blade.php' => 'x', 'newapp/css/a.css' => 'y'])] as $package) {
        [$exit, $report] = dpvVerify($package);
        expect($exit)->toBe(VerifyDeploymentPackage::EXIT_CLEAN)->and($report['mode'])->toBe('incremental');
        dpvRemove($package);
    }
});

it('15. a valid full package with the canonical front controller passes in --full mode', function () {
    $files = [
        'newapp/index.php' => dpvTemplate(),
        'newapp/assets/site.css' => 'body{}',
        'laravel-app/app/Foo.php' => '<?php',
        'laravel-app/bootstrap/cache/.gitignore' => "*\n!.gitignore\n",
    ];
    foreach ([dpvStage($files), dpvZip($files), dpvRawZip([['newapp/', ''], ['laravel-app/', ''], ['newapp/index.php', dpvTemplate()], ['laravel-app/bootstrap/cache/', ''], ['laravel-app/bootstrap/cache/.gitignore', '*']])] as $package) {
        [$exit, $report] = dpvVerify($package, full: true);
        expect($exit)->toBe(VerifyDeploymentPackage::EXIT_CLEAN)->and($report['mode'])->toBe('full')->and($report['violations'])->toBe([]);
        dpvRemove($package);
    }
});

it('16. a full package missing the front controller fails in --full mode (and passes as incremental)', function () {
    $files = ['newapp/css/a.css' => 'x', 'laravel-app/app/Foo.php' => '<?php'];
    foreach ([dpvStage($files), dpvZip($files)] as $package) {
        [$exit, $report] = dpvVerify($package, full: true);
        expect($exit)->toBe(VerifyDeploymentPackage::EXIT_VIOLATIONS)->and(dpvRules($report))->toBe(['front_controller_missing'])
            ->and(dpvVerify($package)[0])->toBe(VerifyDeploymentPackage::EXIT_CLEAN);
        dpvRemove($package);
    }
});

it('17. a wrong front controller fails in both modes', function () {
    foreach ([dpvLocalIndex(), dpvTemplate()."\n// edited\n"] as $contents) {
        foreach ([false, true] as $full) {
            [$exit, $report] = dpvZipResult([['newapp/index.php', $contents]], $full);
            expect($exit)->toBe(VerifyDeploymentPackage::EXIT_VIOLATIONS)->and(dpvRules($report))->toBe(['front_controller_not_split']);
        }
    }
    [, $local] = dpvZipResult([['newapp/index.php', dpvLocalIndex()]]);
    expect($local['violations'][0]['message'])->toContain('co-located public/index.php');
});

// ================================================================ 18: existing behaviour preserved

it('18. existing rules: runtime autocomplete lists, bootstrap cache artifacts, laravel-app/public and top level', function () {
    [$exit, $autocomplete] = dpvVerify($zip = dpvZip(['newapp/w2a_autocomplete/authors.txt' => 'a', 'newapp/w2a_autocomplete/channels.txt' => 'b']));
    expect($exit)->toBe(VerifyDeploymentPackage::EXIT_VIOLATIONS)->and(dpvRules($autocomplete))->toBe(['runtime_autocomplete_file'])
        ->and($autocomplete['violations'])->toHaveCount(2);
    @unlink($zip);

    $package = dpvStage([
        'laravel-app/bootstrap/cache/.gitignore' => '*',
        'laravel-app/bootstrap/cache/config.php' => '<?php return [];',
        'laravel-app/bootstrap/cache/packages.php' => '<?php return [];',
        'laravel-app/bootstrap/cache/services.php' => '<?php return [];',
        'laravel-app/bootstrap/cache/routes-v7.php' => '<?php',
    ]);
    [, $cache] = dpvVerify($package);
    expect(dpvRules($cache))->toBe(['bootstrap_cache_artifact'])->and($cache['violations'])->toHaveCount(4);
    dpvRemove($package);

    [, $mixed] = dpvVerify($zip = dpvZip(['laravel-app/public/index.php' => '<?php', 'public/css/a.css' => 'x', 'newapp/w2a_autocomplete' => 'file replacing the directory']));
    expect(dpvRules($mixed))->toEqualCanonicalizing(['application_public_file', 'unsafe_entry_name', 'runtime_autocomplete_file']);
    @unlink($zip);

    [, $nested] = dpvVerify($zip = dpvZip(['laravel-app/bootstrap/cache/sub/.gitignore' => '*']));
    expect(dpvRules($nested))->toBe(['bootstrap_cache_artifact']);     // only the cache directory's own .gitignore is allowed
    @unlink($zip);
});

it('reports an unreadable package (exit 2) and usage errors (exit 64)', function () {
    $notZip = sys_get_temp_dir().'/dpv-'.bin2hex(random_bytes(6)).'.zip';
    file_put_contents($notZip, 'not a zip');

    expect(dpvVerify('/nonexistent/package.zip')[0])->toBe(VerifyDeploymentPackage::EXIT_UNREADABLE)
        ->and(dpvVerify($notZip)[0])->toBe(VerifyDeploymentPackage::EXIT_UNREADABLE)
        ->and(Artisan::call('deploy:verify-package'))->toBe(VerifyDeploymentPackage::EXIT_USAGE)
        ->and(Artisan::call('deploy:verify-package', ['package' => '/tmp', '--bogus' => true]))->toBe(VerifyDeploymentPackage::EXIT_USAGE);
    @unlink($notZip);
});

it('escapes control characters in reported entry names', function () {
    [, $report] = dpvZipResult([["newapp/a\x01b/../x.php", 'x'], ["newapp/index.php\0.txt", 'y']]);

    expect(array_column($report['violations'], 'entry'))->toBe(['newapp/a\\x01b/../x.php', 'newapp/index.php\\x00.txt']);
});

// ================================================================ 19: read-only

it('19. never extracts or writes: the package, its directory and the temp directory are unchanged', function () {
    $snapshot = function (string $dir): array {
        $items = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $f) {
            $items[$f->getPathname()] = $f->isLink() ? 'link:'.readlink($f->getPathname()) : ($f->isDir() ? 'dir' : hash_file('sha256', $f->getPathname()).'@'.$f->getMTime());
        }
        ksort($items);

        return $items;
    };

    $staged = dpvStage(['newapp/index.php' => 'bad', 'newapp/w2a_autocomplete/authors.txt' => 'live', 'laravel-app/bootstrap/cache/config.php' => 'c']);
    symlink('/etc', $staged.'/newapp/media');
    $zipDir = dpvTemp();
    $zip = dpvRawZip([['newapp/index.php', dpvTemplate()], ['newapp/index.php', 'x'], ['newapp/media', '/etc', DPV_LINK], ['../escape.txt', 'x'], ['/abs.txt', 'x']]);
    rename($zip, $zipDir.'/package.zip');
    $before = [$snapshot($staged), $snapshot($zipDir), scandir(sys_get_temp_dir())];

    foreach ([[$staged, false], [$staged, true], [$zipDir.'/package.zip', false], [$zipDir.'/package.zip', true]] as [$package, $full]) {
        dpvVerify($package, $full);
        Artisan::call('deploy:verify-package', ['package' => $package] + ($full ? ['--full' => true] : []));
    }

    expect([$snapshot($staged), $snapshot($zipDir), scandir(sys_get_temp_dir())])->toBe($before)
        ->and(file_exists(dirname($zipDir).'/escape.txt'))->toBeFalse()
        ->and(file_exists('/abs.txt'))->toBeFalse();
    dpvRemove($staged);
    dpvRemove($zipDir);
});
