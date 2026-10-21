@if ($component)
    <x-dynamic-component :component="$component" :disk="$disk" :path="$path" :src="$src" {{ $attributes }} />
@else
    <div {{ $attributes->class('mv mv-denied') }} role="note">{{ __('media-viewer::viewer.unsupported') }}</div>
@endif
