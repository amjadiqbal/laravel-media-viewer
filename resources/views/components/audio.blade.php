@if (! $allowed)
    @include('media-viewer::components.denied')
@else
{{ $assets() }}
<div {{ $attributes->class('mv mv-audio') }} wire:key="mv-{{ $key }}" data-mv="audio">
    <div wire:ignore>
        <audio-player>
            <audio-skin class="mv-audio__skin">
                <audio src="{{ $url }}" preload="{{ $preload }}" @if ($autoplay) autoplay @endif @if ($loop) loop @endif>{{ $slot }}</audio>
            </audio-skin>
        </audio-player>
    </div>
</div>
@endif
