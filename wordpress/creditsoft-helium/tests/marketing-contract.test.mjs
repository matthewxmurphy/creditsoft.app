// Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
// Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
// SPDX-License-Identifier: GPL-2.0-or-later. Child 2026.10.1 UNRELEASED.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
const root = new URL('../', import.meta.url), repo = new URL('../../', root);
const slugs = ['about-us', 'resources', 'docs'];
const read = name => readFile(new URL(name, root), 'utf8');
const normalize = value => value.replace(/\s+/g, ' ').trim();
const plain = value => normalize(value.replace(/<[^>]+>/g, '').replaceAll('&amp;', '&').replaceAll('&lt;', '<').replaceAll('&gt;', '>'));
const elements = rows => rows.flatMap(row => [row, ...elements(row.elements || [])]);
const sourceArray = (source, name) => vm.runInNewContext('(' + source.match(new RegExp('const ' + name + ' = ([\\s\\S]*?);'))[1] + ')');

async function fixture(slug) {
    const page = JSON.parse(await read(`content/${slug}.elementor.json`));
    const source = await readFile(new URL(`site-astro/src/pages/${slug}.astro`, repo), 'utf8');
    const rows = elements(page.content), widgets = rows.filter(row => row.widgetType);
    const copy = widgets.map(row => row.settings.title || row.settings.text || row.settings.editor || '').map(plain);
    return { page, source, rows, widgets, copy };
}

test('three explicit free-page exports have unique IDs and no active integration widgets', async () => {
    for (const slug of slugs) {
        const { page, rows, widgets } = await fixture(slug);
        assert.equal(page.type, 'page'); assert.equal(page.version, '0.4');
        assert.equal(page.page_settings.template, 'templates/creditsoft-home.php');
        assert.equal(page.content[0].settings.css_classes, 'creditsoft-home creditsoft-marketing');
        assert.equal(new Set(rows.map(row => row.id)).size, rows.length);
        assert.ok(widgets.every(row => ['heading', 'text-editor', 'button'].includes(row.widgetType)));
        assert.equal(widgets.filter(row => row.widgetType === 'heading' && row.settings.header_size === 'h1').length, 1);
        assert.ok(!JSON.stringify(page).match(/<(?:script|iframe|form)\b/i));
    }
});

test('displayed copy and CTA destinations come from the exact public Astro bodies', async () => {
    for (const slug of slugs) {
        const { source, widgets, copy } = await fixture(slug);
        const renderedSource = source.replace('{"{cuid}"}', '{cuid}');
        const sourceViews = [normalize(renderedSource), plain(renderedSource)];
        for (const text of copy) {
            if (slug === 'about-us' && /^0[123]$/.test(text)) continue; // The source renders its three story indices.
            assert.ok(sourceViews.some(view => view.includes(text)), `${slug}: exported text must exist in source`);
        }
        for (const row of widgets.filter(row => row.widgetType === 'button')) {
            assert.ok(source.includes(`href="${row.settings.link.url}"`));
        }
        const staticParagraphs = [...source.matchAll(/<p(?:\s[^>]*)?>([\s\S]*?)<\/p>/g)].map(match => match[1]).filter(text => !text.includes('{'));
        const sectionDescriptions = [...source.matchAll(/\btext="([^"]+)"/g)].map(match => match[1]);
        for (const text of [...staticParagraphs, ...sectionDescriptions]) {
            assert.ok(copy.includes(plain(text)), `${slug}: published static paragraph must be retained`);
        }
    }
    const about = await fixture('about-us');
    for (const paragraph of sourceArray(about.source, 'story')) assert.ok(about.copy.includes(paragraph));
    const resources = await fixture('resources');
    for (const section of sourceArray(resources.source, 'guideSections')) {
        assert.ok(resources.copy.includes(section.title));
        for (const [title, paragraph] of section.items) {
            assert.ok(resources.copy.includes(title)); assert.ok(resources.copy.includes(paragraph));
        }
    }
    const docs = await fixture('docs');
    for (const [route, note] of sourceArray(docs.source, 'docs')) {
        assert.ok(docs.copy.includes(route)); assert.ok(docs.copy.includes(note));
    }
});

test('guide anchors are unique and fixed export selection does not add routes or page writes', async () => {
    const resources = await fixture('resources');
    const anchors = resources.rows.map(row => row.settings._element_id).filter(Boolean);
    assert.deepEqual(anchors.toSorted(), ['admin-guide', 'ai-settings', 'crm-guide', 'user-guide']);
    for (const row of resources.widgets.filter(row => row.widgetType === 'button')) {
        const url = row.settings.link.url;
        if (url.startsWith('#')) assert.ok(anchors.includes(url.slice(1)));
    }
    assert.ok(resources.copy.some(text => text.includes('/resources#/user-guide/introduction')));
    const functions = await read('functions.php');
    const helper = functions.slice(functions.indexOf('function creditsoft_helium_marketing_template'));
    for (const slug of slugs) assert.ok(helper.includes(`'${slug}' => '${slug}.elementor.json'`));
    assert.ok(helper.includes('return creditsoft_helium_rebase_template_links($template);'));
    assert.ok(!/wp_insert_post|wp_update_post|update_option|add_action|add_filter|redirect/.test(helper));
    const alias = await readFile(new URL('site-astro/src/pages/about.astro', repo), 'utf8');
    assert.ok(alias.includes('const target = "/about-us";'));
});
