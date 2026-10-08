/*
 * Sidebar navigation behaviour: the Ctrl+K search and the collapsed icon-rail
 * mode.
 *
 * The sections themselves are steady — the server draws every group and its
 * items in full, and nothing here folds them away. What is kept client-side
 * and per-user (localStorage keyed by user id) is the rail choice.
 */
(function () {
    'use strict';

    var nav = document.getElementById('app-nav');
    if (!nav) return;

    var aside = document.getElementById('sidebar');
    var body = document.body;
    var currentKey = nav.dataset.currentRoute || '';
    var currentUrl = nav.dataset.currentUrl || '';

    /* ------------------------------------------------------------------ */
    /* State (per user)                                                    */
    /* ------------------------------------------------------------------ */

    var state = {
        collapsed: false,
    };

    function storageKey(suffix) {
        var uid = body.dataset.authUser || 'anon';
        return 'isu.nav.' + uid + '.' + suffix;
    }

    function load(name, fallback) {
        try {
            var raw = localStorage.getItem(storageKey(name));
            return raw ? JSON.parse(raw) : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function save(name, value) {
        try {
            localStorage.setItem(storageKey(name), JSON.stringify(value));
        } catch (e) { /* storage unavailable — defaults hold */ }
    }

    state.collapsed = load('collapsed', false);

    /* ------------------------------------------------------------------ */
    /* Index: every link in the nav, for search / favorites / recents      */
    /* ------------------------------------------------------------------ */

    var items = [];
    nav.querySelectorAll('a[data-nav-item]').forEach(function (a) {
        items.push({
            key: a.dataset.key,
            label: a.getAttribute('title') || a.textContent.trim(),
            url: a.getAttribute('href'),
            section: a.closest('[data-nav-section]')?.dataset.navSection || '',
            sectionLabel: a.closest('[data-nav-section]')?.dataset.navLabel || '',
            variant: a.dataset.variant === '1',
            anchor: a,
        });
    });

    function byKey(key) {
        for (var i = 0; i < items.length; i++) {
            if (items[i].key === key) return items[i];
        }
        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Generic list renderer (used by search results + favorites).         */
    /* ------------------------------------------------------------------ */

    function renderList(el, entries, emptyText) {
        if (!el) return;
        el.textContent = '';
        if (!entries.length) {
            var p = document.createElement('p');
            p.className = 'px-3 py-1.5 text-[12px] text-slate-400';
            p.textContent = emptyText;
            el.appendChild(p);
            return;
        }
        entries.forEach(function (entry) {
            var item = byKey(entry.key);
            var a = document.createElement('a');
            a.href = entry.url;
            a.className = 'nav-link group flex items-center gap-3 rounded-lg px-3 py-1.5 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900';
            a.setAttribute('title', entry.label);
            a.dataset.navMirror = entry.key;

            var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            icon.setAttribute('class', 'h-4 w-4 shrink-0 text-brand-500');
            icon.setAttribute('viewBox', '0 0 24 24');
            icon.setAttribute('fill', 'currentColor');
            icon.setAttribute('stroke', 'none');
            icon.setAttribute('aria-hidden', 'true');
            var path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('stroke-linecap', 'round');
            path.setAttribute('stroke-linejoin', 'round');
            path.setAttribute('d', 'M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z');
            icon.appendChild(path);

            var span = document.createElement('span');
            span.className = 'nav-label flex-1 truncate';
            span.textContent = entry.label;

            a.appendChild(icon);
            a.appendChild(span);
            el.appendChild(a);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Search (Ctrl+K)                                                     */
    /* ------------------------------------------------------------------ */

    var overlay = document.getElementById('nav-search-overlay');
    var searchInput = document.getElementById('nav-search-input');
    var searchResults = document.getElementById('nav-search-results');
    var searchActive = -1;
    var searchMatches = [];

    function openSearch() {
        if (!overlay) return;
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        body.classList.add('overflow-hidden');
        renderSearch('');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
    }

    function closeSearch() {
        if (!overlay) return;
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
        body.classList.remove('overflow-hidden');
        searchActive = -1;
        searchMatches = [];
    }

    function renderSearch(query) {
        if (!searchResults) return;
        searchResults.textContent = '';
        var q = query.trim().toLowerCase();
        searchMatches = items.filter(function (item) {
            if (!q) return !item.variant || item.url === currentUrl;
            var hay = (item.label + ' ' + item.sectionLabel + ' ' + (item.anchor.dataset.keywords || '')).toLowerCase();
            return q.split(/\s+/).every(function (word) { return hay.indexOf(word) !== -1; });
        }).slice(0, 9);

        if (!searchMatches.length) {
            var empty = document.createElement('p');
            empty.className = 'px-4 py-8 text-center text-sm text-slate-400';
            empty.textContent = 'No matching pages.';
            searchResults.appendChild(empty);
            return;
        }

        searchMatches.forEach(function (item, i) {
            var a = document.createElement('a');
            a.href = item.url;
            a.className = 'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-slate-700';
            a.dataset.searchIndex = String(i);

            if (i === searchActive) {
                a.classList.add('bg-brand-50', 'text-brand-800');
            }

            var icon = item.anchor.querySelector('svg');
            if (icon) {
                var clone = icon.cloneNode(true);
                clone.setAttribute('class', 'h-5 w-5 shrink-0 text-slate-400');
                a.appendChild(clone);
            }

            var wrap = document.createElement('span');
            wrap.className = 'min-w-0 flex-1';
            var label = document.createElement('span');
            label.className = 'block truncate font-medium';
            label.textContent = item.label;
            var crumb = document.createElement('span');
            crumb.className = 'block truncate text-xs text-slate-400';
            crumb.textContent = item.sectionLabel;
            wrap.appendChild(label);
            wrap.appendChild(crumb);
            a.appendChild(wrap);

            if (item.key === currentKey) {
                var badge = document.createElement('span');
                badge.className = 'shrink-0 rounded-full bg-brand-100 px-2 py-0.5 text-[10px] font-semibold text-brand-700';
                badge.textContent = 'Current';
                a.appendChild(badge);
            }

            a.addEventListener('click', function () { closeSearch(); });
            searchResults.appendChild(a);
        });
    }

    function moveSelection(delta) {
        if (!searchMatches.length) return;
        searchActive = (searchActive + delta + searchMatches.length) % searchMatches.length;
        searchResults.querySelectorAll('[data-search-index]').forEach(function (el) {
            var on = Number(el.dataset.searchIndex) === searchActive;
            el.classList.toggle('bg-brand-50', on);
            el.classList.toggle('text-brand-800', on);
        });
        var activeEl = searchResults.querySelector('[data-search-index="' + searchActive + '"]');
        if (activeEl) activeEl.scrollIntoView({ block: 'nearest' });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            searchActive = searchMatches.length ? 0 : -1;
            renderSearch(searchInput.value);
        });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); moveSelection(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); moveSelection(-1); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                var picked = searchMatches[searchActive] || searchMatches[0];
                if (picked) window.location.href = picked.url;
            } else if (e.key === 'Escape') {
                closeSearch();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('mousedown', function (e) {
            if (e.target === overlay) closeSearch();
        });
    }

    var trigger = document.getElementById('nav-search-trigger');
    if (trigger) trigger.addEventListener('click', openSearch);

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            if (overlay && !overlay.classList.contains('hidden')) closeSearch();
            else openSearch();
        } else if (e.key === 'Escape') {
            closeSearch();
        }
    });

    // The trigger is a no-op button when the overlay is missing (never in
    // practice), so also let a click on it focus the inline field.
    var inlineField = document.getElementById('nav-search-inline');
    if (inlineField) {
        inlineField.addEventListener('focus', openSearch);
    }

    /* ------------------------------------------------------------------ */
    /* Collapsed rail (desktop) + tooltips                                 */
    /* ------------------------------------------------------------------ */

    function applyCollapsed() {
        if (!aside) return;
        aside.classList.toggle('nav-rail', state.collapsed);
        body.classList.toggle('nav-rail-page', state.collapsed);
        var btn = document.getElementById('sidebar-collapse');
        if (btn) {
            btn.setAttribute('title', state.collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            var icon = btn.querySelector('.nav-collapse-icon');
            if (icon) icon.style.transform = state.collapsed ? 'rotate(180deg)' : '';
        }
        // Collapsed mode shows icons only; the labels hide via CSS (.nav-rail).
    }

    var collapseBtn = document.getElementById('sidebar-collapse');
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function () {
            state.collapsed = !state.collapsed;
            save('collapsed', state.collapsed);
            applyCollapsed();
        });
    }

    applyCollapsed();

    /* ------------------------------------------------------------------ */
    /* Filtering (typing into the inline trigger converts to search)       */
    /* ------------------------------------------------------------------ */

    // The inline "Search menu…" button opens the overlay; the overlay owns
    // the real input, so there is exactly one search implementation.
})();
