<?php

namespace AmjadIqbal\MediaViewer\Http\Controllers;

use AmjadIqbal\MediaViewer\Support\Assets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the package's own runtime and stylesheet straight from the package,
 * so nothing has to be published or built for them.
 */
class AssetController
{
    public function __invoke(string $file): BinaryFileResponse
    {
        $path = Assets::path($file);
        abort_if($path === null, 404);

        return new BinaryFileResponse($path, 200, [
            'Content-Type' => str_ends_with($file, '.css') ? 'text/css; charset=utf-8' : 'text/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ], true, null, false, true);
    }
}
