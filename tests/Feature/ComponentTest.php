<?php

use AmjadIqbal\MediaViewer\Livewire\MediaGallery;
use AmjadIqbal\MediaViewer\Support\Assets;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('renders the pdf viewer with a signed url and prints assets once', function () {
    $html = Blade::render('<x-media-viewer::pdf disk="local" path="docs/a.pdf" height="60vh" /><x-media-viewer::pdf disk="local" path="docs/b.pdf" />');

    expect($html)
        ->toContain('data-mv="pdf"')
        ->toContain('/media-viewer/file/local/docs/a.pdf?expires=')
        ->toContain('signature=')
        ->toContain('--mv-height: 60vh')
        ->toContain('data-mv-search')
        ->not->toContain('download=');
    expect(substr_count($html, 'id="media-viewer-config"'))->toBe(1);
    expect(substr_count($html, '<script type="importmap">'))->toBe(1);
});

it('prints an import map with the pinned hashes', function () {
    $html = (string) app(Assets::class)->render();
    $pinned = Assets::pinned();

    expect($html)
        ->toContain('https://cdn.jsdelivr.net/npm/pdfjs-dist@'.$pinned['pdfjs']['version'].'/build/pdf.min.mjs')
        ->toContain($pinned['pdfjs']['files']['build/pdf.min.mjs'])
        ->toContain('https://cdn.jsdelivr.net/npm/@videojs/cdn@'.$pinned['videojs']['version'].'/media/hlsjs-video.js')
        ->toContain($pinned['videojs']['files']['media/hlsjs-video.js'])
        ->toContain('integrity="'.Assets::hash('media-viewer.js').'"');
});

it('points at local copies when assets are self-hosted', function () {
    config(['media-viewer.assets.source' => 'local']);

    expect((string) app(Assets::class)->render())->toContain('http://localhost/vendor/media-viewer/pdfjs/build/pdf.min.mjs');
});

it('prints assets from the directive even after a component did, and not from components when inject is off', function () {
    // Content renders before the layout: the component prints first, the <head> directive still prints.
    expect(substr_count(Blade::render('<x-media-viewer::pdf disk="local" path="docs/a.pdf" />@mediaViewerAssets'), 'id="media-viewer-config"'))->toBe(2);

    config(['media-viewer.assets.inject' => false]);
    app()->forgetScopedInstances();

    $html = Blade::render('@mediaViewerAssets<x-media-viewer::pdf disk="local" path="docs/a.pdf" />');
    expect(substr_count($html, 'id="media-viewer-config"'))->toBe(1);
    expect(strpos($html, 'importmap'))->toBeLessThan(strpos($html, 'data-mv="pdf"'));
});

it('can drop the import map', function () {
    config(['media-viewer.assets.integrity' => false]);

    expect((string) app(Assets::class)->render())->not->toContain('importmap')->toContain('"workerIntegrity":null');
});

it('does not print assets inside a livewire update', function () {
    request()->headers->set('X-Livewire', '1');

    expect(Blade::render('<x-media-viewer::pdf disk="local" path="docs/a.pdf" />'))
        ->toContain('data-mv="pdf"')
        ->not->toContain('media-viewer-config');
});

it('shows the download button only when asked', function () {
    expect(Blade::render('<x-media-viewer::pdf disk="local" path="docs/a.pdf" :download="true" />'))->toContain('download="a.pdf"');
});

it('renders a denied box and no url when the file may not be shown', function () {
    config(['media-viewer.gate' => 'viewMedia']);
    Gate::define('viewMedia', fn ($user = null) => false);

    $html = Blade::render('<x-media-viewer::pdf disk="local" path="docs/a.pdf" />');

    expect($html)->toContain('This file is not available.')->not->toContain('/media-viewer/file/');
});

it('refuses disks and extensions outside the allow-lists', function (string $blade) {
    expect(Blade::render($blade))->toContain('This file is not available.')->not->toContain('/media-viewer/file/');
})->with([
    '<x-media-viewer::pdf disk="secret" path="a.pdf" />',
    '<x-media-viewer::video disk="local" path="a.html" />',
    '<x-media-viewer::pdf disk="local" path="../.env" />',
]);

