import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from app.main import CONFIG, init_db, make_backup, prune_backups

init_db()
root = CONFIG.get("nas_backup_path", "").strip()
if not root:
    raise SystemExit("Chemin NAS absent. Configurez-le dans l'interface ou config.json.")
p = Path(root)
p.mkdir(parents=True, exist_ok=True)
out = make_backup(p)
prune_backups(p)
print(out)
