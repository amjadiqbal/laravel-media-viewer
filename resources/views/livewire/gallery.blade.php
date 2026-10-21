<div class="mv-gallery">
    <ul class="mv-gallery__list" role="listbox" aria-label="Files">
        @foreach ($items as $item)
            <li wire:key="mv-item-{{ $item['index'] }}" role="option" aria-selected="{{ $item['index'] === $selected ? 'true' : 'false' }}">
                <button type="button" class="mv-gallery__item @if ($item['index'] === $selected) is-selected @endif" wire:click="select({{ $item['index'] }})">
                    @if ($item['kind'] === 'pdf' && $item['allowed'])
                        <x-media-viewer::pdf-thumbnail :disk="$disk" :path="$item['path']" :width="72" />
                    @else
                        <span class="mv-gallery__icon" aria-hidden="true">{{ strtoupper(pathinfo($item['path'], PATHINFO_EXTENSION)) }}</span>
                    @endif
                    <span class="mv-gallery__name">{{ $item['name'] }}</span>
                </button>
            </li>
        @endforeach
    </ul>
    <div class="mv-gallery__viewer">
        @if ($current)
            <x-media-viewer::file :disk="$disk" :path="$current['path']" />
        @endif
    </div>
</div>
