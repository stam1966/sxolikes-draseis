"""Φτιάχνει το αρχείο εγκατάστασης του πρόσθετου: dist/sxolikes-draseis-<έκδοση>.zip

Χρήση (από τον φάκελο του έργου):  python tools/build.py
Ελέγχει ότι η έκδοση της επικεφαλίδας και η σταθερά SD_VERSION συμφωνούν.
"""
import pathlib
import re
import sys
import zipfile

ROOT = pathlib.Path(__file__).resolve().parent.parent
PLUGIN = ROOT / 'sxolikes-draseis'

src = (PLUGIN / 'sxolikes-draseis.php').read_text(encoding='utf8')
header = re.search(r"Version:\s*([\d.]+)", src).group(1)
const = re.search(r"'SD_VERSION',\s*'([\d.]+)'", src).group(1)
if header != const:
    sys.exit(f'Η έκδοση της επικεφαλίδας ({header}) διαφέρει από το SD_VERSION ({const}).')

out = ROOT / 'dist' / f'sxolikes-draseis-{header}.zip'
out.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for p in sorted(PLUGIN.rglob('*')):
        if p.is_file():
            z.write(p, p.relative_to(ROOT).as_posix())
print(out, out.stat().st_size, 'bytes')