it('renders a video, an hls stream and audio with video.js markup', function () {
    expect(Blade::render('<x-media-viewer::video disk="local" path="v/intro.mp4" poster-path="v/intro.jpg" muted />'))
        ->toContain('<video-player>')
        ->toContain('data-mv="video"')
        ->toContain('<video src="http://localhost/media-viewer/file/local/v/intro.mp4?')
        ->toContain('poster="http://localhost/media-viewer/file/local/v/intro.jpg?')
        ->toContain(' muted');

    expect(Blade::render('<x-media-viewer::video disk="local" path="hls/index.m3u8" />'))
        ->toContain('data-mv="hls"')
        ->toContain('<hlsjs-video src="http://localhost/media-viewer/file/local/hls/index.m3u8?');

    expect(Blade::render('<x-media-viewer::audio disk="local" path="a/ep1.mp3" />'))
        ->toContain('<audio-player>')
        ->toContain('<audio src="http://localhost/media-viewer/file/local/a/ep1.mp3?');
});

it('accepts a plain src url', function () {
    expect(Blade::render('<x-media-viewer::video src="https://cdn.example.com/live/stream.m3u8" />'))
        ->toContain('<hlsjs-video src="https://cdn.example.com/live/stream.m3u8"');
});

it('picks the right viewer from the extension', function () {
    expect(Blade::render('<x-media-viewer::file disk="local" path="x.pdf" />'))->toContain('data-mv="pdf"');
    expect(Blade::render('<x-media-viewer::file disk="local" path="x.webm" />'))->toContain('data-mv="video"');
    expect(Blade::render('<x-media-viewer::file disk="local" path="x.flac" />'))->toContain('data-mv="audio"');
    expect(Blade::render('<x-media-viewer::file disk="local" path="x.docx" />'))->toContain('cannot be previewed');
});

it('renders a lazy pdf thumbnail', function () {
    expect(Blade::render('<x-media-viewer::pdf-thumbnail disk="local" path="docs/a.pdf" :width="120" href="/docs/1" />'))
        ->toContain('<a ')
        ->toContain('href="/docs/1"')
        ->toContain('data-mv="pdf-thumb"')
        ->toContain('data-mv-width="120"');
});

it('serves its own runtime with the hash it advertises', function () {
    $response = $this->get(app(Assets::class)->own('media-viewer.js'));

    $response->assertOk()->assertHeader('Content-Type', 'text/javascript; charset=utf-8');
    expect('sha384-'.base64_encode(hash('sha384', $response->getFile()->getContent(), true)))->toBe(Assets::hash('media-viewer.js'));

    $this->get('/media-viewer/assets/composer.json')->assertNotFound();
});

it('lets the livewire gallery switch files but not swap in others', function () {
    Livewire::test(MediaGallery::class, ['disk' => 'local', 'paths' => ['docs/a.pdf', 'v/intro.mp4']])
        ->assertSeeHtml('data-mv="pdf"')
        ->call('select', 1)
        ->assertSet('selected', 1)
        ->assertSeeHtml('data-mv="video"')
        ->call('select', 9)
        ->assertSet('selected', 1);

    expect(fn () => Livewire::test(MediaGallery::class, ['disk' => 'local', 'paths' => ['docs/a.pdf']])->set('paths', ['../../.env']))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('adds itself to artisan about', function () {
    $this->artisan('about', ['--only' => 'media_viewer'])->expectsOutputToContain('6.4.299 / 10.1.0')->assertSuccessful();
});

it('refuses to copy assets that do not match the pinned versions', function () {
    $modules = sys_get_temp_dir().'/mv-node-'.getmypid();
    @mkdir($modules.'/pdfjs-dist', 0777, true);
    file_put_contents($modules.'/pdfjs-dist/package.json', '{"version":"1.0.0"}');

    $this->artisan('media-viewer:assets', ['--node-modules' => $modules])->assertFailed();

    (new Filesystem)->deleteDirectory($modules);
});
