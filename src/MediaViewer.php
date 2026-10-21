<?php

namespace AmjadIqbal\MediaViewer;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

/**
 * Decides which files may be shown and signs the URLs that serve them.
 */
class MediaViewer
{
    public const VIDEO = ['mp4', 'm4v', 'mov', 'webm', 'ogv'];

    public const AUDIO = ['mp3', 'm4a', 'aac', 'oga', 'ogg', 'opus', 'wav', 'flac'];

    public function __construct(
        protected Config $config,
        protected UrlGenerator $url,
        protected Gate $gate,
        protected AuthFactory $auth,
    ) {}

    /**
     * Turns a disk path into a clean relative path, or null when it tries to
     * leave the disk root ("..") or is otherwise unusable.
     */
    public function normalizePath(string $path): ?string
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return null;
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return null;
            }
            $parts[] = $part;
        }

        return $parts === [] ? null : implode('/', $parts);
    }

    public function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /** The Content-Type the stream route sends, or null when the extension isn't allowed. */
    public function contentType(string $path): ?string
    {
        $types = (array) $this->config->get('media-viewer.extensions', []);
        $type = $types[$this->extension($path)] ?? null;

        return is_string($type) ? $type : null;
    }

    /** pdf, video, hls, audio or null. */
    public function kind(string $path): ?string
    {
        $extension = $this->extension($path);

        return match (true) {
            $extension === 'pdf' => 'pdf',
            $extension === 'm3u8' => 'hls',
            in_array($extension, self::VIDEO, true) => 'video',
            in_array($extension, self::AUDIO, true) => 'audio',
            default => null,
        };
    }

    public function diskAllowed(string $disk): bool
    {
        return in_array($disk, (array) $this->config->get('media-viewer.disks', []), true);
    }

    /**
     * Whether the current user may see this file: allowed disk, allowed
     * extension, a path inside the disk, and the configured Gate ability.
     */
    public function allows(string $disk, string $path): bool
    {
        $path = $this->normalizePath($path);

        if ($path === null || ! $this->diskAllowed($disk) || $this->contentType($path) === null) {
            return false;
        }

        $ability = $this->config->get('media-viewer.gate');

        return ! is_string($ability) || $this->gate->allows($ability, [$disk, $path]);
    }

    /**
     * A signed, expiring URL for a file on a disk. It does not check the Gate:
     * call allows() first (the components do).
     */
    public function url(string $disk, string $path, ?int $ttlMinutes = null): string
    {
        $user = $this->config->get('media-viewer.bind_to_user', true) ? $this->auth->guard()->id() : null;
        $expires = Date::now()->addMinutes($ttlMinutes ?? (int) $this->config->get('media-viewer.ttl', 120));

        return $this->signedUrl($disk, $path, $expires, $user === null ? null : (string) $user);
    }

    public function signedUrl(string $disk, string $path, DateTimeInterface $expires, ?string $user): string
    {
        $clean = $this->normalizePath($path);

        if ($clean === null || ! $this->diskAllowed($disk) || $this->contentType($clean) === null) {
            throw new InvalidArgumentException("Media viewer cannot serve [{$path}] from disk [{$disk}].");
        }

        $parameters = ['disk' => $disk, 'path' => $clean];
        if ($user !== null) {
            $parameters['u'] = $user;
        }

        // Signed without scheme and host, so it survives proxies and alternate domains;
        // to() then puts the app's root (and any sub-folder) back in front.
        return $this->url->to($this->url->temporarySignedRoute('media-viewer.file', $expires, $parameters, absolute: false));
    }

    /**
     * Checks a request to the stream route. Returns null when it may be served,
     * otherwise the HTTP status to answer with.
     */
    public function refuse(Request $request, string $disk, ?string $path): ?int
    {
        if (! $request->hasValidSignature(absolute: false)) {
            return 403;
        }

        if ($path === null || ! $this->diskAllowed($disk) || $this->contentType($path) === null) {
            return 404;
        }

        $user = $request->query('u');
        if ($user !== null && (string) $this->auth->guard()->id() !== $user) {
            return 403;
        }

        $ability = $this->config->get('media-viewer.gate');
        if (is_string($ability) && ! $this->gate->allows($ability, [$disk, $path])) {
            return 403;
        }

        return null;
    }
}
