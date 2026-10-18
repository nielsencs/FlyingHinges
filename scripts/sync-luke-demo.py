#!/usr/bin/env python3
"""Refresh the LUKE demo from the real LUKE code (Project-Board/public).

Copies the app into site/lukedemo/luke/ and adds src/demo-only.flag so every entry point
of the copy is demo-only; no real board data is involved.
Usage: python3 scripts/sync-luke-demo.py [path-to-Project-Board]
"""
import shutil
import sys
from pathlib import Path

root = Path(__file__).resolve().parent.parent
source = Path(sys.argv[1] if len(sys.argv) > 1 else root.parent / 'Project-Board') / 'public'
dest = root / 'site' / 'lukedemo' / 'luke'
if not (source / 'src' / 'demo.php').is_file():
    sys.exit(f'{source} does not look like a LUKE with demo mode')
if dest.exists():
    shutil.rmtree(dest)
shutil.copytree(source, dest, ignore=shutil.ignore_patterns('lukedemo', '*.py', '__pycache__'))
config = dest / 'src' / 'config.php'
text = config.read_text()
start = text.index("    $privatePrefix")
end = text.index("}\n", start)
text = text[:start] + "    throw new RuntimeException('No board path: this copy is demo-only.');\n" + text[end:]
config.write_text(text)
(dest / 'src' / 'demo-only.flag').write_text('demo only: this copy never uses a real board\n')
print(f'Synced LUKE into {dest.relative_to(root)}')
