#!/usr/bin/env python3
"""Bounded deployment for the verified CreditSoft static tools release.
Run on the already verified public host with sudo; default is inspection only.
"""
import argparse, hashlib, json, os, shutil, time
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('release',type=Path);p.add_argument('--apply',action='store_true');args=p.parse_args()
release=args.release.resolve();root=Path('/var/www/0abb0757-d06a-4da8-b26e-ff885980834e/public_html')
manifest=json.loads((release/'manifest.json').read_text())
sha=lambda path:hashlib.sha256(path.read_bytes()).hexdigest()
for name,item in manifest.items():
 rel=Path(name)
 assert not rel.is_absolute() and '..' not in rel.parts
 assert name in ('index.html','sitemap.xml') or (rel.parts[0]=='tools' and rel.suffix=='.html') or (rel.parts[0]=='_astro' and rel.suffix in ('.js','.css'))
 target=root/rel;source=release/rel
 assert source.is_file() and sha(source)==item['sha256'],f'Release mismatch: {name}'
 assert not target.is_symlink(),f'Unexpected symlink: {name}'
 assert root.resolve() in target.resolve().parents,f'Path escapes public root: {name}'
 current=sha(target) if target.exists() else None
 assert current==item['before'],f'Live file changed or new path is occupied: {name}'
print(json.dumps({'preflight':'passed','files':len(manifest),'apply':args.apply}))
if not args.apply:raise SystemExit()
backup=Path('/var/backups/creditsoft-financial-tools')/time.strftime('%Y%m%dT%H%M%SZ',time.gmtime())
backup.mkdir(parents=True,mode=0o700);backup.chmod(0o700)
(backup/'manifest.json').write_text(json.dumps(manifest,indent=2))
for name,item in manifest.items():
 if item['before']:
  saved=backup/name;saved.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(root/name,saved)
  assert sha(saved)==item['before']
# Assets first, tools next, then discovery links. Each replacement is atomic.
order=sorted(manifest,key=lambda n:(0 if n.startswith('_astro/') else 1 if n.startswith('tools/') else 2,n))
written=[]
try:
 for name in order:
  target=root/name
  parent=root
  for part in Path(name).parts[:-1]:
   parent=parent/part
   if not parent.exists():
    parent.mkdir(mode=0o2775);os.chown(parent,root.stat().st_uid,root.stat().st_gid);parent.chmod(0o2775)
  current=sha(target) if target.exists() else None
  assert current==manifest[name]['before'],f'Concurrent update: {name}'
  temporary=target.with_name(target.name+'.creditsoft-tools-pending')
  if temporary.exists():raise RuntimeError(f'Stale temporary file: {name}')
  try:
   shutil.copyfile(release/name,temporary);temporary.chmod(0o644)
   owner=target.stat() if target.exists() else root.stat();os.chown(temporary,owner.st_uid,owner.st_gid)
   os.replace(temporary,target)
  finally:
   if temporary.exists():temporary.unlink()
  written.append(name)
  assert sha(target)==manifest[name]['sha256']
except Exception:
 # Restore only this attempt's files and only when they still match our release.
 for name in reversed(written):
  target=root/name
  if sha(target)!=manifest[name]['sha256']:continue
  if manifest[name]['before']:
   temporary=target.with_name(target.name+'.creditsoft-tools-restore');shutil.copy2(backup/name,temporary);owner=target.stat();os.chown(temporary,owner.st_uid,owner.st_gid);os.replace(temporary,target)
  else:target.unlink()
 raise
print(json.dumps({'deployed':len(written),'backup':str(backup),'manifest_sha256':sha(backup/'manifest.json')}))
