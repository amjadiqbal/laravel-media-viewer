<?php

use AmjadIqbal\MediaViewer\Support\Playlist;

it('signs every relative uri in a playlist', function () {
    $playlist = <<<'M3U8'
    #EXTM3U
    #EXT-X-VERSION:7
    #EXT-X-KEY:METHOD=AES-128,URI="keys/k1.key",IV=0x1
    #EXT-X-MAP:URI="init.mp4"
    #EXTINF:4.0,
    seg 0.ts
    #EXTINF:4.0,
    ../shared/seg1.ts?token=abc
    #EXTINF:4.0,
    https://cdn.example.com/seg2.ts
    #EXT-X-KEY:METHOD=SAMPLE-AES,URI="skd://fairplay-key"
    /root/seg3.ts
    #EXT-X-ENDLIST
    M3U8;

    $out = Playlist::rewrite($playlist, 'videos/course/index.m3u8', fn (string $path) => 'SIGNED['.$path.']');

    expect($out)
        ->toContain('URI="SIGNED[videos/course/keys/k1.key]"')
        ->toContain('URI="SIGNED[videos/course/init.mp4]"')
        ->toContain("\nSIGNED[videos/course/seg 0.ts]\n")
        ->toContain("\nSIGNED[videos/shared/seg1.ts]\n")
        ->toContain("\nhttps://cdn.example.com/seg2.ts\n")
        ->toContain('URI="skd://fairplay-key"')
        ->toContain("\nSIGNED[root/seg3.ts]\n")
        ->toContain('#EXT-X-ENDLIST');
});

it('decodes percent-encoded uris and never climbs above the disk root', function () {
    $out = Playlist::rewrite("#EXTM3U\nseg%201.ts\n../../../etc/passwd\n", 'a/index.m3u8', fn (string $path) => 'SIGNED['.$path.']');

    expect($out)->toContain('SIGNED[a/seg 1.ts]')
        ->toContain("\n../../../etc/passwd\n")
        ->not->toContain('SIGNED[etc');
});

it('leaves a uri alone when the signer refuses it', function () {
    expect(Playlist::rewrite("#EXTM3U\nnotes.html\n", 'index.m3u8', fn () => null))->toBe("#EXTM3U\nnotes.html\n");
});
