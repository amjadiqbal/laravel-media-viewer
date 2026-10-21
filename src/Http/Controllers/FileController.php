<?php

namespace AmjadIqbal\MediaViewer\Http\Controllers;

use AmjadIqbal\MediaViewer\MediaViewer;
use AmjadIqbal\MediaViewer\Support\FileResponse;
use AmjadIqbal\MediaViewer\Support\Playlist;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class FileController
{
    /** Playlists larger than this are refused rather than read into memory. */
    public const MAX_PLAYLIST_BYTES = 5242880;

    public function __invoke(Request $request, MediaViewer $viewer, Config $config, string $disk, string $path): Response
    {
        $clean = $viewer->normalizePath($path);

        if ($status = $viewer->refuse($request, $disk, $clean)) {
            abort($status);
        }

        /** @var string $clean */
        /** @var FilesystemAdapter $filesystem */
        $filesystem = Storage::disk($disk);

        try {
            $exists = $filesystem->fileExists($clean);
        } catch (Throwable) {
            $exists = false;
        }
        abort_unless($exists, 404);

        $expires = (int) $request->query('expires');
        $maxAge = max(0, $expires - time());

        if ($viewer->extension($clean) === 'm3u8') {
            abort_if($filesystem->size($clean) > self::MAX_PLAYLIST_BYTES, 413);

            $user = $request->query('u');
            $playlist = Playlist::rewrite(
                (string) $filesystem->get($clean),
                $clean,
                function (string $target) use ($viewer, $disk, $expires, $user): ?string {
                    if ($viewer->contentType($target) === null) {
                        return null;
                    }

                    return $viewer->signedUrl($disk, $target, Date::createFromTimestamp($expires), is_string($user) ? $user : null);
                },
            );

            return new Response($playlist, 200, [
                'Content-Type' => 'application/vnd.apple.mpegurl',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        if ($config->get('media-viewer.delivery') === 'redirect' && $filesystem->providesTemporaryUrls()) {
            return redirect()->away($filesystem->temporaryUrl($clean, Date::createFromTimestamp($expires)), 302, [
                'Cache-Control' => 'no-store',
            ]);
        }

        return FileResponse::make($request, $filesystem, $clean, (string) $viewer->contentType($clean), $maxAge);
    }
}
