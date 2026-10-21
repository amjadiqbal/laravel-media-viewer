<?php

namespace AmjadIqbal\MediaViewer\View\Components;

/**
 * Renders one page of a PDF as a small image in the browser, only when it
 * scrolls into view. With the Range-aware stream route, pdf.js fetches just
 * the bytes it needs for that page, not the whole file.
 */
class PdfThumbnail extends MediaComponent
{
    public function __construct(
        public ?string $disk = null,
        public ?string $path = null,
        public ?string $src = null,
        public int $width = 160,
        public int $page = 1,
        public ?string $href = null,
        public ?string $label = null,
    ) {
        $this->resolveSource($disk, $path, $src);
        $this->label ??= basename((string) ($path ?? parse_url((string) $src, PHP_URL_PATH)));
    }

    public function render(): string
    {
        return 'media-viewer::components.pdf-thumbnail';
    }
}
