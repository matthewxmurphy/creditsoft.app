#!/usr/bin/env python3
"""Remove captured Astro marketing files only, after verified WP cutover."""
from pathlib import Path
import hashlib,json,sys
site=Path('/var/www/0abb0757-d06a-4da8-b26e-ff885980834e')
backup=Path(sys.argv[1]).resolve()
assert backup.parent == site/'backups'
state=json.loads((backup/'cutover.json').read_text())
assert state['phase']=='live_verified'
services=Path(state['services'])
assert services.parent==site and services.name.startswith('creditsoft-services-')
assert (site/'public_html/wp-config.php').is_file()
assert (backup/'previous-public.tar.gz').is_file()
manifest=json.loads(Path(sys.argv[2]).read_text())
removed=[]
for relative,expected in manifest.items():
    path=services/relative
    assert not Path(relative).is_absolute() and '..' not in Path(relative).parts
    assert relative.endswith('.html') or relative.startswith('_astro/')
    assert not path.is_symlink()
    if not path.exists(): continue
    assert hashlib.sha256(path.read_bytes()).hexdigest()==expected, 'Legacy file changed: '+relative
    path.unlink();removed.append(relative)
for path in sorted(services.rglob('*'),key=lambda p:len(p.parts),reverse=True):
    if path.is_dir() and not path.is_symlink() and (path.relative_to(services).parts[0]=='_astro'):
        try:path.rmdir()
        except OSError:pass
receipt={'removed_captured_marketing_files':removed,'services_preserved':str(services),'rollback_archive':str(backup/'previous-public.tar.gz')}
(backup/'astro-retirement.json').write_text(json.dumps(receipt,indent=2))
(backup/'astro-retirement.json').chmod(0o600)
print(json.dumps({'removed_captured_marketing_files':len(removed),'services_preserved':True,'rollback_preserved':True}))
