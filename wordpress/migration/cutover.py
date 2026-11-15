#!/usr/bin/env python3
"""Promote the verified WordPress site at the existing panel-managed web root.

Run as root on the verified CreditSoft host. Old service data remains in its
original directory inode, reachable through scoped links. No payment/mail jobs
are run. The previous web tree and WordPress database are privately backed up.
"""
from pathlib import Path
import fcntl
import hashlib
import json
import os
import subprocess
import tarfile
import time
from urllib.request import urlopen

site = Path('/var/www/0abb0757-d06a-4da8-b26e-ff885980834e')
live = site / 'public_html'
stage = site / 'public_html-wp'
release = Path(__file__).resolve().parent
assert os.geteuid() == 0
lock = open('/var/lock/creditsoft-wordpress-cutover.lock', 'w')
fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
assert (stage / 'wp-config.php').is_file() and not stage.is_symlink()
assert not (live / 'wp-config.php').exists()
theme = stage / 'wp-content/themes/creditsoft-helium'
manifest = json.loads((theme / 'content/preserved/manifest.json').read_text())
home = next(page for page in manifest['pages'] if page['route'] == '/')
assert hashlib.sha256((live / 'index.html').read_bytes()).hexdigest() == home['source_sha256'], 'Public homepage changed after capture'
assert len(manifest['pages']) == 47
with urlopen('https://staging.creditsoft.app/', timeout=25) as response:
    assert response.status == 200 and b'creditsoft-preserved-design' in response.read()

stamp = time.strftime('%Y%m%dT%H%M%SZ', time.gmtime())
backup = site / 'backups' / ('wordpress-cutover-' + stamp)
backup.mkdir(mode=0o700)
services = site / ('creditsoft-services-' + stamp)
assert not services.exists()
wp_base = ['sudo', '-n', '-u', 'www-data', 'wp', '--path=' + str(stage)]
def wp(*args):
    return subprocess.run(wp_base + list(args), check=True, text=True, capture_output=True).stdout.strip()

subprocess.run(['wp', '--allow-root', '--path=' + str(stage), 'db', 'export', str(backup / 'wordpress.sql'), '--skip-comments'], check=True, stdout=subprocess.DEVNULL)
with tarfile.open(backup / 'previous-public.tar.gz', 'w:gz') as archive:
    archive.add(live, arcname='public_html')
for file in backup.iterdir():
    file.chmod(0o600)
previous_home = wp('option', 'get', 'home')
previous_siteurl = wp('option', 'get', 'siteurl')
state = {'previous_home': previous_home, 'previous_siteurl': previous_siteurl, 'services': str(services), 'live': str(live), 'stage': str(stage), 'phase': 'backed_up'}
state_file = backup / 'cutover.json'
state_file.write_text(json.dumps(state, indent=2))
state_file.chmod(0o600)

try:
    wp('option', 'update', 'home', 'https://creditsoft.app')
    wp('option', 'update', 'siteurl', 'https://creditsoft.app')
    wp('option', 'update', 'blog_public', '1')
    assert wp('option', 'get', 'home') == 'https://creditsoft.app', 'A fixed WP_HOME prevents promotion'
    assert wp('option', 'get', 'siteurl') == 'https://creditsoft.app', 'A fixed WP_SITEURL prevents promotion'
    # Prepare future service targets before the two local renames.
    for path in stage.iterdir():
        if path.is_symlink():
            target = os.readlink(path)
            if target.startswith(str(live) + '/'):
                path.unlink()
                path.symlink_to(str(services) + target[len(str(live)):])
    live.rename(services)
    try:
        stage.rename(live)
    except BaseException:
        services.rename(live)
        raise
    stage.symlink_to(live, target_is_directory=True)
    wp_base[-1] = '--path=' + str(live)
    wp('cache', 'flush')
    # Confirm the exact public root now serves the dedicated Gantry theme.
    with urlopen('https://creditsoft.app/?creditsoft_cutover=' + stamp, timeout=30) as response:
        body = response.read()
        assert response.geturl().startswith('https://creditsoft.app/'), 'Unexpected canonical host'
        assert response.status == 200 and b'creditsoft-preserved-design' in body
        assert b'wp-content/themes/creditsoft-helium' in body
    state['phase'] = 'live_verified'
    state_file.write_text(json.dumps(state, indent=2))
except BaseException:
    if (live / 'wp-config.php').is_file() and services.exists():
        if stage.is_symlink():
            stage.unlink()
        live.rename(stage)
        services.rename(live)
    wp_base[-1] = '--path=' + str(stage)
    wp('option', 'update', 'home', previous_home)
    wp('option', 'update', 'siteurl', previous_siteurl)
    wp('option', 'update', 'blog_public', '0')
    for path in stage.iterdir():
        if path.is_symlink():
            target = os.readlink(path)
            if target.startswith(str(services) + '/'):
                path.unlink()
                path.symlink_to(str(live) + target[len(str(services)):])
    state['phase'] = 'rolled_back'
    state_file.write_text(json.dumps(state, indent=2))
    raise
print(json.dumps({'status': 'live_verified', 'backup': str(backup), 'legacy_services': str(services), 'site': 'https://creditsoft.app'}))
