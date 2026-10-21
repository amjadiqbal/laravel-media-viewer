<?php

namespace AmjadIqbal\MediaViewer\View\Components;

use AmjadIqbal\MediaViewer\MediaViewer;
use AmjadIqbal\MediaViewer\Support\Assets;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;

/**
 * Shared source handling: either a file on a disk (checked, then served by a
 * signed URL) or a plain src URL you already trust.
 */
abstract class MediaComponent extends Component
{
    /** The URL the browser loads, or null when the file may not be shown. */
    public ?string $url = null;

    public bool $allowed = false;

    /** Stable per file (not per signature), used as wire:key so Livewire only replaces the viewer when the file changes. */
    public string $key = '';

    protected function resolveSource(?string $disk, ?string $path, ?string $src): void
    {
        $viewer = app(MediaViewer::class);

        if ($src !== null && $src !== '') {
            $this->url = $src;
            $this->allowed = true;
            $this->key = substr(md5('src|'.$src), 0, 12);

            return;
        }

        $disk ??= (string) config('filesystems.default');

        if ($path !== null && $viewer->allows($disk, $path)) {
            $this->url = $viewer->url($disk, $path);
            $this->allowed = true;
        }

        $this->key = substr(md5($disk.'|'.$path), 0, 12);
    }

    protected function sourceKind(?string $path, ?string $src): ?string
    {
        $file = $path ?? (string) parse_url((string) $src, PHP_URL_PATH);

        return app(MediaViewer::class)->kind($file);
    }

    /** Assets print once per page, never inside a Livewire update response. */
    public function assets(): HtmlString
    {
        if (request()->hasHeader('X-Livewire')) {
            return new HtmlString('');
        }

        return app(Assets::class)->render();
    }
}
