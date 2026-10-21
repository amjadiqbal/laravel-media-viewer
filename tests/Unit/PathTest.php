<?php

use AmjadIqbal\MediaViewer\MediaViewer;

it('normalizes paths and rejects traversal', function (string $path, ?string $expected) {
    expect(app(MediaViewer::class)->normalizePath($path))->toBe($expected);
})->with([
    ['docs/a.pdf', 'docs/a.pdf'],
    ['/docs//./a.pdf', 'docs/a.pdf'],
    ['docs/../a.pdf', null],
    ['..', null],
    ['', null],
    ['/', null],
    ["docs/a\0.pdf", null],
    ['docs\\a.pdf', null],
]);

it('knows each kind', function (string $path, ?string $kind) {
    expect(app(MediaViewer::class)->kind($path))->toBe($kind);
})->with([
    ['a.PDF', 'pdf'],
    ['a.mp4', 'video'],
    ['a.webm', 'video'],
    ['a.m3u8', 'hls'],
    ['a.mp3', 'audio'],
    ['a.html', null],
]);
