// Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
// Built Not Begged https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
// SPDX-License-Identifier: GPL-2.0-or-later. Adapter 2026.10.1 UNRELEASED.
// Guard public-source reuse and generated input drift; actual WordPress tests exercise routing/rendering.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { tools, sourceLink } from '../assets/catalog.mjs';
const component = new URL('../', import.meta.url);
const repository = new URL('../../', component);

test('adopted modules preserve published Astro source exactly', async () => {
    for (const name of ['catalog.mjs', 'client.mjs', 'model.mjs']) {
        assert.deepEqual(await readFile(new URL(`assets/${name}`, component)),
            await readFile(new URL(`site-astro/src/lib/financial-tools/${name}`, repository)));
    }
});

test('PHP catalog is generated from all twelve existing descriptors', async () => {
    const data = JSON.parse(await readFile(new URL('assets/catalog.json', component), 'utf8'));
    assert.deepEqual(data, { tools, sourceLink });
    assert.equal(new Set(tools.map(tool => tool.slug)).size, 12);
    assert.deepEqual(tools.map(tool => tool.slug).sort(), ['budget', 'comparison', 'negative', 'optimizer',
        'payoff', 'prep', 'readiness', 'recommendation', 'simulator', 'strategy', 'timeline', 'utilization']);
});

test('new component version is bound without changing inherited identities', async () => {
    const version = (await readFile(new URL('VERSION', component), 'utf8')).trim();
    assert.match(version, /^\d{4}\.[1-9]\d?\.[1-9]\d*$/);
    const block = JSON.parse(await readFile(new URL('blocks/financial-tool/block.json', component), 'utf8'));
    const php = await readFile(new URL('creditsoft-financial-tools.php', component), 'utf8');
    assert.equal(block.version, version);
    assert.ok(php.includes(` * Version: ${version}\n`));
    assert.ok(php.includes(`public const VERSION = '${version}';`));
});
