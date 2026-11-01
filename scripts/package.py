#!/usr/bin/env python3
"""Create a Hostinger-ready archive, never including local configuration or test data."""
from pathlib import Path
import argparse
import hashlib
import shutil
import zipfile

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--directory', type=Path, help='Also prepare an unpacked deployment directory.')
args = parser.parse_args()
game = root / "zaidimas" / "index.html"
if not game.is_file():
    raise SystemExit("Pirmiausia sukurk žaidimą: npm --prefix game ci && npm --prefix game run build")

files = [root / name for name in (
    ".htaccess", ".env.example", "index.php", "404.html", "env.example.php", "pateikti.php", "README.md",
)]
for folder in ("assets", "lib", "admin", "keiksmazodziai", "migrations", "zaidimas"):
    files.extend(path for path in (root / folder).rglob("*") if path.is_file())
files.extend(root / "scripts" / name for name in ("migrate.php", "seed.php", "create-admin.php"))
files.append(root / "data" / ".htaccess")
# Keep provenance with the shipped game even though its editable sources are separate.
files.append(root / "game" / "UPSTREAM.md")
files.extend(root / name for name in ("icon.png", "favicon.ico", "apple-touch-icon.png"))
output = root / "dist" / "blynine-hostinger.zip"
output.parent.mkdir(exist_ok=True)
if args.directory:
    directory = args.directory.resolve()
    # Keep cleanup inside ignored build output, never a checkout or a hosting directory.
    if directory == root / 'dist' or root / 'dist' not in directory.parents:
        raise SystemExit('--directory turi būti dist/ pakatalogis.')
    if directory.exists():
        shutil.rmtree(directory)
    directory.mkdir(parents=True)
with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(set(files)):
        if not path.is_file():
            raise SystemExit(f"Trūksta leidinio failo: {path.relative_to(root)}")
        archive.write(path, path.relative_to(root))
        if args.directory:
            target = directory / path.relative_to(root)
            target.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(path, target)
    # Runtime assets must be tracked on the deployment branch, unlike the source checkout.
    deployment_ignore = '.env\n.env.*\n!.env.example\nenv.local.php\n.DS_Store\ndata/*.jsonl\n'
    archive.writestr('.gitignore', deployment_ignore)
    if args.directory:
        (directory / '.gitignore').write_text(deployment_ignore)
digest = hashlib.sha256(output.read_bytes()).hexdigest()
(output.parent / "SHA256SUMS").write_text(f"{digest}  {output.name}\n")
print(f"Paruošta: {output}\nSHA256: {digest}")
