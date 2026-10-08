#!/usr/bin/env bash
#
# One-time Raspberry Pi setup: advertises the ISU supply management Laravel
# server over mDNS/Bonjour so the Flutter app discovers it automatically —
# no more typing the Pi's IP address, and it keeps working when DHCP changes
# the address.
#
# Usage:   sudo ./setup-mdns.sh [PORT]
#   PORT   The port the Laravel app is served on. Default 8000
#          (`php artisan serve --host=0.0.0.0`). Use 80 for Nginx/Apache.
#
# Safe to re-run: it only installs missing packages and rewrites the service
# file. Test afterwards with:  avahi-browse -rt _isu-supply._tcp
set -euo pipefail

PORT="${1:-8000}"

if [[ $EUID -ne 0 ]]; then
  echo "Please run with sudo: sudo $0 ${PORT}" >&2
  exit 1
fi

echo "==> [1/3] Ensuring avahi-daemon is installed..."
if ! command -v avahi-daemon >/dev/null 2>&1; then
  apt-get update
  apt-get install -y avahi-daemon
fi

echo "==> [2/3] Writing the mDNS service advertisement (port ${PORT})..."
SERVICE_FILE="/etc/avahi/services/isu-supply.service"
cat > "$SERVICE_FILE" <<EOF
<?xml version="1.0" standalone="no"?>
<!DOCTYPE service-group SYSTEM "avahi-service.dtd">
<service-group>
  <name replace-wildcards="yes">ISU Supply Server on %h</name>
  <service>
    <type>_isu-supply._tcp</type>
    <port>${PORT}</port>
    <txt-record>service=ISU Supply Management</txt-record>
    <txt-record>service_key=isu-supply</txt-record>
    <txt-record>version=1.0</txt-record>
    <txt-record>api=/api</txt-record>
  </service>
</service-group>
EOF

echo "==> [3/3] Restarting avahi-daemon..."
systemctl enable avahi-daemon >/dev/null 2>&1 || true
systemctl restart avahi-daemon

echo
echo "Done. The server now advertises  _isu-supply._tcp.local  on the LAN."
echo "Verify it is visible from another machine:"
echo "    avahi-browse -rt _isu-supply._tcp"
echo
echo "Note: the Laravel server itself must be listening on 0.0.0.0 (all"
echo "interfaces), e.g.  php artisan serve --host=0.0.0.0 --port=${PORT}"
echo "or Nginx/Apache configured to listen on all interfaces."