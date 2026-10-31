// Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
// Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
// SPDX-License-Identifier: GPL-2.0-or-later. Child 2026.10.1 UNRELEASED.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const root = new URL('../', import.meta.url), repo = new URL('../../', root);
const flatten = rows => rows.flatMap(row => [row, ...flatten(row.elements || [])]);
const normalize = text => text.replace(/\s+/g, ' ').trim();
const blocks = html => [...html.matchAll(/<(h1|h2|p|ul)\b[^>]*>([\s\S]*?)<\/\1>/g)].map(match => ({tag:match[1],html:normalize(match[2])}));
const links = html => [...html.matchAll(/<a\b[^>]*href="([^"]+)"[^>]*>([\s\S]*?)<\/a>/g)].map(match => ({href:match[1],label:normalize(match[2])}));

for (const slug of ['cookies', 'privacy', 'terms']) {
    test(`${slug}: every policy block, list and link preserves published text and order`, async () => {
        const source = await readFile(new URL(`site-astro/src/pages/${slug}.astro`, repo), 'utf8');
        const main = source.match(/<main class="legal-page">([\s\S]*?)<\/main>/)[1];
        const page = JSON.parse(await readFile(new URL(`content/${slug}.elementor.json`, root), 'utf8'));
        const rows = flatten(page.content), widgets = rows.filter(row => row.widgetType);
        assert.equal(page.title, source.match(/const title = "([^"]+)"/)[1]);
        assert.equal(page.type, 'page'); assert.equal(page.version, '0.4');
        assert.equal(page.page_settings.template, 'templates/creditsoft-home.php');
        assert.equal(new Set(rows.map(row => row.id)).size, rows.length);
        assert.ok(widgets.every(row => ['heading', 'text-editor'].includes(row.widgetType)));
        const output = widgets.map(row => row.widgetType === 'heading'
            ? `<${row.settings.header_size}>${row.settings.title}</${row.settings.header_size}>`
            : row.settings.editor).join('\n');
        assert.deepEqual(blocks(output), blocks(main));
        assert.deepEqual(links(output), links(main));
        assert.equal(blocks(output).filter(block => block.tag === 'h1').length, 1);
        assert.ok(output.includes('Last Updated: February 2026'));
    });
}
