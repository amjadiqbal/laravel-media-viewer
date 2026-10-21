<?php

namespace AmjadIqbal\MediaViewer\View\Components;

/** A Video.js 10 audio player. */
class Audio extends MediaComponent
{
    public function __construct(
        public ?string $disk = null,
        public ?string $path = null,
        public ?string $src = null,
        public bool $autoplay = false,
        public bool $loop = false,
        public string $preload = 'metadata',
    ) {
        $this->resolveSource($disk, $path, $src);
    }

    public function render(): string
    {
        return 'media-viewer::components.audio';
    }
}
