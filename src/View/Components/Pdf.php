<?php

namespace AmjadIqbal\MediaViewer\View\Components;

class Pdf extends MediaComponent
{
    public string $height;

    public bool $download;

    /** @var array{scale: string, page: int} */
    public array $options;

    public function __construct(
        public ?string $disk = null,
        public ?string $path = null,
        public ?string $src = null,
        ?string $height = null,
        string|int|float|null $scale = null,
        ?bool $download = null,
        public bool $search = true,
        int $page = 1,
        public ?string $filename = null,
    ) {
        $this->resolveSource($disk, $path, $src);

        $this->height = $height ?? (string) config('media-viewer.pdf.height', '80vh');
        $this->download = $download ?? (bool) config('media-viewer.pdf.download', false);
        $this->filename ??= basename((string) ($path ?? parse_url((string) $src, PHP_URL_PATH)));
        $this->options = [
            'scale' => (string) ($scale ?? config('media-viewer.pdf.scale', 'page-width')),
            'page' => max(1, $page),
        ];
    }

    public function render(): string
    {
        return 'media-viewer::components.pdf';
    }
}
