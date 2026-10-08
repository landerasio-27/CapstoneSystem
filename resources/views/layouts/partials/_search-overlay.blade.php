{{-- The Ctrl+K command palette. Filled and driven by resources/js/sidebar.js. --}}
<div id="nav-search-overlay" class="fixed inset-0 z-50 hidden items-start justify-center p-4 pt-[12vh]">
    <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>

    <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-center gap-3 border-b border-slate-100 px-4">
            <x-icon name="search" class="h-5 w-5 shrink-0 text-slate-400" />
            <input id="nav-search-input" type="text" autocomplete="off" role="combobox" aria-expanded="true"
                   placeholder="Search pages… (e.g. RIS, Stock Card, Low Stock)"
                   class="w-full bg-transparent py-3.5 text-sm text-slate-800 placeholder-slate-400 focus:outline-none" />
            <button type="button" onclick="document.getElementById('nav-search-overlay').classList.add('hidden'); document.getElementById('nav-search-overlay').classList.remove('flex');"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" title="Close (Esc)">
                <x-icon name="x-mark" class="h-4 w-4" />
            </button>
        </div>
        <div id="nav-search-results" class="max-h-[50vh] overflow-y-auto p-2" role="listbox"></div>
        <div class="flex items-center gap-4 border-t border-slate-100 px-4 py-2 text-[11px] text-slate-400">
            <span><kbd class="rounded border border-slate-200 px-1">↑</kbd> <kbd class="rounded border border-slate-200 px-1">↓</kbd> navigate</span>
            <span><kbd class="rounded border border-slate-200 px-1">Enter</kbd> open</span>
            <span><kbd class="rounded border border-slate-200 px-1">Esc</kbd> close</span>
        </div>
    </div>
</div>
