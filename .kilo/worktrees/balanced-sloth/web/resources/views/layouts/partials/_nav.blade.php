@php
    /*
     * The admin sidebar, built from config/navigation.php.
     *
     * The sections are steady groups: a heading and the items under it, with
     * nothing that opens or closes — every group is always drawn in full. The
     * JS powers the Ctrl+K search and the icon-rail mode; this partial only
     * renders honest server-side state: every item the role can open, with
     * its real URL.
     *
     * $navSections is passed in pre-filtered by role and with resolved URLs.
     */
@endphp

<nav id="app-nav" class="flex-1 overflow-y-auto overscroll-contain px-3 py-4 text-sm sidebar-scroll"
     data-current-route="{{ request()->route()?->getName() }}"
     data-current-url="{{ url()->current() . (request()->getQueryString() ? '?' . request()->getQueryString() : '') }}">

    {{-- Global navigation search: Ctrl+K / typing filters every item. --}}
    <div class="nav-search-wrap relative mb-4 px-1" data-nav-label="Search">
        <button type="button" id="nav-search-trigger"
                class="flex w-full items-center gap-2.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-left text-slate-400 transition hover:border-slate-300 hover:text-slate-500"
                title="Search navigation (Ctrl+K)">
            <x-icon name="search" class="h-4 w-4 shrink-0" />
            <span class="nav-label flex-1 truncate text-[13px]">Search menu…</span>
            <kbd class="nav-label hidden rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-slate-400 lg:inline">Ctrl K</kbd>
        </button>
    </div>

    {{-- Standalone items above the sections (the dashboard). --}}
    @if (! empty($navSections['top']))
        <div class="nav-group mb-4" data-nav-label="">
            <div class="nav-items space-y-0.5">
                @foreach ($navSections['top'] as $item)
                    <a href="{{ $item['url'] }}"
                       data-nav-item
                       data-key="{{ $item['key'] }}"
                       data-keywords="{{ $item['keywords'] ?? '' }}"
                       title="{{ $item['label'] }}"
                       class="nav-link group relative flex items-center gap-3 rounded-lg px-3 py-2 transition {{ $item['active'] ? 'is-active bg-brand-50 font-semibold text-brand-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                        <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 {{ $item['active'] ? 'text-brand-700' : 'text-slate-400 group-hover:text-slate-600' }}" />
                        <span class="nav-label flex-1 truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- The sections. The name heads its group and the items follow it: a
         steady list, with no control to fold either away. --}}
    @foreach ($navSections['sections'] as $section => $items)
        @php $sectionKey = \Illuminate\Support\Str::slug($section); @endphp
        <div class="nav-group mb-3" data-nav-section="{{ $sectionKey }}" data-nav-label="{{ $section }}">
            <div class="nav-section-heading px-3 py-1.5" title="{{ $section }}">
                <span class="nav-label text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $section }}</span>
            </div>
            <div class="nav-items mt-0.5 space-y-0.5">
                @foreach ($items as $item)
                    @php $isVariant = isset($item['queries']); @endphp
                    <a href="{{ $item['url'] }}"
                       data-nav-item
                       data-key="{{ $item['key'] }}"
                       data-keywords="{{ $item['keywords'] ?? '' }}"
                       @if ($isVariant) data-variant="1" @endif
                       title="{{ $item['label'] }}"
                       class="nav-link group relative flex items-center gap-3 rounded-lg px-3 py-2 transition {{ $item['active'] ? 'is-active bg-brand-50 font-semibold text-brand-800' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                        <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 {{ $item['active'] ? 'text-brand-700' : 'text-slate-400 group-hover:text-slate-600' }}" />
                        <span class="nav-label flex-1 truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach

    <p id="nav-empty" class="hidden px-3 py-6 text-center text-[13px] text-slate-400">No matching pages.</p>
</nav>
