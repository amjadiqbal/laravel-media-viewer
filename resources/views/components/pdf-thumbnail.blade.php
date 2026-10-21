@if (! $allowed)
    @include('media-viewer::components.denied')
@else
{{ $assets() }}
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} {{ $attributes->class('mv mv-thumb') }} @if ($href) href="{{ $href }}" @endif wire:key="mv-thumb-{{ $key }}-{{ $page }}" data-mv="pdf-thumb" data-mv-src="{{ $url }}" data-mv-page="{{ $page }}" data-mv-width="{{ $width }}" style="--mv-thumb-width: {{ $width }}px" title="{{ $label }}">
    <span class="mv-thumb__frame" wire:ignore><span class="mv-thumb__placeholder" aria-hidden="true">PDF</span></span>
    <span class="mv-sr">{{ $label }}</span>
</{{ $tag }}>
@endif
