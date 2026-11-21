<?php

/*
 * F06 (owner-approved Option B, 2026-11-14) — an AdminCP author-image upload
 * must reach the public pages.
 *
 * Before: the upload wrote only `media/authors/{b}/{id}.jpg`; the public pages
 * read `author_image` → `media/authors/sq/{id}.png` → the placeholder, so the
 * new image never appeared. After: the upload also points `author_image` at
 * the file (root-relative, `?v={mtime}`), and every author-photo surface
 * resolves through `Author::displayImageUrl()`.
 */

use App\Domain\Admin\Actions\Khotab\UploadKhotabAuthorImageAction;
use App\Domain\Content\Models\Author;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Support\ContextualAdminHarness as H;

uses(RefreshDatabase::class);

beforeEach(function () {
    H::setUp();

    $this->tmp = sys_get_temp_dir().'/w2a-f06-'.uniqid();
    mkdir($this->tmp.'/media/authors/sq', 0775, true);
    config(['admin_khotab.media_root' => $this->tmp.'/media', 'media.base_url' => '/media']);

    // A legacy square image that must survive the upload untouched.
    file_put_contents($this->tmp.'/media/authors/sq/7.png', 'legacy-sq-png');

    H::insert('nuke_islamic_authors', ['id' => 7, 'name' => 'الشيخ', 'prename' => 'د.', 'vedio' => 3, 'audio' => 0, 'hidden' => 0]);
    $this->group = H::insert('nuke_islamic_groups', ['author_id' => 7, 'title' => 'مجموعة', 'vedio' => 1, 'count' => 1, 'hidden' => 0, 'channel_id' => 0]);
    $this->series = H::insert('nuke_islamic_series', ['author_id' => 7, 'group_id' => 0, 'title' => 'سلسلة', 'vedio' => 1, 'count' => 1, 'hidden' => 0, 'channel_id' => 0, 'lastupdate' => 1]);
    $this->item = H::insert('nuke_islamic_khotab', ['author' => 7, 'title' => 'مادة', 'vedio' => 1, 'ser_id' => $this->series, 'group_id' => $this->group, 'hidden' => 0, 'channel_id' => 0, 'weight' => 1]);
    DB::connection('main')->table('nuke_fatwa_questions')->insert(['id' => 1, 'auther_id' => 7, 'general_question_id' => '|10|', 'question_text' => 'q']);

    $this->editor = H::session(H::admin(['khotab.editauthor'], 'ed'));
    $this->uploadUrl = route('admin.khotab.authors.image', ['author' => 7, 'media' => 'video']);
});

afterEach(function () {
    if (is_dir($this->tmp)) {
        exec('rm -rf '.escapeshellarg($this->tmp));
    }
});

it('before any upload, every surface keeps the existing fallback (the legacy sq png here)', function () {
    $pages = [
        '/khotab-video-7.htm', "/khotab-group-{$this->group}.htm", "/khotab-series-{$this->series}.htm",
        "/khotab-item-{$this->item}.htm", '/khotab-video.htm', '/fatawa-by-authers.htm',
    ];

    foreach ($pages as $page) {
        expect($this->get($page)->assertOk()->getContent())->toContain('/media/authors/sq/7.png');
    }
});

it('a successful upload writes the file, sets a versioned root-relative author_image, and audits the previous value', function () {
    DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->update(['author_image' => 'https://old.way2allah.com/media/authors/0/7.jpg']);

    $this->withSession($this->editor)
        ->post($this->uploadUrl, ['photoimg' => UploadedFile::fake()->createWithContent('a.jpg', str_repeat('j', 2048))])
        ->assertRedirect();

    $file = $this->tmp.'/media/authors/0/7.jpg';
    $stored = DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->value('author_image');

    expect(filesize($file))->toBe(2048)
        ->and($stored)->toBe(UploadKhotabAuthorImageAction::publicUrl(7, filemtime($file)))
        ->and($stored)->toMatch('#^/media/authors/0/7\.jpg\?v=\d+$#');

    $audit = DB::connection('main')->table('admin_audit_log')->where('action', 'upload_author_image')->sole();
    $metadata = json_decode($audit->metadata, true);
    expect($metadata['author_image'])->toBe($stored)
        ->and($metadata['previous_author_image'])->toBe('https://old.way2allah.com/media/authors/0/7.jpg');

    // The legacy image is neither deleted nor overwritten.
    expect(file_get_contents($this->tmp.'/media/authors/sq/7.png'))->toBe('legacy-sq-png');
});

it('after an upload, the author, group, series, item, preacher-card and Fatwa by-authors pages all show the uploaded image', function () {
    $this->withSession($this->editor)
        ->post($this->uploadUrl, ['photoimg' => UploadedFile::fake()->createWithContent('a.jpg', str_repeat('j', 2048))])
        ->assertRedirect();
    $url = (string) DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->value('author_image');

    $pages = [
        'author page' => '/khotab-video-7.htm',
        'group page' => "/khotab-group-{$this->group}.htm",
        'series page' => "/khotab-series-{$this->series}.htm",
        'item page' => "/khotab-item-{$this->item}.htm",
        'preacher card (authors index)' => '/khotab-video.htm',
        'Fatwa by-authors' => '/fatawa-by-authers.htm',
    ];

    foreach ($pages as $label => $page) {
        $this->assertStringContainsString('src="'.$url.'"', (string) $this->get($page)->assertOk()->getContent(), $label);
    }
});

it('the stored value resolves through the configured media base URL (a CDN base applies to it)', function () {
    config(['media.base_url' => 'https://cdn.example.test/media']);
    $author = new Author(['author_image' => '/media/authors/0/7.jpg?v=123']);

    expect($author->displayImageUrl())->toBe('https://cdn.example.test/media/authors/0/7.jpg?v=123');
});

it('existing author_image values that were not produced by an upload are displayed unchanged', function () {
    DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->update(['author_image' => 'https://old.way2allah.com/media/authors/0/7.jpg']);

    expect($this->get("/khotab-series-{$this->series}.htm")->assertOk()->getContent())
        ->toContain('src="https://old.way2allah.com/media/authors/0/7.jpg"');
});

it('a refused upload changes neither the column nor the audit log', function () {
    $this->withSession($this->editor)
        ->post($this->uploadUrl, ['photoimg' => UploadedFile::fake()->create('a.png', 5, 'image/png')])
        ->assertSessionHasErrors('photoimg');

    expect(DB::connection('main')->table('nuke_islamic_authors')->where('id', 7)->value('author_image'))->toBeNull()
        ->and(DB::connection('main')->table('admin_audit_log')->count())->toBe(0);
});
