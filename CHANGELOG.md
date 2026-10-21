# Changelog

All notable changes to `laravel-media-viewer` are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - unreleased

### Added

- `<x-media-viewer::pdf>`: pdf.js 6.4.299 viewer with page navigation, zoom (steps, page
  width, whole page), text search with match count and highlighting, full screen and an
  optional download button.
- `<x-media-viewer::pdf-thumbnail>`: lazy first-page (or any page) thumbnails that fetch only
  the bytes they need through Range requests.
- `<x-media-viewer::video>` and `<x-media-viewer::audio>`: Video.js 10.1.0 players; `.m3u8`
  sources play through Video.js's hls.js adapter.
- `<x-media-viewer::file>`: picks the right viewer from the extension.
- `<livewire:media-viewer-gallery>`: file list with thumbnails and a viewer, locked paths.
- Signed, expiring, user-bound URLs for files on any allowed disk, an optional Gate ability
  checked on every request, an extension allow-list with fixed Content-Types, Range
  streaming (206/416, If-Range, ETag), HLS playlist rewriting, and a redirect mode for
  disks with temporary URLs.
- Subresource Integrity for every pdf.js and Video.js module through an import map, the
  pdf.js worker fetched with its hash, and `php artisan media-viewer:assets` for
  self-hosting the pinned files.
- `php artisan about` section.
