<?php

namespace AmjadIqbal\MediaViewer\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a file from any Laravel disk with single-range support (RFC 9110):
 * 206 for a satisfiable range, 416 for an unsatisfiable one, 200 otherwise.
 */
class FileResponse
{
    public const CHUNK = 1048576;

    public static function make(Request $request, Filesystem $disk, string $path, string $contentType, int $maxAge): Response
    {
        $size = $disk->size($path);
        $modified = $disk->lastModified($path);
        $etag = '"'.hash('xxh128', $path.'|'.$size.'|'.$modified).'"';
        $lastModified = gmdate('D, d M Y H:i:s', $modified).' GMT';

        $headers = [
            'Content-Type' => $contentType,
            'Accept-Ranges' => 'bytes',
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
            'Cache-Control' => 'private, max-age='.max(0, $maxAge),
            'Content-Disposition' => self::disposition($path),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        if (self::notModified($request, $etag, $modified)) {
            return new Response('', 304, array_diff_key($headers, ['Content-Type' => 1, 'Content-Disposition' => 1]));
        }

        $range = self::wantsRange($request, $etag, $lastModified) ? self::parseRange($request->headers->get('Range'), $size) : null;

        if ($range === false) {
            return new Response('', 416, $headers + ['Content-Range' => "bytes */{$size}"]);
        }

        [$start, $end] = $range ?? [0, $size - 1];
        $length = $size === 0 ? 0 : $end - $start + 1;
        $status = $range === null ? 200 : 206;

        $headers['Content-Length'] = (string) $length;
        if ($range !== null) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        if ($request->isMethod('HEAD') || $length === 0) {
            return new Response('', $status, $headers);
        }

        return new StreamedResponse(function () use ($disk, $path, $start, $length): void {
            $stream = $disk->readStream($path);
            if (! is_resource($stream)) {
                return;
            }

            self::skip($stream, $start);

            $remaining = $length;
            while ($remaining > 0 && ! feof($stream)) {
                $chunk = fread($stream, max(1, min(self::CHUNK, $remaining)));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                $remaining -= strlen($chunk);
                flush();
                if (connection_aborted()) {
                    break;
                }
            }

            fclose($stream);
        }, $status, $headers);
    }

    /**
     * @return array{0: int, 1: int}|false|null [start, end] for a satisfiable range,
     *                                          false for an unsatisfiable one, null to send the whole file
     */
    public static function parseRange(?string $header, int $size): array|false|null
    {
        // Multiple ranges, other units or bad syntax: ignore the header (allowed by RFC 9110).
        if ($header === null || ! preg_match('/^bytes\s*=\s*(\d*)\s*-\s*(\d*)\s*$/i', $header, $m)) {
            return null;
        }

        [, $first, $last] = $m;

        if ($first === '' && $last === '') {
            return null;
        }

        if ($first === '') {
            $suffix = (int) $last;
            if ($suffix === 0 || $size === 0) {
                return false;
            }

            return [max(0, $size - $suffix), $size - 1];
        }

        $start = (int) $first;
        if ($last !== '' && (int) $last < $start) {
            return null;
        }
        if ($start >= $size) {
            return false;
        }

        return [$start, $last === '' ? $size - 1 : min((int) $last, $size - 1)];
    }

    protected static function wantsRange(Request $request, string $etag, string $lastModified): bool
    {
        if (! $request->headers->has('Range')) {
            return false;
        }

        $ifRange = $request->headers->get('If-Range');

        return $ifRange === null || $ifRange === $etag || $ifRange === $lastModified;
    }

    protected static function notModified(Request $request, string $etag, int $modified): bool
    {
        $ifNoneMatch = $request->headers->get('If-None-Match');
        if ($ifNoneMatch !== null) {
            return in_array($etag, array_map('trim', explode(',', $ifNoneMatch)), true) || trim($ifNoneMatch) === '*';
        }

        $since = $request->headers->get('If-Modified-Since');

        return $since !== null && ($time = strtotime($since)) !== false && $time >= $modified;
    }

    /** @param resource $stream */
    protected static function skip($stream, int $offset): void
    {
        if ($offset === 0) {
            return;
        }

        if (stream_get_meta_data($stream)['seekable'] && fseek($stream, $offset) === 0) {
            return;
        }

        // Remote disks often return non-seekable streams: read past the offset instead.
        while ($offset > 0 && ! feof($stream)) {
            $read = fread($stream, max(1, min(self::CHUNK, $offset)));
            if ($read === false || $read === '') {
                return;
            }
            $offset -= strlen($read);
        }
    }

    protected static function disposition(string $path): string
    {
        $name = basename($path);
        $fallback = preg_replace('/[^\x20-\x7e]|[\/\\\\%"]/', '_', $name) ?: 'file';

        return HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $name, $fallback);
    }
}
