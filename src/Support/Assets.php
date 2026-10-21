<?php

namespace AmjadIqbal\MediaViewer\Support;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\HtmlString;

/**
 * Prints the browser side once per request: an import map carrying sha384
 * hashes for pdf.js and Video.js, a JSON config block, the package's own
 * stylesheet and its runtime module. Registered as a scoped singleton.
 */
class Assets
{
    protected bool $printed = false;

    /** @var array<string, string> */
    protected static array $hashes = [];

    /** @var array{pdfjs: array{package: string, version: string, files: array<string, string>}, videojs: array{package: string, version: string, files: array<string, string>}}|null */
    protected static ?array $pinned = null;

    public function __construct(protected Config $config, protected UrlGenerator $url) {}

    /** @return array{pdfjs: array{package: string, version: string, files: array<string, string>}, videojs: array{package: string, version: string, files: array<string, string>}} */
    public static function pinned(): array
    {
        return self::$pinned ??= require __DIR__.'/../../resources/integrity.php';
    }

    /** Base URL of one library: jsDelivr, or the copy made by media-viewer:assets. */
    public function base(string $library): string
    {
        $pinned = self::pinned()[$library];

        if ($this->config->get('media-viewer.assets.source') === 'local') {
            return rtrim($this->url->asset(trim((string) $this->config->get('media-viewer.assets.local_path'), '/').'/'.$library), '/');
        }

        return rtrim((string) $this->config->get('media-viewer.assets.cdn'), '/').'/'.$pinned['package'].'@'.$pinned['version'];
    }

    public function render(): HtmlString
    {
        if ($this->printed) {
            return new HtmlString('');
        }
        $this->printed = true;

        $pinned = self::pinned();
        $pdfjs = $this->base('pdfjs');
        $videojs = $this->base('videojs');
        $integrity = (bool) $this->config->get('media-viewer.assets.integrity', true);
        $flags = JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR;

        $config = [
            'pdfjs' => [
                'lib' => $pdfjs.'/build/pdf.min.mjs',
                'viewer' => $pdfjs.'/web/pdf_viewer.mjs',
                'worker' => $pdfjs.'/build/pdf.worker.min.mjs',
                'workerIntegrity' => $integrity ? $pinned['pdfjs']['files']['build/pdf.worker.min.mjs'] : null,
                'css' => $pdfjs.'/web/pdf_viewer.css',
                'cssIntegrity' => $integrity ? $pinned['pdfjs']['files']['web/pdf_viewer.css'] : null,
                'base' => $pdfjs,
            ],
            'videojs' => [
                'video' => $videojs.'/video.js',
                'audio' => $videojs.'/audio.js',
                'hls' => $videojs.'/media/hlsjs-video.js',
            ],
            'text' => trans('media-viewer::viewer.runtime'),
        ];

        $html = '';

        if ($integrity) {
            $map = [];
            foreach (['pdfjs' => $pdfjs, 'videojs' => $videojs] as $library => $base) {
                foreach ($pinned[$library]['files'] as $file => $hash) {
                    if (str_ends_with($file, '.mjs') || str_ends_with($file, '.js')) {
                        $map[$base.'/'.$file] = $hash;
                    }
                }
            }
            $html .= '<script type="importmap">'.json_encode(['integrity' => $map], $flags).'</script>'."\n";
        }

        $html .= '<script type="application/json" id="media-viewer-config">'.json_encode($config, $flags).'</script>'."\n";
        $html .= '<link rel="stylesheet" href="'.e($this->own('media-viewer.css')).'" integrity="'.self::hash('media-viewer.css').'">'."\n";
        $html .= '<script type="module" src="'.e($this->own('media-viewer.js')).'" integrity="'.self::hash('media-viewer.js').'"></script>'."\n";

        return new HtmlString($html);
    }

    /** URL of one of the package's own files, versioned by its hash. */
    public function own(string $file): string
    {
        return $this->url->to($this->url->route('media-viewer.asset', ['file' => $file, 'id' => substr(md5(self::hash($file)), 0, 12)], false));
    }

    public static function path(string $file): ?string
    {
        return match ($file) {
            'media-viewer.js' => __DIR__.'/../../resources/js/media-viewer.js',
            'media-viewer.css' => __DIR__.'/../../resources/css/media-viewer.css',
            default => null,
        };
    }

    public static function hash(string $file): string
    {
        return self::$hashes[$file] ??= 'sha384-'.base64_encode(hash_file('sha384', (string) self::path($file), true));
    }
}
