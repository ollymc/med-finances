from pathlib import Path
import json
import uvicorn

ROOT=Path(__file__).resolve().parent
config_path=ROOT/'config.json'
if config_path.exists(): config=json.loads(config_path.read_text(encoding='utf-8'))
else: config={"bind_host":"127.0.0.1","bind_port":8787}
uvicorn.run("app.main:app", host=config.get("bind_host","127.0.0.1"), port=int(config.get("bind_port",8787)), reload=False)
