<?php

use App\Domain\Content\Support\MediaUrl;
use Tests\TestCase;

uses(TestCase::class);

it('builds media URLs from relative and legacy media paths', function () {
    config()->set('media.base_url', 'https://cdn.example.test/media/');

    expect(MediaUrl::asset('authors/0/3.jpg'))
        ->toBe('https://cdn.example.test/media/authors/0/3.jpg')
        ->and(MediaUrl::asset('/media/7amlat/slide.jpg'))
        ->toBe('https://cdn.example.test/media/7amlat/slide.jpg');
});

it('preserves URLs that are already absolute', function () {
    config()->set('media.base_url', 'https://cdn.example.test/media');

    expect(MediaUrl::asset('https://images.example.test/custom.jpg'))
        ->toBe('https://images.example.test/custom.jpg')
        ->and(MediaUrl::asset('//images.example.test/custom.jpg'))
        ->toBe('//images.example.test/custom.jpg');
});

it('builds thumbnail URLs without changing the legacy query string', function () {
    config()->set('media.thumbnail_url', 'https://img.example.test/thumbnails.php');

    expect(MediaUrl::thumbnail('h=197&w=242&src=media/albums/example.jpg'))
        ->toBe('https://img.example.test/thumbnails.php?h=197&w=242&src=media/albums/example.jpg');
});

it('falls back to same-origin paths when configured values are empty', function () {
    config()->set('media.base_url', '');
    config()->set('media.thumbnail_url', '');

    expect(MediaUrl::asset('authors/0/3.jpg'))->toBe('/media/authors/0/3.jpg')
        ->and(MediaUrl::thumbnail('w=72&src=images/example.jpg'))
        ->toBe('/thumbnails.php?w=72&src=images/example.jpg');
});
