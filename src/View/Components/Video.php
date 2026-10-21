<?php

namespace AmjadIqbal\MediaViewer\View\Components;

use AmjadIqbal\MediaViewer\MediaViewer;

/**
 * A Video.js 10 player. .m3u8 sources (or hls="true") play through Video.js's
 * hls.js adapter; everything else uses the browser's own <video>.
 */
class Video extends MediaComponent
{
    public bool $hls;

    public ?string $posterUrl = null;

    public function __construct(
        public ?string $disk = null,
        public ?string $path = null,
        public ?string $src = null,
        ?bool $hls = null,
        public ?string $poster = null,
        public ?string $posterPath = null,
        public string $aspectRatio = '16 / 9',
        public bool $autoplay = false,
        public bool $muted = false,
        public bool $loop = false,
        public string $preload = 'metadata',
    ) {
        $this->resolveSource($disk, $path, $src);
        $this->hls = $hls ?? $this->sourceKind($path, $src) === 'hls';

        $viewer = app(MediaViewer::class);
        $posterDisk = $disk ?? (string) config('filesystems.default');
        $this->posterUrl = $poster;
        if ($posterPath !== null && $viewer->allows($posterDisk, $posterPath)) {
            $this->posterUrl = $viewer->url($posterDisk, $posterPath);
        }
    }

    public function render(): string
    {
        return 'media-viewer::components.video';
    }
}
