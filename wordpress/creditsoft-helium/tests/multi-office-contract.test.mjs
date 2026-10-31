// Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
// Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
// SPDX-License-Identifier: GPL-2.0-or-later. Child 2026.10.1 UNRELEASED.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
const root = new URL('../', import.meta.url), repo = new URL('../../', root);
const read = name => readFile(new URL(name, root), 'utf8');
const flatten = rows => rows.flatMap(row => [row, ...flatten(row.elements || [])]);
const plain = text => text.replace(/<[^>]+>/g, '').replaceAll('&amp;', '&').replaceAll('&lt;', '<').replaceAll('&gt;', '>');
async function fixture() {
    const source = await readFile(new URL('site-astro/src/lib/site-lanes.ts', repo), 'utf8');
    const renderer = await readFile(new URL('site-astro/src/pages/[slug].astro', repo), 'utf8');
    const lanes = vm.runInNewContext('(' + source.match(/export const lanes: Lane\[\] = ([\s\S]*);\s*$/)[1] + ')', { proofBase: '/assets/images/product-proof' });
    const lane = lanes.find(row => row.slug === 'multi-office-nodes');
    const page = JSON.parse(await read('content/multi-office-nodes.elementor.json'));
    const rows = flatten(page.content), widgets = rows.filter(row => row.widgetType);
    return { lane, renderer, page, rows, widgets };
}

test('one editable page preserves every selected lane and generic-renderer text', async () => {
    const { lane, renderer, page, rows, widgets } = await fixture();
    assert.equal(page.type, 'page'); assert.equal(page.version, '0.4');
    assert.equal(page.page_settings.template, 'templates/creditsoft-home.php');
    assert.equal(page.content[0].settings.css_classes, 'creditsoft-home creditsoft-marketing creditsoft-multi-office');
    assert.equal(new Set(rows.map(row => row.id)).size, rows.length);
    assert.ok(widgets.every(row => ['heading', 'text-editor', 'button', 'image'].includes(row.widgetType)));
    const context = renderer.match(/: "(Click the screenshot[^"\n]+)"/)[1];
    const expected = [lane.eyebrow, lane.title, lane.summary, 'Start intake', 'Login', lane.proofTitle,
        'Product proof', `${lane.proofTitle} in the CreditSoft workspace`, context, 'Back to feature overview',
        ...lane.detailSections.flatMap(section => [section.eyebrow, section.title, section.text, ...section.items.flatMap(item => [item.title, item.text])]),
        ...lane.bullets.flatMap((bullet, index) => [bullet, lane.bulletDetails[index]])];
    const actual = widgets.map(row => row.settings.title || row.settings.text || row.settings.editor || row.settings.caption).map(plain);
    assert.deepEqual(actual, expected);
    assert.equal(widgets.filter(row => row.settings.header_size === 'h1').length, 1);
});

test('the approved diagram is byte-identical, full-size and locally lightboxed with exact alt/caption', async () => {
    const { lane, widgets } = await fixture();
    const images = widgets.filter(row => row.widgetType === 'image'); assert.equal(images.length, 1);
    const settings = images[0].settings;
    assert.equal(settings.image.url, lane.image); assert.equal(settings.image.id, 0);
    assert.equal(settings.image.alt, lane.alt); assert.equal(settings.image_size, 'full');
    assert.equal(settings.caption_source, 'custom'); assert.equal(settings.caption, lane.proofTitle);
    assert.equal(settings.link_to, 'file'); assert.equal(settings.open_lightbox, 'yes');
    const asset = await readFile(new URL('assets/multi-office-node-topology.png', root));
    assert.deepEqual(asset, await readFile(new URL('site-astro/public' + lane.image, repo)));
    assert.equal(asset.subarray(0, 8).toString('hex'), '89504e470d0a1a0a');
    assert.equal(asset.readUInt32BE(16), 1600); assert.equal(asset.readUInt32BE(20), 1000);
});

test('CTA paths stay literal and preparation allowlists only the selected static image', async () => {
    const { renderer, widgets } = await fixture();
    const links = widgets.filter(row => row.widgetType === 'button').map(row => row.settings.link.url);
    assert.deepEqual(links, ['/contact-us', '/login', '/features']);
    for (const url of links) assert.ok(renderer.includes(`href="${url}"`));
    const functions = await read('functions.php');
    assert.ok(functions.includes("'multi-office-nodes' => 'multi-office-nodes.elementor.json'"));
    const imageBranch = functions.slice(functions.indexOf("if ($slug === 'multi-office-nodes')"));
    assert.ok(imageBranch.includes("=== '/assets/images/product-proof/multi-office-node-topology.png'"));
    assert.ok(imageBranch.includes("get_stylesheet_directory_uri() . '/assets/multi-office-node-topology.png'"));
    assert.ok(!/file_get_contents|wp_remote|curl_|wp_insert|wp_update|update_option/.test(imageBranch));
});
