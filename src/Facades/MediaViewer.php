<?php

namespace AmjadIqbal\MediaViewer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string url(string $disk, string $path, ?int $ttlMinutes = null)
 * @method static bool allows(string $disk, string $path)
 * @method static string|null kind(string $path)
 *
 * @see \AmjadIqbal\MediaViewer\MediaViewer
 */
class MediaViewer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AmjadIqbal\MediaViewer\MediaViewer::class;
    }
}
