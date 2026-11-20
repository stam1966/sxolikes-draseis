"""Ενημερώνει το update.json για νέα έκδοση (ίδιος μηχανισμός με το School e-Forms).

Χρήση, αφού γίνει commit και push του κώδικα της έκδοσης:
    python tools/release.py "## 0.4.1" "- Αλλαγή 1" "- Αλλαγή 2"
Το download δείχνει στο zip του τρέχοντος commit (HEAD) στο GitHub. Μετά: commit + push του update.json.
Οι προηγούμενες σημειώσεις αλλαγών διατηρούνται κάτω από τις νέες.
"""
import datetime
import json
import pathlib
import re
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
REPO = 'https://github.com/stam1966/sxolikes-draseis'

src = (ROOT / 'sxolikes-draseis' / 'sxolikes-draseis.php').read_text(encoding='utf8')
version = re.search(r"Version:\s*([\d.]+)", src).group(1)
const = re.search(r"'SD_VERSION',\s*'([\d.]+)'", src).group(1)
if version != const:
    sys.exit(f'Η έκδοση της επικεφαλίδας ({version}) διαφέρει από το SD_VERSION ({const}).')

sha = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
dirty = subprocess.check_output(['git', 'status', '--porcelain', '--', 'sxolikes-draseis'], cwd=ROOT, text=True).strip()
if dirty:
    sys.exit('Υπάρχουν αλλαγές στο πρόσθετο χωρίς commit. Κάντε πρώτα commit και push.')

path = ROOT / 'update.json'
old = json.loads(path.read_text(encoding='utf8')) if path.exists() else {}
notes = '\n'.join(sys.argv[1:]) or f'## {version}'
changelog = notes + ('\n' + old['changelog'] if old.get('changelog') else '')

data = {
    'version': version,
    'download': f'{REPO}/archive/{sha}.zip',
    'requires': '6.2',
    'requires_php': '7.4',
    'tested': '7.1',
    'date': datetime.date.today().isoformat(),
    'changelog': changelog[:20000],
}
path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n', encoding='utf8')
print('update.json ->', version, sha[:10])
