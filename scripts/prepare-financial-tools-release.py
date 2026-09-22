#!/usr/bin/env python3
"""Assemble a bounded static release; never copy the whole site or any PHP config."""
import hashlib, json, re, shutil, sys, xml.etree.ElementTree as ET
from datetime import date
from pathlib import Path
root=Path(__file__).resolve().parents[1]
dist=root/'site-astro/dist'; previous=root/'web'; dest=Path(sys.argv[1]).resolve()
if dest.exists(): raise SystemExit('Use a fresh release directory.')
old=(previous/'index.html').read_text(); new=(dist/'index.html').read_text()
stripped=re.sub(r'<a\b[^>]*href="/tools/"[^>]*>.*?</a>', '', new)
assert re.sub(r'\s+','',old)==re.sub(r'\s+','',stripped), 'Homepage has changes beyond tools links; review before release.'
dest.mkdir(parents=True)
files=[Path('index.html')]+[p.relative_to(dist) for p in (dist/'tools').rglob('*.html')]
assert len(files)==14, 'Expected twelve tools and one index.'
for p in (dist/'_astro').iterdir():
 rel=p.relative_to(dist); current=previous/rel
 if current.exists():
  assert current.read_bytes()==p.read_bytes(), f'Existing fingerprinted asset changed: {rel}'
 else: files.append(rel)
for rel in files:
 (dest/rel).parent.mkdir(parents=True,exist_ok=True)
 shutil.copy2(dist/rel,dest/rel)
# Keep old sitemap dates and entries intact; append only the new tools URLs.
ns='http://www.sitemaps.org/schemas/sitemap/0.9';ET.register_namespace('',ns)
xml=ET.parse(previous/'sitemap.xml');known={u.find(f'{{{ns}}}loc').text for u in xml.getroot()}
for path in sorted((dist/'tools').rglob('index.html')):
 url='https://www.creditsoft.app/'+str(path.parent.relative_to(dist))+'/'
 assert url not in known, f'Existing tools URL needs explicit reconciliation: {url}'
 node=ET.SubElement(xml.getroot(),f'{{{ns}}}url');ET.SubElement(node,f'{{{ns}}}loc').text=url;ET.SubElement(node,f'{{{ns}}}lastmod').text=date.today().isoformat()
ET.indent(xml,space='  ');xml.write(dest/'sitemap.xml',encoding='utf-8',xml_declaration=True)
files.append(Path('sitemap.xml'))
hash_file=lambda p:hashlib.sha256(p.read_bytes()).hexdigest()
manifest={str(p):{'sha256':hash_file(dest/p),'before':hash_file(previous/p) if (previous/p).exists() else None} for p in sorted(files)}
(dest/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
print(json.dumps({'files':len(manifest),'directory':str(dest),'sha256':hash_file(dest/'manifest.json')},indent=2))
