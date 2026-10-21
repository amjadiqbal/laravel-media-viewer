<?php

use AmjadIqbal\MediaViewer\Facades\MediaViewer;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $disk = Storage::disk('local');
    $disk->put('hls/course/index.m3u8', "#EXTM3U\n#EXT-X-STREAM-INF:BANDWIDTH=800000\n720p/index.m3u8\n");
    $disk->put('hls/course/720p/index.m3u8', "#EXTM3U\n#EXT-X-TARGETDURATION:4\n#EXT-X-MAP:URI=\"init.mp4\"\n#EXTINF:4.0,\nseg0.m4s\n#EXTINF:4.0,\nnotes.html\n#EXT-X-ENDLIST\n");
    $disk->put('hls/course/720p/init.mp4', 'INIT');
    $disk->put('hls/course/720p/seg0.m4s', 'SEGMENT-0');
});

it('rewrites a private hls playlist so every level and segment is signed and playable', function () {
    $master = $this->get(MediaViewer::url('local', 'hls/course/index.m3u8'));
    $master->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.apple.mpegurl');
    expect($master->headers->get('Cache-Control'))->toContain('no-store');
    expect($master->headers->get('Content-Security-Policy'))->toBe("default-src 'none'; sandbox");
    $master->assertHeader('X-Content-Type-Options', 'nosniff');

    preg_match('~^(http\S+720p/index\.m3u8\?\S+)$~m', $master->getContent(), $level);
    expect($level)->toHaveCount(2);

    $media = $this->get($level[1])->assertOk()->getContent();
    preg_match('~URI="([^"]+)"~', $media, $init);
    preg_match('~^(http\S+seg0\.m4s\?\S+)$~m', $media, $segment);

    expect($this->get($init[1])->assertOk()->streamedContent())->toBe('INIT');
    expect($this->get($segment[1])->assertOk()->streamedContent())->toBe('SEGMENT-0');
    expect($media)->toContain("\nnotes.html\n");
});

it('keeps the playlist expiry and user on the rewritten urls', function () {
    $url = MediaViewer::url('local', 'hls/course/720p/index.m3u8', 10);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    $media = $this->get($url)->getContent();
    preg_match('~^(http\S+seg0\.m4s\?\S+)$~m', $media, $segment);
    parse_str((string) parse_url($segment[1], PHP_URL_QUERY), $segmentQuery);

    expect($segmentQuery['expires'])->toBe($query['expires']);

    $this->travel(11)->minutes();
    $this->get($segment[1])->assertForbidden();
});
