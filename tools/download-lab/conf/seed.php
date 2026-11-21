<?php

// Lab seed: legacy tables from the test fixtures, the 3 download migrations, and synthetic items. No real data.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\Fixtures\MainSchema;

foreach (['nuke_authors' => MainSchema::nukeAuthors(), 'nuke_config' => MainSchema::nukeConfig(), 'ips' => MainSchema::ips(),
    'nuke_islamic_khotab' => MainSchema::nukeIslamicKhotab(), 'nuke_islamic_authors' => MainSchema::nukeIslamicAuthors(),
    'nuke_islamic_mirror' => MainSchema::nukeIslamicMirror()] as $t => $def) {
    Schema::connection('main')->dropIfExists($t);
    Schema::connection('main')->create($t, $def);
}
foreach (['2026_10_22_130000_create_download_rejections_table.php', '2026_11_11_100000_create_download_telemetry_tables.php',
    '2026_11_11_300000_add_resource_title_to_download_rejections.php'] as $m) {
    Artisan::call('migrate', ['--path' => 'database/migrations/'.$m, '--force' => true]);
    echo trim(Artisan::output()), "\n";
}
$db = DB::connection('main');
$db->table('nuke_config')->insert(['language' => 'arabic', 'multilingual' => 0, 'anonymous' => 'Anonymous']);
$db->table('nuke_islamic_authors')->insert(['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.']);
$src = 'http://w2a-lab-source:8090';
$items = [
    101 => '/data/lecture.mp3', 102 => '/data/lecture.mp4', 103 => '/data/transcript.pdf', 104 => '/data/archive.rar',
    105 => '/data/missing.mp3',
    201 => "$src/file/5242880/talk.mp3?sig=SECRET-SIGNATURE",      // 5 MiB fast
    202 => "$src/slow/2097152/65536/slow.mp3",                    // 2 MiB at 64 KiB/s (32 s)
    203 => "$src/stall/10485760/524288/stall.mp4",                // 512 KiB then silence
    204 => "$src/drop/10485760/1048576/drop.mp4",                 // 1 MiB then clean close (truncated)
    205 => "$src/reset/10485760/1048576/reset.mp4",               // 1 MiB then RST
    206 => "$src/html",                                           // HTML page (rejected)
    207 => "$src/missing",                                        // 404 upstream
    208 => "$src/slow/104857600/131072/big.mp4",                  // 100 MiB at 128 KiB/s (long transfer, for disconnect / concurrency)
    209 => "$src/file/1048576/one.mp3",                           // 1 MiB fast
];
foreach ($items as $id => $link) {
    $db->table('nuke_islamic_khotab')->insert(['id' => $id, 'author' => 7, 'title' => "مادة اختبار {$id}", 'vedio' => 1, 'link' => $link, 'linksize' => 1000, 'downcount' => 0, 'pdf' => 1]);
}
foreach ([301 => 'http://www.youtube.com/watch?v=abc123&list=PL9', 302 => 'https://soundcloud.com/way2allah/lecture-1',
    303 => "$src/html/youtube.com/watch", 304 => '/data/lecture.mp3'] as $id => $link) {
    $db->table('nuke_islamic_mirror')->insert(['id' => $id, 'khid' => 101, 'link' => $link, 'linksize' => 1000, 'hits' => 0]);
}
echo 'seeded ', $db->table('nuke_islamic_khotab')->count(), ' items, ', $db->table('nuke_islamic_mirror')->count(), " mirrors\n";
