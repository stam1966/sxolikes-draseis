"""Φτιάχνει τον Χάρτη Τοπικής Ιστορίας ως ένα αυτόνομο αρχείο HTML.

Χρήση:  python tools/build_html.py
Αποτέλεσμα: dist/xartis-topikis-istorias.html

Ανοίγει με διπλό κλικ σε κάθε υπολογιστή (χρειάζεται μόνο Internet για το υπόβαθρο),
ανεβαίνει σε οποιαδήποτε ιστοσελίδα και δεν χρειάζεται WordPress ή το πρόσθετο.
Περιέχει μόνο τα ελεγμένα δελτία (όχι σημεία τάξεων). Κάθε σχολείο ορίζει
τη θέση του με το κουμπί «Σχολείο».
"""
import json
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent.parent
PLUGIN = ROOT / 'sxolikes-draseis'
OUT = ROOT / 'dist' / 'xartis-topikis-istorias.html'

src = (PLUGIN / 'sxolikes-draseis.php').read_text(encoding='utf8')
version = re.search(r"Version:\s*([\d.]+)", src).group(1)


def read(rel):
    return (PLUGIN / rel).read_text(encoding='utf8')


def safe_js(code):
    # Ένα «</script» μέσα στον κώδικα θα έκλεινε νωρίς το στοιχείο.
    return code.replace('</script', '<\\/script')


data = json.loads(read('data/topoi.json'))
payload = {
    'school': None,
    'cats': data['categories'],
    'routes': data['routes'],
    'borders': data.get('borders', []),
    'admin': data.get('admin_history', []),
    'network': data.get('network', []),
    'nomoi': data.get('nomoi', []),
    'coasts': data.get('coastlines', []),
    'places': data['places'],
    'checked': data.get('checked', ''),
    'public': True,
    'standalone': True,
    # Υπόβαθρο που φορτώνει και όταν το αρχείο ανοίγει από τον δίσκο.
    # Ο κεντρικός server του OpenStreetMap μπλοκάρει σελίδες που ανοίγουν από τον δίσκο·
    # ο γερμανικός server της κοινότητας OpenStreetMap δεν τις μπλοκάρει.
    'tiles': 'https://tile.openstreetmap.de/{z}/{x}/{y}.png',
    'tilesAttr': '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> (openstreetmap.de)',
    'tilesSub': '',
}
data_js = 'window.SD_MAPS={"sd-map-file":' + json.dumps(payload, ensure_ascii=False).replace('</', '<\\/') + '};'

html = f"""<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Χάρτης Τοπικής Ιστορίας και Γεωγραφίας Φθιώτιδας</title>
<style>{read('assets/vendor/leaflet/leaflet.css')}</style>
<style>{read('assets/sd-map.css')}</style>
<style>
.sd-map-foot {{ font: .78rem/1.4 system-ui, sans-serif; color: #5f6b76; padding: .35rem 16px; background: #fff; border-top: 1px solid #d9dee3; }}
body.sd-map-public .sd-map-shell {{ height: calc(100vh - 44px - 30px); }}
@media (max-width: 800px) {{ body.sd-map-public .sd-map-shell {{ height: auto; }} }}
</style>
</head>
<body class="sd-map-public">
<header class="sd-map-head"><strong>Χάρτης Τοπικής Ιστορίας και Γεωγραφίας</strong> <span>Φθιώτιδα · {len(data['places'])} ελεγμένοι τόποι · {len(data['routes'])} διαδρομές</span></header>
<div class="sd-map-wrap" data-sd-map="sd-map-file"></div>
<footer class="sd-map-foot">Δελτία ελεγμένα με επίσημες πηγές ({'/'.join(reversed(data.get('checked', '').split('-')))}). Κουμπί «Σχολείο»: ορίστε τη θέση του σχολείου σας για να βλέπετε αποστάσεις. Έκδοση {version} · Leaflet (BSD-2) · υπόβαθρο OpenStreetMap και OpenTopoMap.</footer>
<script>{data_js}</script>
<script>{safe_js(read('assets/vendor/leaflet/leaflet.js'))}</script>
<script>{safe_js(read('assets/sd-map.js'))}</script>
</body>
</html>
"""

OUT.parent.mkdir(exist_ok=True)
OUT.write_text(html, encoding='utf8')
print(OUT, OUT.stat().st_size, 'bytes')
