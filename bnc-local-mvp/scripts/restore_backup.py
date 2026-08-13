from __future__ import annotations
import argparse, hashlib, json, shutil, sqlite3, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DATA = ROOT / "data"
DB = DATA / "bnc.sqlite3"
DOC = DATA / "documents"
CONFIG = ROOT / "config.json"

def sha(path: Path):
    h=hashlib.sha256()
    with path.open('rb') as f:
        for b in iter(lambda:f.read(1024*1024), b''): h.update(b)
    return h.hexdigest()

def main():
    ap=argparse.ArgumentParser(description="Restaure une sauvegarde BNC Local. Arrêtez l'application avant utilisation.")
    ap.add_argument("backup", help="Dossier backup-YYYYMMDD-HHMMSS")
    ap.add_argument("--yes", action="store_true")
    args=ap.parse_args(); src=Path(args.backup)
    manifest_path=src/'manifest.json'
    if not manifest_path.exists(): raise SystemExit('manifest.json absent')
    m=json.loads(manifest_path.read_text(encoding='utf-8'))
    dbsnap=src/'bnc.sqlite3'
    if sha(dbsnap)!=m['database_sha256']: raise SystemExit('ECHEC: hash base invalide')
    for item in m.get('files',[]):
        p=src/item['path']
        if not p.exists() or sha(p)!=item['sha256']: raise SystemExit(f"ECHEC: fichier invalide {item['path']}")
    # Also verify SQLite opens and integrity check passes.
    con=sqlite3.connect(dbsnap)
    try:
        result=con.execute('PRAGMA integrity_check').fetchone()[0]
        if result!='ok': raise SystemExit(f'ECHEC intégrité SQLite: {result}')
    finally: con.close()
    if not args.yes:
        ans=input(f"Restaurer {src} vers {ROOT} ? Cette opération remplace les données locales. [oui/N] ")
        if ans.strip().lower()!='oui': raise SystemExit('Annulé')
    safety=DATA/f"pre-restore-{__import__('datetime').datetime.now().strftime('%Y%m%d-%H%M%S')}"
    safety.mkdir(parents=True, exist_ok=True)
    if DB.exists(): shutil.copy2(DB, safety/'bnc.sqlite3')
    if DOC.exists(): shutil.copytree(DOC, safety/'documents', dirs_exist_ok=True)
    DATA.mkdir(exist_ok=True); DOC.mkdir(parents=True, exist_ok=True)
    shutil.copy2(dbsnap, DB)
    shutil.rmtree(DOC, ignore_errors=True); shutil.copytree(src/'documents', DOC)
    if (src/'config.json').exists(): shutil.copy2(src/'config.json', CONFIG)
    print('Restauration terminée. Copie de sécurité pré-restauration:', safety)

if __name__=='__main__': main()
