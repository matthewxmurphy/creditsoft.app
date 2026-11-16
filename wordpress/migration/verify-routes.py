#!/usr/bin/env python3
"""Read-only route/asset verification of a deployed CreditSoft WordPress site."""
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path
import hashlib
import html
import json
import re
import sys
import time
from urllib.error import HTTPError
from urllib.request import Request, urlopen

base = sys.argv[1].rstrip('/')
theme = Path(sys.argv[2])
manifest = json.loads((theme / 'content/preserved/manifest.json').read_text())

def fetch(path):
    try:
        with urlopen(Request(base + path, headers={'User-Agent': 'CreditSoft-WordPress-Verification/1.0'}), timeout=30) as response:
            return response.status, response.read(), response.geturl()
    except HTTPError as error:
        return error.code, error.read(), error.url

def plain(value):
    return ' '.join(html.unescape(re.sub('<[^>]+>', '', value)).split())

def check_page(page):
    route = page['route']
    status, body, final = fetch(route + '?creditsoft_verify=' + str(int(time.time())))
    text = body.decode(errors='replace')
    headings = [plain(h) for h in re.findall(r'<h1\b[^>]*>(.*?)</h1>', text, re.S | re.I)]
    errors = []
    if status != 200:
        errors.append('http_' + str(status))
    if 'creditsoft-preserved-design' not in text:
        errors.append('not_wordpress_preserved_theme')
    if page['heading'] and plain(page['heading']) not in headings:
        errors.append('missing_expected_heading')
    if text.count('<main') != 1:
        errors.append('main_count_' + str(text.count('<main')))
    if 'A few reasons why Pay might be the right fit' in text or 'Lorem ipsum dolor sit amet' in text:
        errors.append('demo_content')
    if 'CreditSoft Preserved Design' in text or '__CREDITSOFT_ASSET_BASE__' in text:
        errors.append('unresolved_template')
    return {'route': route, 'status': status, 'errors': errors}

def check_asset(file):
    path = file.relative_to(theme / 'assets/preserved').as_posix()
    status, body, final = fetch('/wp-content/themes/creditsoft-helium/assets/preserved/' + path)
    expected = hashlib.sha256(file.read_bytes()).hexdigest()
    return {'asset': path, 'status': status, 'match': status == 200 and hashlib.sha256(body).hexdigest() == expected}

with ThreadPoolExecutor(max_workers=5) as pool:
    pages = list(pool.map(check_page, manifest['pages']))
    assets = list(pool.map(check_asset, [p for p in (theme / 'assets/preserved').rglob('*') if p.is_file() and not p.name.startswith('._')]))
errors = [p for p in pages if p['errors']] + [a for a in assets if not a['match']]
status, body, final = fetch('/creditsoft-missing-route-verification/')
if status != 404 or b'That page is not here.' not in body:
    errors.append({'route': 'missing-control', 'status': status})
result = {'base': base, 'page_count': len(pages), 'asset_count': len(assets), 'errors': errors, 'pages': pages}
if len(sys.argv) > 3:
    Path(sys.argv[3]).write_text(json.dumps(result, indent=2) + '\n')
print(json.dumps({key: result[key] for key in ['base', 'page_count', 'asset_count', 'errors']}, indent=2))
sys.exit(bool(errors))
