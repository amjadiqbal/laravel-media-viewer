<?php

use AmjadIqbal\MediaViewer\Facades\MediaViewer;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    Storage::disk('local')->put('docs/report.pdf', '%PDF-'.str_repeat('0123456789', 100));
    Storage::disk('local')->put('docs/page.html', '<script>alert(1)</script>');
});

function user(int $id): User
{
    $user = new User;
    $user->forceFill(['id' => $id]);

    return $user;
}

it('streams a file through a signed url', function () {
    $response = $this->get(MediaViewer::url('local', 'docs/report.pdf'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Length', '1005')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe(Storage::disk('local')->get('docs/report.pdf'));
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;');
});

it('answers a range request with 206 and exactly those bytes', function () {
    $response = $this->get(MediaViewer::url('local', 'docs/report.pdf'), ['Range' => 'bytes=5-14']);

    $response->assertStatus(206)
        ->assertHeader('Content-Range', 'bytes 5-14/1005')
        ->assertHeader('Content-Length', '10');
    expect($response->streamedContent())->toBe('0123456789');
});

it('answers a suffix range', function () {
    $response = $this->get(MediaViewer::url('local', 'docs/report.pdf'), ['Range' => 'bytes=-3']);

    $response->assertStatus(206)->assertHeader('Content-Range', 'bytes 1002-1004/1005');
    expect($response->streamedContent())->toBe('789');
});

it('answers an unsatisfiable range with 416', function () {
    $this->get(MediaViewer::url('local', 'docs/report.pdf'), ['Range' => 'bytes=5000-'])
        ->assertStatus(416)
        ->assertHeader('Content-Range', 'bytes */1005');
});

it('sends the whole file when If-Range no longer matches', function () {
    $this->get(MediaViewer::url('local', 'docs/report.pdf'), ['Range' => 'bytes=0-1', 'If-Range' => '"stale"'])
        ->assertOk()
        ->assertHeader('Content-Length', '1005');
});

it('answers 304 when the etag matches', function () {
    $url = MediaViewer::url('local', 'docs/report.pdf');
    $etag = $this->get($url)->headers->get('ETag');

    $this->get($url, ['If-None-Match' => $etag])->assertStatus(304);
});

it('answers HEAD without a body', function () {
    $response = $this->call('HEAD', MediaViewer::url('local', 'docs/report.pdf'));

    $response->assertOk()->assertHeader('Content-Length', '1005');
    expect($response->getContent())->toBe('');
});

it('refuses an unsigned or tampered url', function () {
    $this->get('/media-viewer/file/local/docs/report.pdf')->assertForbidden();

    $url = MediaViewer::url('local', 'docs/report.pdf');
    $this->get(str_replace('report.pdf', 'other.pdf', $url))->assertForbidden();
    $this->get(str_replace('/local/', '/public/', $url))->assertForbidden();
});

it('refuses an expired url', function () {
    $url = MediaViewer::url('local', 'docs/report.pdf', 5);

    $this->travel(6)->minutes();

    $this->get($url)->assertForbidden();
});

it('never serves extensions outside the allow-list, even when signed', function () {
    expect(fn () => MediaViewer::url('local', 'docs/page.html'))->toThrow(InvalidArgumentException::class);

    $url = URL::temporarySignedRoute('media-viewer.file', now()->addHour(), ['disk' => 'local', 'path' => 'docs/page.html'], absolute: false);
    $this->get($url)->assertNotFound();
});

it('never serves disks outside the allow-list, even when signed', function () {
    Storage::disk('secret')->put('a.pdf', '%PDF-');
    $url = URL::temporarySignedRoute('media-viewer.file', now()->addHour(), ['disk' => 'secret', 'path' => 'a.pdf'], absolute: false);

    $this->get($url)->assertNotFound();
});

it('refuses path traversal, even when signed', function () {
    $url = URL::temporarySignedRoute('media-viewer.file', now()->addHour(), ['disk' => 'local', 'path' => 'docs/../../x.pdf'], absolute: false);

    $this->get($url)->assertNotFound();
});

it('answers 404 for a missing file', function () {
    $this->get(MediaViewer::url('local', 'docs/missing.pdf'))->assertNotFound();
});

it('makes the browser revalidate before reusing a cached copy', function () {
    $cacheControl = $this->get(MediaViewer::url('local', 'docs/report.pdf'))->headers->get('Cache-Control');

    expect($cacheControl)->toContain('no-cache')->toContain('private')->not->toContain('max-age');
});

it('binds the url to the user who saw the viewer', function () {
    $this->actingAs(user(1));
    $url = MediaViewer::url('local', 'docs/report.pdf');
    expect($url)->toContain('u=1');

    $this->get($url)->assertOk();
    $this->actingAs(user(2))->get($url)->assertForbidden();
    auth()->logout();
    $this->get($url)->assertForbidden();
});

it('can leave urls unbound', function () {
    config(['media-viewer.bind_to_user' => false]);
    $this->actingAs(user(1));
    $url = MediaViewer::url('local', 'docs/report.pdf');

    $this->actingAs(user(2))->get($url)->assertOk();
});

it('checks the gate on every request', function () {
    config(['media-viewer.gate' => 'viewMedia']);
    $allowed = true;
    Gate::define('viewMedia', function (?User $user, string $disk, string $path) use (&$allowed) {
        return $allowed && $disk === 'local' && str_starts_with($path, 'docs/');
    });

    $url = MediaViewer::url('local', 'docs/report.pdf');
    $this->get($url)->assertOk();

    $allowed = false;
    $this->get($url)->assertForbidden();
    expect(MediaViewer::allows('local', 'docs/report.pdf'))->toBeFalse();
});

it('handles file names with spaces and unicode', function () {
    Storage::disk('local')->put('docs/Q3 report – final.pdf', '%PDF-x');

    $this->get(MediaViewer::url('local', 'docs/Q3 report – final.pdf'))->assertOk();
});

it('redirects to the disk temporary url in redirect mode', function () {
    config(['media-viewer.delivery' => 'redirect']);
    Storage::disk('local')->buildTemporaryUrlsUsing(fn (string $path, $expiration) => 'https://bucket.example.com/'.$path.'?exp='.$expiration->getTimestamp());

    $response = $this->get(MediaViewer::url('local', 'docs/report.pdf'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://bucket.example.com/docs/report.pdf?exp=');
});
