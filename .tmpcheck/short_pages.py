"""Find list pages that leave the paper half empty on the export."""
import io, re, urllib.request, urllib.parse, http.cookiejar
import pymupdf as fitz

BASE = "http://127.0.0.1:8000"
MM = 2.834645
BOTTOM = 285.0

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
page = opener.open(BASE + "/login").read().decode()
token = re.search(r'name="_token" value="([^"]+)"', page).group(1)
opener.open(urllib.request.Request(BASE + "/login",
    urllib.parse.urlencode({"email": "admin@isu.edu.ph", "password": "password", "_token": token}).encode())).read()

def probe(url, label):
    doc = fitz.open(stream=io.BytesIO(opener.open(url).read()), filetype="pdf")
    out = []
    for i, p in enumerate(doc):
        t = p.get_text()
        ink = [b[3] for b in p.get_text("blocks")] + [d["rect"].y1 for d in p.get_drawings()]
        ink = [y for y in ink if y / MM < 282]
        low = max(ink) / MM if ink else 0
        no = re.search(r"NO:\s*(\S+)", t)
        counts = [int(m) for m in re.findall(r"(\d+)\s*copies", t)]
        out.append((i + 1, round(low, 1), "RECAP" if "Recapitulation" in t else "", no.group(1) if no else ""))
    print(f"== {label}: {len(out)} pages")
    for pg, low, rec, no in out:
        flag = "   <-- short list page" if (low < 240 and not rec) else ""
        print(f"   p{pg:2d} ends {low:6.1f}mm  {rec:5s} no={no}{flag}")
    doc.close()

for m in ():
    probe(f"{BASE}/admin/reports/monthly/pdf?month={m}&year=2026&document_type=All", f"month {m} All")
