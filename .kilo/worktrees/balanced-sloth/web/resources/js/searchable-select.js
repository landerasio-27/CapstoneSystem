// Searchable <select> for long item lists (documents, cards).
//
// Clicking an enhanced <select class="js-searchable"> opens a dropdown that
// looks like the native one — same options, same group headings — with a
// search box at its top. Typing filters the list; picking a row selects the
// real <option> underneath. The select stays the true form control (same
// name, same server-rendered options), so submission and validation are
// untouched, and rows added later (document "Add Item") enhance themselves.
(function () {
    'use strict';

    var openInstance = null;

    function closeOpen() {
        if (openInstance) openInstance.close();
    }

    function enhance(select) {
        if (select.dataset.searchable) return;
        select.dataset.searchable = '1';

        var instance; // per-select handle, so several selects can coexist

        var placeholder = !select.value ? (select.options[select.selectedIndex] || {}).textContent || 'Select an item' : '';

        var shell = document.createElement('div');
        shell.className = 'isu-ss';
        select.parentNode.insertBefore(shell, select);
        shell.appendChild(select);

        // The visible control. Mirrors the app's input styling.
        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'isu-ss-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.innerHTML = '<span class="isu-ss-value"></span>' +
            '<svg class="isu-ss-caret" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';

        // The dropdown: search on top, options below — one control, no extra
        // search bar parked in the form.
        var pop = document.createElement('div');
        pop.className = 'isu-ss-pop';
        pop.setAttribute('role', 'listbox');

        var search = document.createElement('input');
        search.type = 'text';
        search.className = 'isu-ss-search';
        search.placeholder = 'Type to search…';
        search.setAttribute('aria-label', 'Search options');

        var list = document.createElement('div');
        list.className = 'isu-ss-list';

        var empty = document.createElement('div');
        empty.className = 'isu-ss-empty';
        empty.textContent = 'No matches';

        pop.appendChild(search);
        pop.appendChild(list);
        pop.appendChild(empty);
        shell.appendChild(trigger);
        shell.appendChild(pop);

        // Build rows once from the real options, keeping group headings.
        var rows = [];
        var groupEls = new Map();

        function addGroup(label) {
            var g = document.createElement('div');
            g.className = 'isu-ss-group';
            g.textContent = label;
            list.appendChild(g);
            return g;
        }

        Array.prototype.forEach.call(select.children, function (node) {
            if (node.tagName === 'OPTGROUP') {
                var g = addGroup(node.label);
                Array.prototype.forEach.call(node.children, function (opt) { addRow(opt, g); });
            } else if (node.tagName === 'OPTION') {
                addRow(node, list);
            }
        });

        function addRow(opt, parent) {
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'isu-ss-row' + (opt.value ? '' : ' isu-ss-row-placeholder');
            row.textContent = (opt.textContent || '').replace(/\s+/g, ' ').trim();
            row.dataset.value = opt.value;
            if (opt.selected) row.classList.add('is-selected');
            parent.appendChild(row);
            rows.push({ opt: opt, row: row });
        }

        function selectedText() {
            var opt = select.options[select.selectedIndex];
            var text = opt && opt.value ? opt.textContent : placeholder;
            return (text || '').replace(/\s+/g, ' ').trim();
        }

        function syncTrigger() {
            trigger.querySelector('.isu-ss-value').textContent = selectedText();
            trigger.classList.toggle('is-placeholder', !select.value);
            rows.forEach(function (r) { r.row.classList.toggle('is-selected', r.opt.selected); });
        }

        var activeIndex = -1;

        function visibleRows() {
            return rows.filter(function (r) { return !r.row.closest('.isu-ss-group')?.hidden && !r.row.hidden; });
        }

        function setActive(i, scroll) {
            var vis = visibleRows();
            if (!vis.length) { activeIndex = -1; return; }
            activeIndex = Math.max(0, Math.min(i, vis.length - 1));
            vis.forEach(function (r, idx) { r.row.classList.toggle('is-active', idx === activeIndex); });
            if (scroll !== false && vis[activeIndex]) vis[activeIndex].row.scrollIntoView({ block: 'nearest' });
        }

        function filter(q) {
            var needle = q.trim().toLowerCase();
            var shown = 0;

            rows.forEach(function (r) {
                var hit = !needle || r.row.textContent.toLowerCase().indexOf(needle) !== -1;
                r.row.hidden = !hit;
                if (hit) shown++;
            });

            list.querySelectorAll('.isu-ss-group').forEach(function (g) {
                var any = Array.prototype.some.call(g.querySelectorAll('.isu-ss-row'), function (row) { return !row.hidden; });
                g.hidden = !any;
            });

            empty.hidden = shown > 0;
            list.hidden = shown === 0;
            setActive(0, false);
        }

        function choose(row) {
            select.value = row.dataset.value;
            syncTrigger();
            close();
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function open() {
            closeOpen();
            openInstance = instance;
            shell.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');

            // Decide placement from the rendered popover (measurable now that
            // it is displayed), not from a hidden element's scrollHeight.
            pop.classList.remove('open-up');
            var rect = trigger.getBoundingClientRect();
            var roomBelow = window.innerHeight - rect.bottom - 12;
            var roomAbove = rect.top - 12;
            var height = pop.offsetHeight || 300;
            var openUp = roomBelow < Math.min(height, 240) && roomAbove > roomBelow;
            pop.classList.toggle('open-up', openUp);

            // Never let the option list run past the viewport edge.
            var room = openUp ? roomAbove : roomBelow;
            list.style.maxHeight = room < height ? Math.max(140, room - 46) + 'px' : '';

            search.value = '';
            filter('');
            syncTrigger();
            search.focus();
        }

        function close() {
            shell.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            if (openInstance === instance) openInstance = null;
        }

        trigger.addEventListener('click', function () {
            shell.classList.contains('is-open') ? close() : open();
        });
        trigger.addEventListener('keydown', function (e) {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].indexOf(e.key) !== -1) {
                e.preventDefault();
                if (!shell.classList.contains('is-open')) open();
            }
        });

        search.addEventListener('input', function () { filter(search.value); });
        search.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); setActive(activeIndex + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(activeIndex - 1); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                var vis = visibleRows();
                if (vis[activeIndex]) choose(vis[activeIndex].row);
            } else if (e.key === 'Escape') { e.preventDefault(); close(); trigger.focus(); }
            else if (e.key === 'Tab') { close(); }
        });

        list.addEventListener('click', function (e) {
            var row = e.target.closest('.isu-ss-row');
            if (row) choose(row);
        });
        list.addEventListener('mousemove', function (e) {
            var row = e.target.closest('.isu-ss-row');
            if (!row || row.hidden) return;
            var vis = visibleRows();
            var i = vis.findIndex(function (r) { return r.row === row; });
            setActive(i, false);
        });

        instance = { close: close, shell: shell };

        syncTrigger();
    }

    document.addEventListener('mousedown', function (e) {
        if (openInstance && !openInstance.shell.contains(e.target)) closeOpen();
    });

    function scan(root) { (root || document).querySelectorAll('select.js-searchable').forEach(enhance); }

    document.addEventListener('DOMContentLoaded', function () { scan(document); });

    // Rows added after load (document "Add Item" template clones) get the same
    // treatment without re-running anything.
    window.isuSearchable = { rescan: scan };
})();
