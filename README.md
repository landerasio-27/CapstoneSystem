# ISU Cauayan Supply Management System

Network-based supply management system for Isabela State University — Cauayan
Campus. Replaces the manual, Excel-based supply office workflow with a
centralized Laravel web portal (Admin) and a Flutter Android app (Inspection
Staff), sharing one MySQL database.

## Repository layout

- `web/` — Laravel 13 admin portal + REST API (PHP 8.3, MySQL, Sanctum)
- `mobile/` — Flutter Android app for QR-based equipment inspection

## Default credentials (seeded)

| Role              | Email                    | Password |
|--------------------|--------------------------|----------|
| Admin              | admin@isu.edu.ph         | password |
| Inspection Staff   | jdelacruz@isu.edu.ph     | password |
| Inspection Staff   | msantos@isu.edu.ph       | password |

Change these before any real deployment.

## Web portal setup

Requires PHP 8.2+, Composer, MySQL, Node.js.

```bash
cd web
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
# edit .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD to match your MySQL
php artisan db:seed --class=UserSeeder
php artisan migrate
php artisan serve

# changing ip address in the raspberry pi command
sudo nano /etc/apache2/sites-available/isu-supply.conf
```

Visit `http://127.0.0.1:8000/login`. Only the `admin` role can sign in to the
web portal — inspection staff accounts are for the mobile app / API only.

### Notable packages

- `laravel/sanctum` — token auth for the mobile REST API
- `simplesoftwareio/simple-qrcode` — QR generation (SVG; no Imagick needed)
- `barryvdh/laravel-dompdf` — PAR/IAR/ICS/RIS and report PDFs
- `maatwebsite/excel` — report Excel exports
- `kreait/laravel-firebase` — FCM push notifications (see below)

## Firebase Cloud Messaging (currently stubbed)

The full notification pipeline (DB tables, `NotificationService`,
`FcmService`, the `/api/fcm-token` endpoint) is built and working — in-app
notifications and admin alerts work today. Actual push delivery is disabled
until real Firebase credentials are supplied:

1. Create a Firebase project and generate a service-account JSON
   (Project Settings → Service Accounts → Generate new private key).
2. Save it as `web/storage/app/firebase/service-account.json`.
3. In `web/.env`, set `FCM_ENABLED=true` and `FIREBASE_PROJECT_ID=<your project id>`.
4. For the mobile app, add `firebase_core`/`firebase_messaging` to
   `mobile/pubspec.yaml`, run `flutterfire configure`, and register the
   device token via `POST /api/fcm-token` after login.

Until then, `FcmService::sendToUser()` fails soft (logs and returns false) —
nothing else in the app depends on push actually succeeding.

## REST API (mobile)

Base path: `/api`. All routes except `/api/login` require a Sanctum bearer
token and the `inspection_staff` role.

- `POST /api/login`, `POST /api/logout`, `GET /api/profile`
- `GET /api/teachers/{qr}` — resolve a scanned teacher QR
- `POST /api/inspections/start` — `{teacher_qr}`
- `POST /api/inspections/{id}/scan` — `{equipment_qr}` → verified/misplaced/invalid
- `POST /api/inspections/{id}/submit` — finalizes, auto-flags unscanned items missing
- `GET /api/inspections/history`
- `GET /api/notifications`, `POST /api/fcm-token`

## Mobile app setup

Requires Flutter 3.x with the Android toolchain.

```bash
cd mobile
flutter pub get
flutter run          # with a device/emulator attached
flutter build apk --debug   # or --release for a signed build
```

The app **auto-discovers the server** — nobody types an IP address anymore.
On launch it checks the last known server; if it's gone (or on first launch)
it runs a radar-style **Find Server** scan over mDNS/Bonjour
(`_isu-supply._tcp.local`), verifies the candidate against
`GET /api/server-discovery`, and connects automatically. If the search finds
nothing it offers **Scan Again** plus **Enter Server Manually** as a fallback
only. The discovered address is saved locally and re-verified on every launch;
**Server Settings → Rediscover Server** rescans any time.

For the emulator (no mDNS), keep the old fallback: on the discovery screen tap
**Enter Server Manually** and enter `http://10.0.2.2:8000/api`.

The app requests camera + internet permissions and allows cleartext HTTP,
since the server runs on a local Raspberry Pi over LAN/Wi-Fi without HTTPS.

## Deployment target

Raspberry Pi 5 (8GB) hosting Laravel + MySQL on the local network:

1. Install PHP 8.2+, MySQL, and Composer on the Pi (or use a Docker image).
2. Deploy `web/`, run `composer install --no-dev`, `npm run build`,
   `php artisan migrate --seed`, `php artisan config:cache`.
3. Serve via Nginx/Apache + PHP-FPM (not `artisan serve`, which is single
   process and dev-only).
4. **Advertise the server for auto-discovery** (one-time):

   ```bash
   sudo web/deploy/setup-mdns.sh 8000   # 8000 = artisan serve; use 80 for Nginx/Apache
   ```

   This installs avahi-daemon and publishes the `_isu-supply._tcp` service so
   phones find the Pi even when DHCP changes its IP. Verify with
   `avahi-browse -rt _isu-supply._tcp`. The Laravel server must listen on all
   interfaces (`php artisan serve --host=0.0.0.0` or an Nginx/Apache vhost).

## Server discovery — how it works

- **Flutter** (`mobile/lib/services/server_discovery.dart`): browses the mDNS
  service `_isu-supply._tcp.local` (via `multicast_dns`), collects candidate
  host/port pairs, then probes each one's `GET /api/server-discovery` and only
  accepts a response identifying the ISU Supply Management backend — an
  unrelated device on the LAN can never be mistaken for the server. On Android
  a Wi-Fi multicast lock is held during the scan (`MainActivity.kt`).
- **Laravel** (`web/routes/api.php` + `web/app/Http/Controllers/Api/ServerDiscoveryController.php`):
  a public, no-auth endpoint that returns a tiny JSON identity payload
  (`success`, `service`, `version`, `api`) — no secrets, no database.
- **Raspberry Pi**: avahi publishes the service (see step 4 above), so the
  discovery works across DHCP address changes.
- **Subnet fallback (no advertisement needed)**: if mDNS finds nothing — the
  server is a plain dev PC running Laravel without avahi/Bonjour, or the
  access point blocks multicast — the app probes every host in the phone's
  own `/24` subnet on ports 8000 and 80 against `/api/server-discovery` and
  only accepts a matching ISU response. So on the dev PC you can just run
  `Start ISU Server.bat` (or `php artisan serve --host=0.0.0.0`) and the
  phone still finds it, as long as both are on the same Wi-Fi/subnet.
- Fallback: manual entry is still available from the discovery screen and
  Server Settings, and is verified the same way before being accepted.

## Architecture notes worth knowing

- **Document auto-fill chain**: `Document::spawnChild()` copies header +
  line items from a PAR into a new IAR/ICS/RIS record (`parent_document_id`
  links them) — data is entered once and never re-typed.
- **QR codes** are opaque random tokens in a polymorphic `qr_codes` table
  (`qrable_type`/`qrable_id`), covering both teachers and equipment.
- **Inspection workflow**: starting an inspection pre-populates one
  `inspection_items` row per equipment currently assigned to the teacher
  (`status = pending`). Scanning marks items verified/misplaced; submitting
  flips any still-pending rows to `missing` and alerts admins.
- **RBAC** is enforced by `EnsureUserHasRole` (`role:admin` / `role:inspection_staff`)
  on both web and API route groups — not just at the UI layer.
