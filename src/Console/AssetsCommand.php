<?php

namespace AmjadIqbal\MediaViewer\Console;

use AmjadIqbal\MediaViewer\Support\Assets;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Copies pdf.js and Video.js from node_modules into public/ for
 * MEDIA_VIEWER_ASSETS=local, and refuses files whose hash doesn't match
 * the versions this package was tested with. No network access.
 */
class AssetsCommand extends Command
{
    protected $signature = 'media-viewer:assets {--node-modules= : Path to node_modules (default: base_path("node_modules"))}';

    protected $description = 'Copy the pinned pdf.js and Video.js files from node_modules to public/ for self-hosting';

    public function handle(Filesystem $files): int
    {
        $nodeModules = rtrim((string) ($this->option('node-modules') ?: base_path('node_modules')), '/');
        $target = public_path(trim((string) config('media-viewer.assets.local_path'), '/'));
        $pinned = Assets::pinned();

        $sources = [
            'pdfjs' => $nodeModules.'/'.$pinned['pdfjs']['package'],
            'videojs' => $nodeModules.'/'.$pinned['videojs']['package'],
        ];

        foreach ($sources as $library => $source) {
            $version = json_decode((string) @file_get_contents($source.'/package.json'), true)['version'] ?? null;
            if ($version !== $pinned[$library]['version']) {
                $this->components->error(sprintf(
                    '%s %s not found in %s (found: %s). Run: npm i -D %s',
                    $pinned[$library]['package'],
                    $pinned[$library]['version'],
                    $nodeModules,
                    $version ?? 'nothing',
                    'pdfjs-dist@'.$pinned['pdfjs']['version'].' @videojs/cdn@'.$pinned['videojs']['version'],
                ));

                return self::FAILURE;
            }

            foreach ($pinned[$library]['files'] as $file => $hash) {
                $actual = 'sha384-'.base64_encode(hash_file('sha384', $source.'/'.$file, true));
                if ($actual !== $hash) {
                    $this->components->error("{$library}/{$file} does not match its pinned hash.");

                    return self::FAILURE;
                }
            }
        }

        $files->deleteDirectory($target);

        // pdf.js: the hashed modules plus the data the worker fetches on demand.
        foreach (['build', 'cmaps', 'standard_fonts', 'wasm', 'iccs', 'web/images'] as $directory) {
            if ($files->isDirectory($sources['pdfjs'].'/'.$directory)) {
                $files->copyDirectory($sources['pdfjs'].'/'.$directory, $target.'/pdfjs/'.$directory);
            }
        }
        foreach (['web/pdf_viewer.mjs', 'web/pdf_viewer.css'] as $file) {
            $files->ensureDirectoryExists(dirname($target.'/pdfjs/'.$file));
            $files->copy($sources['pdfjs'].'/'.$file, $target.'/pdfjs/'.$file);
        }

        // Video.js: exactly the module graph the runtime can load.
        foreach (array_keys($pinned['videojs']['files']) as $file) {
            $files->ensureDirectoryExists(dirname($target.'/videojs/'.$file));
            $files->copy($sources['videojs'].'/'.$file, $target.'/videojs/'.$file);
        }

        $this->components->info(sprintf(
            'Copied pdf.js %s and Video.js %s to %s. Set MEDIA_VIEWER_ASSETS=local to use them.',
            $pinned['pdfjs']['version'],
            $pinned['videojs']['version'],
            $target,
        ));

        return self::SUCCESS;
    }
}
