// Service worker for the ISU Supply Management PWA.
// Its main job is to make the app installable as a desktop/standalone app and
// to cache static assets so the shell loads fast (and works offline for assets).
// Authenticated HTML pages are always fetched from the network (never cached),
// so admins never see stale data.

const CACHE = 'isu-sms-v2';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            // Drop old caches.
            const keys = await caches.keys();
            await Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)));
            await self.clients.claim();
        })()
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    // Cache-first for build assets, images, fonts and other static files.
    const isStatic =
        url.pathname.startsWith('/build/') ||
        url.pathname.startsWith('/images/') ||
        /\.(css|js|woff2?|ttf|png|jpe?g|svg|ico|webmanifest)$/i.test(url.pathname);

    if (isStatic) {
        event.respondWith(
            caches.open(CACHE).then(async (cache) => {
                const cached = await cache.match(req);
                if (cached) return cached;
                const res = await fetch(req);
                if (res && res.status === 200) cache.put(req, res.clone());
                return res;
            })
        );
    }
    // Everything else (HTML pages, API) falls through to the default network handling.
});
