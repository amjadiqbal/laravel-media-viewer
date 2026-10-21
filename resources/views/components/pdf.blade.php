@if (! $allowed)
    @include('media-viewer::components.denied')
@else
{{ $assets() }}
<div {{ $attributes->class('mv mv-pdf') }} wire:key="mv-{{ $key }}" data-mv="pdf" data-mv-src="{{ $url }}" data-mv-options="{{ json_encode($options) }}" style="--mv-height: {{ $height }}">
    <div class="mv-pdf__inner" wire:ignore>
        <div class="mv-toolbar" role="toolbar" aria-label="{{ __('media-viewer::viewer.toolbar') }}">
            <div class="mv-group">
                <button type="button" class="mv-btn" data-mv-action="prev" aria-label="{{ __('media-viewer::viewer.previous') }}" title="{{ __('media-viewer::viewer.previous') }}">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M12.5 4.5 7 10l5.5 5.5"/></svg>
                </button>
                <label class="mv-page">
                    <span class="mv-sr">{{ __('media-viewer::viewer.page') }}</span>
                    <input type="number" min="1" value="1" inputmode="numeric" data-mv-page>
                    <span>/ <span data-mv-pages>–</span></span>
                </label>
                <button type="button" class="mv-btn" data-mv-action="next" aria-label="{{ __('media-viewer::viewer.next') }}" title="{{ __('media-viewer::viewer.next') }}">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M7.5 4.5 13 10l-5.5 5.5"/></svg>
                </button>
            </div>
            <div class="mv-group">
                <button type="button" class="mv-btn" data-mv-action="zoom-out" aria-label="{{ __('media-viewer::viewer.zoom_out') }}" title="{{ __('media-viewer::viewer.zoom_out') }}">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 10h10"/></svg>
                </button>
                <label>
                    <span class="mv-sr">{{ __('media-viewer::viewer.zoom') }}</span>
                    <select data-mv-scale>
                        <option value="auto">{{ __('media-viewer::viewer.auto') }}</option>
                        <option value="page-width">{{ __('media-viewer::viewer.page_width') }}</option>
                        <option value="page-fit">{{ __('media-viewer::viewer.page_fit') }}</option>
                        @foreach ([0.5, 0.75, 1, 1.25, 1.5, 2, 3, 4] as $step)
                            <option value="{{ $step }}">{{ $step * 100 }}%</option>
                        @endforeach
                        <option value="custom" hidden data-mv-custom></option>
                    </select>
                </label>
                <button type="button" class="mv-btn" data-mv-action="zoom-in" aria-label="{{ __('media-viewer::viewer.zoom_in') }}" title="{{ __('media-viewer::viewer.zoom_in') }}">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 10h10M10 5v10"/></svg>
                </button>
            </div>
            @if ($search)
                <div class="mv-group mv-search">
                    <label>
                        <span class="mv-sr">{{ __('media-viewer::viewer.search') }}</span>
                        <input type="search" data-mv-search placeholder="{{ __('media-viewer::viewer.search') }}" autocomplete="off">
                    </label>
                    <button type="button" class="mv-btn" data-mv-action="search-prev" aria-label="{{ __('media-viewer::viewer.search_previous') }}" title="{{ __('media-viewer::viewer.search_previous') }}">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 12.5 10 7l5 5.5"/></svg>
                    </button>
                    <button type="button" class="mv-btn" data-mv-action="search-next" aria-label="{{ __('media-viewer::viewer.search_next') }}" title="{{ __('media-viewer::viewer.search_next') }}">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7.5 10 13l5-5.5"/></svg>
                    </button>
                    <span class="mv-matches" data-mv-matches aria-live="polite"></span>
                </div>
            @endif
            <div class="mv-group mv-end">
                @if ($download)
                    <a class="mv-btn" href="{{ $url }}" download="{{ $filename }}" aria-label="{{ __('media-viewer::viewer.download') }}" title="{{ __('media-viewer::viewer.download') }}">
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 3.5v9M6 9l4 4 4-4M4 16.5h12"/></svg>
                    </a>
                @endif
                <button type="button" class="mv-btn" data-mv-action="fullscreen" aria-label="{{ __('media-viewer::viewer.fullscreen') }}" title="{{ __('media-viewer::viewer.fullscreen') }}">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3.5 7.5v-4h4M12.5 3.5h4v4M16.5 12.5v4h-4M7.5 16.5h-4v-4"/></svg>
                </button>
            </div>
        </div>
        <div class="mv-pdf__stage">
            <div class="mv-pdf__scroller" data-mv-scroller tabindex="0"><div class="pdfViewer"></div></div>
            <div class="mv-status" data-mv-status>{{ __('media-viewer::viewer.loading') }}</div>
        </div>
    </div>
</div>
@endif
