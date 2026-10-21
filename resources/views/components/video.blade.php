@if (! $allowed)
    @include('media-viewer::components.denied')
@else
{{ $assets() }}
<div {{ $attributes->class('mv mv-video') }} wire:key="mv-{{ $key }}" data-mv="{{ $hls ? 'hls' : 'video' }}">
    <div wire:ignore>
        <video-player>
            <video-skin class="mv-video__skin" style="display: block; width: 100%; aspect-ratio: {{ $aspectRatio }};">
                @if ($hls)
                    <hlsjs-video src="{{ $url }}" playsinline preload="{{ $preload }}" @if ($posterUrl) poster="{{ $posterUrl }}" @endif @if ($autoplay) autoplay @endif @if ($muted) muted @endif @if ($loop) loop @endif></hlsjs-video>
                @else
                    <video src="{{ $url }}" playsinline preload="{{ $preload }}" @if ($posterUrl) poster="{{ $posterUrl }}" @endif @if ($autoplay) autoplay @endif @if ($muted) muted @endif @if ($loop) loop @endif>{{ $slot }}</video>
                @endif
            </video-skin>
        </video-player>
    </div>
</div>
@endif
