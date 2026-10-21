<?php

use AmjadIqbal\MediaViewer\Support\FileResponse;

it('parses single byte ranges', function (?string $header, int $size, array|false|null $expected) {
    expect(FileResponse::parseRange($header, $size))->toBe($expected);
})->with([
    'no header' => [null, 100, null],
    'first bytes' => ['bytes=0-9', 100, [0, 9]],
    'middle' => ['bytes=10-19', 100, [10, 19]],
    'open ended' => ['bytes=90-', 100, [90, 99]],
    'end past size is clamped' => ['bytes=90-500', 100, [90, 99]],
    'suffix' => ['bytes=-10', 100, [90, 99]],
    'suffix larger than file' => ['bytes=-500', 100, [0, 99]],
    'zero suffix' => ['bytes=-0', 100, false],
    'start at size' => ['bytes=100-', 100, false],
    'start past size' => ['bytes=200-300', 100, false],
    'empty file' => ['bytes=0-', 0, false],
    'last before first is ignored' => ['bytes=20-10', 100, null],
    'multiple ranges are ignored' => ['bytes=0-1,5-6', 100, null],
    'other unit is ignored' => ['items=0-1', 100, null],
    'garbage is ignored' => ['bytes=abc', 100, null],
    'empty spec is ignored' => ['bytes=-', 100, null],
    'spaces and case' => ['Bytes = 1 - 2', 100, [1, 2]],
]);
