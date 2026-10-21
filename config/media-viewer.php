<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disks that may be shown
    |--------------------------------------------------------------------------
    |
    | Only files on these disks can be rendered or streamed. Anything else is
    | refused, even with a valid signature. Add 's3' (or any other disk) here
    | when you need it.
    |
    */

    'disks' => ['local', 'public'],

    /*
    |--------------------------------------------------------------------------
    | Allowed file extensions
    |--------------------------------------------------------------------------
    |
    | The stream route serves only these extensions, with the Content-Type
    | listed here (the disk's own guess is never trusted). This stops the
    | route from serving HTML or SVG from your own origin. HLS needs m3u8 plus
    | its segment, init and key files.
    |
    */

    'extensions' => [
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4',
        'm4v' => 'video/mp4',
        'mov' => 'video/quicktime',
        'webm' => 'video/webm',
        'ogv' => 'video/ogg',
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'aac' => 'audio/aac',
        'oga' => 'audio/ogg',
        'ogg' => 'audio/ogg',
        'opus' => 'audio/ogg',
        'wav' => 'audio/wav',
        'flac' => 'audio/flac',
        'm3u8' => 'application/vnd.apple.mpegurl',
        'ts' => 'video/mp2t',
        'm4s' => 'video/iso.segment',
        'key' => 'application/octet-stream',
        'vtt' => 'text/vtt',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Signed URLs
    |--------------------------------------------------------------------------
    |
    | Files on a disk are served through a signed route that expires after
    | "ttl" minutes. Keep it longer than your longest video: the player makes
    | new Range requests with the same URL while seeking.
    |
    | "bind_to_user": when someone is logged in, the URL also carries their
    | user id and only works for that user, so a copied link is useless to
    | anyone else. Guests get a URL that works for whoever holds it until it
    | expires.
    |
    | "gate": the name of a Gate ability, checked with ($user, $disk, $path)
    | before a viewer is rendered and again on every request. Null skips it.
    | Gate::define('viewMedia', fn (?User $user, string $disk, string $path) => ...);
    |
    */

    'ttl' => (int) env('MEDIA_VIEWER_TTL', 120),

    'bind_to_user' => true,

    'gate' => null,

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    |
    | "stream": the app streams every file itself, with Range support. Works
    | for every disk and keeps the storage private.
    |
    | "redirect": after the checks above, the route redirects to the disk's
    | own temporary URL (S3 and friends). Saves your server's bandwidth; the
    | bucket needs CORS for GET with the Range header, because pdf.js and
    | hls.js fetch the file. Disks without temporary URLs are still streamed,
    | and HLS playlists are always rewritten by the app.
    |
    */

    'delivery' => env('MEDIA_VIEWER_DELIVERY', 'stream'),

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    */

    'route' => [
        'prefix' => 'media-viewer',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Browser assets
    |--------------------------------------------------------------------------
    |
    | "cdn" loads pdf.js 6.4.299 and Video.js 10.1.0 from jsDelivr. "local"
    | serves them from public/vendor/media-viewer, after:
    |
    |     npm i -D pdfjs-dist@6.4.299 @videojs/cdn@10.1.0
    |     php artisan media-viewer:assets
    |
    | "integrity" prints an import map with a sha384 hash for every module
    | file, so a changed file is refused by the browser.
    |
    | "inject": the first viewer on a page prints these tags itself. To put
    | them in your layout's <head> instead (best for the import map, and
    | needed when a viewer first appears after a Livewire action), add
    | @mediaViewerAssets there and set this to false.
    |
    */

    'assets' => [
        'source' => env('MEDIA_VIEWER_ASSETS', 'cdn'),
        'cdn' => 'https://cdn.jsdelivr.net/npm',
        'local_path' => 'vendor/media-viewer',
        'integrity' => true,
        'inject' => (bool) env('MEDIA_VIEWER_INJECT_ASSETS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Viewer defaults
    |--------------------------------------------------------------------------
    |
    | "download": show a download button in the PDF toolbar. A viewer is not
    | DRM: the browser that is allowed to view a file can always save it.
    |
    */

    'pdf' => [
        'height' => '80vh',
        'scale' => 'page-width',
        'download' => false,
    ],

];
