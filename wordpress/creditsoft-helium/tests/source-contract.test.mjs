// Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
// Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
// SPDX-License-Identifier: GPL-2.0-or-later. Child 2026.10.1 UNRELEASED.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const root = new URL('../', import.meta.url), repo = new URL('../../', root);
const read = name => readFile(new URL(name, root), 'utf8');

test('one child identity and actual parent fallback are bound', async () => {
    const version = (await read('VERSION')).trim();
    assert.match(version, /^\d{4}\.(?:[1-9]|1[0-2])\.[1-9]\d*$/);
    const style = await read('style.css'), yaml = await read('gantry/theme.yaml'), theme = await read('includes/theme.php');
    assert.ok(style.includes('Template: g5_helium\n'));
    assert.ok(style.includes(`Version: ${version}\n`));
    assert.ok(yaml.includes(`version: '${version}'`));
    assert.ok(yaml.includes('parent: g5_helium'));
    assert.ok(theme.includes("gantry-themes://{$gantry['theme.parent']}"));
    assert.ok(theme.includes("gantry-themes://{$gantry['theme.parent']}/common"));
});

test('explicit free homepage and menus preserve public source and wordmark', async () => {
    const page = JSON.parse(await read('content/homepage.elementor.json'));
    assert.equal(page.type, 'page'); assert.equal(page.version, '0.4');
    assert.equal(page.page_settings.template, 'templates/creditsoft-home.php');
    const widgets = [], ids = [];
    const walk = rows => rows.forEach(row => { ids.push(row.id); if (row.widgetType) widgets.push(row); walk(row.elements || []); });
    walk(page.content); assert.equal(new Set(ids).size, ids.length);
    assert.ok(widgets.every(row => ['heading', 'text-editor', 'button'].includes(row.widgetType)));
    const title = widgets.find(row => row.widgetType === 'heading' && row.settings.header_size === 'h1').settings.title;
    const source = await readFile(new URL('site-astro/src/lib/preview-content.ts', repo), 'utf8');
    assert.ok(source.includes(title));
    assert.ok(!JSON.stringify(page).includes('placeholder'));
    const navigation = JSON.parse(await read('content/navigation.json'));
    assert.equal(Object.keys(navigation).length, 5);
    assert.ok(navigation['creditsoft-primary'].some(row => row.url === '/tools/'));
    assert.equal(navigation['creditsoft-footer-social'].length, 4);
    assert.deepEqual(await readFile(new URL('assets/creditsoft-wordmark.png', root)),
        await readFile(new URL('site-astro/public/brand/creditsoft-wordmark.png', repo)));
});

test('activation is non-destructive and selected pages use the real content loop', async () => {
    const functions = await read('functions.php'), template = await read('templates/creditsoft-home.php');
    assert.ok(!/after_switch_theme|wp_insert_post|wp_update_post|update_option|wp_create_nav_menu/.test(functions));
    assert.match(template, /while \(have_posts\(\)\)/);
    assert.match(template, /the_post\(\)/); assert.match(template, /the_content\(\)/);
    assert.match(template, /get_header\(\)/); assert.match(template, /get_footer\(\)/);
});
