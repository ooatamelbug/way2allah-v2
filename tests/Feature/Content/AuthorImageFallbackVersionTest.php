<?php

/*
 * Author-image feedback (owner, 2026-11-18) — «تعديل بيانات الداعية» replaces `media/authors/{b}/{id}.jpg` under the
 * same name. The author page's sidebar thumbnails (ContentSidebarWidget::topitemsThumb) and the home page's video
 * thumbnails (HomeController::bucketedThumb) fell back to that file with an UNVERSIONED URL, so browsers kept the old
 * photo (reproduced in Chrome with Apache-style headers). The fallback now carries `?v={mtime}`, like `author_image`.
 * Frames, covers, placeholders, the profile avatar and the authors listing are unchanged.
 */

use App\Domain\Content\Http\Controllers\HomeController;
use App\Domain\Content\Models\Author;
use App\Domain\Content\Services\ContentSidebarWidget;
use App\Domain\Content\Support\MediaUrl;

beforeEach(function () {
    $this->media = sys_get_temp_dir().'/w2a-author-version-'.uniqid();
    mkdir($this->media.'/authors/0', 0775, true);
    mkdir($this->media.'/khotab_frames/1', 0775, true);
    config(['admin_khotab.media_root' => $this->media, 'media.base_url' => '/media']);
    $this->sidebar = fn (int $frame, int $id, ?int $authorId) => (fn () => $this->topitemsThumb($frame, $id, $authorId))->call(app(ContentSidebarWidget::class));
    $this->home = fn (int $id, int $frame, ?int $authorId) => (fn () => $this->bucketedThumb($id, $frame, $authorId))->call(app(HomeController::class));
    $this->writeAuthor = function (int $mtime, string $bytes = 'jpg'): void {
        $file = $this->media.'/authors/0/7.jpg';
        file_put_contents($file, $bytes);
        touch($file, $mtime);
    };
});

afterEach(function () {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->media, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($this->media);
});

it('versions the sidebar author-image fallback by the file mtime', function () {
    ($this->writeAuthor)(1_700_000_000);

    expect(($this->sidebar)(0, 55, 7))->toBe('/media/authors/0/7.jpg?v=1700000000');
});

it('changes the sidebar version when the author image is replaced', function () {
    ($this->writeAuthor)(1_700_000_000, 'old');
    $before = ($this->sidebar)(0, 55, 7);
    ($this->writeAuthor)(1_700_000_500, 'new');

    expect($before)->toBe('/media/authors/0/7.jpg?v=1700000000')
        ->and(($this->sidebar)(0, 55, 7))->toBe('/media/authors/0/7.jpg?v=1700000500');
});

it('versions the home-page author-image fallback by the file mtime', function () {
    ($this->writeAuthor)(1_700_000_000);

    expect(($this->home)(55, 0, 7))->toBe('/media/authors/0/7.jpg?v=1700000000');
});

it('changes the home-page version when the author image is replaced', function () {
    ($this->writeAuthor)(1_700_000_000, 'old');
    $before = ($this->home)(55, 0, 7);
    ($this->writeAuthor)(1_700_000_500, 'new');

    expect($before)->toBe('/media/authors/0/7.jpg?v=1700000000')
        ->and(($this->home)(55, 0, 7))->toBe('/media/authors/0/7.jpg?v=1700000500');
});

it('keeps an existing query string on a configured media base URL', function () {
    config(['media.base_url' => 'https://cdn.example.test/media']);
    ($this->writeAuthor)(1_700_000_000);

    expect(($this->sidebar)(0, 55, 7))->toBe('https://cdn.example.test/media/authors/0/7.jpg?v=1700000000')
        ->and(MediaUrl::withVersion('https://cdn.example.test/a.jpg?sig=abc', 5))->toBe('https://cdn.example.test/a.jpg?sig=abc&v=5')
        ->and(MediaUrl::withVersion('/a.jpg#top', 5))->toBe('/a.jpg?v=5#top');
});

it('leaves frame images unversioned and preferred over the author image, as before', function () {
    ($this->writeAuthor)(1_700_000_000);
    file_put_contents($this->media.'/khotab_frames/1/1500.jpg', 'frame');

    expect(($this->sidebar)(1, 1500, 7))->toBe('/media/khotab_frames/1/1500.jpg')
        ->and(($this->home)(1500, 1, 7))->toBe('/media/khotab_frames/1/1500.jpg');
});

it('keeps the missing-image placeholders unchanged', function () {
    expect(($this->sidebar)(0, 55, 7))->toBe('/images/way2_withoutimg.png')
        ->and(($this->sidebar)(0, 55, null))->toBe('/images/way2_withoutimg.png')
        ->and(($this->home)(55, 0, 7))->toBe('/images/tvnoise.gif')
        ->and(($this->home)(1500, 1, 7))->toBe('/images/tvnoise.gif')   // a frame item without its frame file
        ->and(($this->home)(55, 0, null))->toBe('/images/tvnoise.gif');
});

it('leaves the profile avatar and listing source (author_image / fallback) unchanged', function () {
    ($this->writeAuthor)(1_700_000_000);

    expect((new Author(['author_image' => '/media/authors/0/7.jpg?v=123']))->displayImageUrl())->toBe('/media/authors/0/7.jpg?v=123')
        ->and((new Author(['author_image' => 'https://old.way2allah.com/media/authors/0/7.jpg']))->displayImageUrl())->toBe('https://old.way2allah.com/media/authors/0/7.jpg')
        ->and((new Author)->forceFill(['id' => 7, 'author_image' => ''])->displayImageUrl())->toBe('/media/authors/no_author_image.png');
});
