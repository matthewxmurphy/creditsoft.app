#!/usr/bin/env python3
"""Run exact modern operator methods in a disposable PHP fixture without I/O facades."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import shutil
import stat
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
EXPECTED = {
    'app/Console/Commands/CreditsoftPublishRelease.php': 'de61a288d72183444b018d2740cc96d6704855edf063490aabc3fcdd397f9396',
    'app/Services/CreditsoftOfficeUpdatePackage.php': '18a9f3fbd6a8f1943c0d0b7bf53b4780206b7a8f95f088cc336b9e056851d7e0',
    'app/Support/CreditsoftReleaseVersion.php': 'cbd3cb5d1a4d925ee11b41f7427453c2b05e41655e741d9f5a546bb26d75c352',
}
DISABLED = ('fsockopen,pfsockopen,stream_socket_client,stream_socket_server,socket_create,socket_connect,'
    'curl_init,curl_exec,curl_multi_exec,mail,exec,shell_exec,system,passthru,proc_open,popen,'
    'pcntl_exec,pcntl_fork,dl,ini_set,putenv')


def need(ok):
    if not ok: raise RuntimeError('Owned modern operator fixture stopped.')


def source(path):
    before = path.lstat()
    need(path.resolve() == path and stat.S_ISREG(before.st_mode) and before.st_nlink == 1 and before.st_size <= 262144)
    with path.open('rb') as stream:
        opened = os.fstat(stream.fileno()); raw = stream.read(262145); after = os.fstat(stream.fileno())
    fields = ('st_dev', 'st_ino', 'st_mode', 'st_uid', 'st_gid', 'st_nlink', 'st_size', 'st_mtime_ns', 'st_ctime_ns')
    need(len(raw) == before.st_size and all(getattr(before, n) == getattr(opened, n) == getattr(after, n) == getattr(path.lstat(), n) for n in fields))
    return raw


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--php-assertions', choices=['on', 'off'], default='off')
    args = parser.parse_args(); os.umask(0o077)
    images = {Path(name).name: source(ROOT / name) for name in EXPECTED}
    need(all(hashlib.sha256(images[Path(name).name]).hexdigest() == digest for name, digest in EXPECTED.items()))
    images['legacy_paid_operator.php'] = source(Path(__file__).with_name('legacy_paid_operator.php'))
    php = shutil.which('php'); need(php is not None)
    with tempfile.TemporaryDirectory(prefix='creditsoft-modern-operator-') as temporary:
        owned = Path(temporary).resolve(); owned.chmod(0o700)
        for name, raw in images.items():
            with (owned / name).open('xb') as stream: stream.write(raw)
            (owned / name).chmod(0o600)
        result = subprocess.run([php, '-n', '-d', 'auto_prepend_file=', '-d', 'auto_append_file=',
            '-d', 'allow_url_fopen=0', '-d', 'allow_url_include=0', '-d', 'ffi.enable=0',
            '-d', 'zend.assertions=' + ('1' if args.php_assertions == 'on' else '-1'),
            '-d', 'disable_functions=' + DISABLED, '-d', 'open_basedir=' + str(owned),
            '-d', 'sys_temp_dir=' + str(owned), str(owned / 'legacy_paid_operator.php')],
            capture_output=True, timeout=30, env={'PATH': '/usr/bin:/bin', 'TMPDIR': str(owned)})
        need(result.returncode == 0 and result.stderr == b'' and len(result.stdout) <= 16384)
        value = json.loads(result.stdout)
        need(value['ok'] is True and value['checks'] == 63 and value['filesystem_writes'] == value['application_calls'] == 0 and
             value['readonly_dry_runs_preserved'] == 2 and value['modern_calendar_denials_preserved'] == 3 and
             value['composer_or_subprocess_invoked'] is False and value['historical_fallback_unchanged'] is True)
        need(set(p.name for p in owned.iterdir()) == set(images) and all((owned / name).read_bytes() == raw for name, raw in images.items()))
    need(all(hashlib.sha256(source(ROOT / name)).hexdigest() == digest for name, digest in EXPECTED.items()))
    print(json.dumps({**value, 'source_images_sha256': EXPECTED, 'php_assertions': args.php_assertions,
                      'owned_fixture_removed': True, 'application_bootstrapped': False}, sort_keys=True))


if __name__ == '__main__':
    try: main()
    except Exception:
        print(json.dumps({'ok': False, 'scope': 'modern-legacy-paid-operator-entrypoints', 'category': 'owned-fixture-stopped'}))
        raise SystemExit(1)
