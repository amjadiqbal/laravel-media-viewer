<?php

namespace AmjadIqbal\MediaViewer\Livewire;

use AmjadIqbal\MediaViewer\MediaViewer;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A list of files with PDF thumbnails beside a viewer for the selected one.
 *
 *   <livewire:media-viewer-gallery disk="s3" :paths="$document->files" />
 *
 * disk and paths are locked, so the browser cannot swap in another file;
 * every file is still checked against the disk list, extensions and Gate.
 */
class MediaGallery extends Component
{
    #[Locked]
    public string $disk = '';

    /** @var list<string> */
    #[Locked]
    public array $paths = [];

    public int $selected = 0;

    /** @param  array<int, string>  $paths */
    public function mount(?string $disk = null, array $paths = [], int $selected = 0): void
    {
        $this->disk = $disk ?? (string) config('filesystems.default');
        $this->paths = array_values(array_filter($paths, 'is_string'));
        $this->selected = $selected;
    }

    public function select(int $index): void
    {
        if (array_key_exists($index, $this->paths)) {
            $this->selected = $index;
        }
    }

    public function render(MediaViewer $viewer, Factory $views): View
    {
        $items = [];
        foreach ($this->paths as $index => $path) {
            $items[] = [
                'index' => $index,
                'path' => $path,
                'name' => basename($path),
                'kind' => $viewer->kind($path),
                'allowed' => $viewer->allows($this->disk, $path),
            ];
        }

        return $views->make('media-viewer::livewire.gallery', [
            'items' => $items,
            'current' => $items[$this->selected] ?? null,
        ]);
    }
}
