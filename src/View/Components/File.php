<?php

namespace AmjadIqbal\MediaViewer\View\Components;

use AmjadIqbal\MediaViewer\MediaViewer;
use Illuminate\View\Component;

/** Picks the PDF viewer, video player or audio player from the file's extension. */
class File extends Component
{
    public ?string $component;

    public function __construct(
        public ?string $disk = null,
        public ?string $path = null,
        public ?string $src = null,
    ) {
        $kind = app(MediaViewer::class)
            ->kind($path ?? (string) parse_url((string) $src, PHP_URL_PATH));

        $this->component = match ($kind) {
            'pdf' => 'media-viewer::pdf',
            'video', 'hls' => 'media-viewer::video',
            'audio' => 'media-viewer::audio',
            default => null,
        };
    }

    public function render(): string
    {
        return 'media-viewer::components.file';
    }
}
