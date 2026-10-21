<?php

namespace AmjadIqbal\MediaViewer\Support;

use Closure;

/**
 * Rewrites an HLS playlist so every relative URI in it (segments, variant
 * playlists, keys, init maps, renditions) becomes its own signed URL. Without
 * this, a segment next to a signed playlist would resolve to an unsigned URL.
 */
class Playlist
{
    /**
     * @param  Closure(string): ?string  $sign  receives a disk path, returns a URL or null to leave the URI alone
     */
    public static function rewrite(string $contents, string $playlistPath, Closure $sign): string
    {
        $directory = str_contains($playlistPath, '/') ? substr($playlistPath, 0, strrpos($playlistPath, '/')) : '';
        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];

        foreach ($lines as $i => $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            if (str_starts_with($trimmed, '#')) {
                if (str_starts_with($trimmed, '#EXT') && str_contains($trimmed, 'URI="')) {
                    $lines[$i] = (string) preg_replace_callback(
                        '/URI="([^"]*)"/',
                        fn (array $m) => 'URI="'.(self::signUri($m[1], $directory, $sign) ?? $m[1]).'"',
                        $line,
                    );
                }

                continue;
            }

            $lines[$i] = self::signUri($trimmed, $directory, $sign) ?? $line;
        }

        return implode("\n", $lines);
    }

    /** @param  Closure(string): ?string  $sign */
    protected static function signUri(string $uri, string $directory, Closure $sign): ?string
    {
        // Absolute URLs (CDN segments, skd:// FairPlay keys, data: URIs) are left alone.
        if ($uri === '' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $uri) || str_starts_with($uri, '//')) {
            return null;
        }

        $path = rawurldecode((string) preg_replace('/[?#].*$/', '', $uri));
        $path = str_starts_with($path, '/') ? $path : $directory.'/'.$path;

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                if ($parts === []) {
                    return null;
                }
                array_pop($parts);

                continue;
            }
            $parts[] = $part;
        }

        return $parts === [] ? null : $sign(implode('/', $parts));
    }
}
