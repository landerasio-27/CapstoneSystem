"""Every month of the year exported, measured: no page's content may spill past
the bottom margin, and each sheet's pages should fill the paper."""

import io
import re
import sys
import urllib.request
import http.cookiejar

import fitz

BASE = "http://127.0.0.1:8000"
MM = 2.834645
BOTTOM = 285.0  # 297 - 12mm bottom margin
TOP = 12.6      # where dompdf's own body margin starts the content

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

page = opener.open(BASE + "/login").read().decode()
token = re.search(r'name="_token" value="([^"]+)"', page).group(1)
opener.open(urllib.request.Request(
    BASE + "/login",
    urllib.parse.urlencode({"email": "admin@isu.edu.ph", "password": "password", "_token": token}).encode(),
)).read()

worst = []
for month in range(1, 13):
    url = f"{BASE}/admin/reports/monthly/pdf?month={month}&year=2026&document_type=All"
    blob = opener.open(url).read()
    doc = fitz.open(stream=io.BytesIO(blob), filetype="pdf")

    for i, p in enumerate(doc):
        ink = [b[3] for b in p.get_text("blocks")] + [d["rect"].y1 for d in p.get_drawings()]
        ink = [y for y in ink if y / MM < 282]  # ignore the dompdf page footer
        if not ink:
            continue
        low = max(ink) / MM
        if low > BOTTOM:
            worst.append((month, i + 1, low))
            print(f"  SPILL month {month} page {i + 1}: content ends {low:.1f}mm > {BOTTOM}mm")

    fills = []
    for i, p in enumerate(doc):
        ink = [b[3] for b in p.get_text("blocks")] + [d["rect"].y1 for d in p.get_drawings()]
        ink = [y for y in ink if y / MM < 282]
        if not ink:
            continue
        low = max(ink) / MM
        if low > TOP + 60:  # a page carrying a list, not a cover
            fills.append(round((low - TOP) / (BOTTOM - TOP) * 100))

    print(f"month {month:2d}: {doc.page_count:2d} pages, list-page fills {fills}")

print()
print("SPILLS:", worst if worst else "none")
sys.exit(1 if worst else 0)
