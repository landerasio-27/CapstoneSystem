{{-- Reusable full-page loading overlay. Toggle with window.showLoading(message)
     / window.hideLoading(). Include once per page: <x-loading-overlay /> --}}
<div id="app-loading" class="app-loading" role="status" aria-live="polite" aria-hidden="true">
    <div class="app-loading-card">
        <span class="app-loading-spinner" aria-hidden="true"></span>
        <span id="app-loading-text" class="app-loading-text">Loading&hellip;</span>
    </div>
</div>

<script>
    window.showLoading = function (message) {
        var el = document.getElementById('app-loading');
        if (!el) return;
        if (message) document.getElementById('app-loading-text').textContent = message;
        el.classList.add('is-visible');
        el.setAttribute('aria-hidden', 'false');
    };
    window.hideLoading = function () {
        var el = document.getElementById('app-loading');
        if (!el) return;
        el.classList.remove('is-visible');
        el.setAttribute('aria-hidden', 'true');
    };
    // Safety: if a page is restored from the bfcache (e.g. back button), hide it.
    window.addEventListener('pageshow', function () { window.hideLoading(); });
</script>
