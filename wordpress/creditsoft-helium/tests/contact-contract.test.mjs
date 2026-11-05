// Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
// Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
// SPDX-License-Identifier: GPL-2.0-or-later. Child 2026.10.1 UNRELEASED.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const root = new URL('../', import.meta.url), repo = new URL('../../', root);
const read = name => readFile(new URL(name, root), 'utf8');
const flatten = rows => rows.flatMap(row => [row, ...flatten(row.elements || [])]);
const normalize = text => text.replace(/\s+/g, ' ').trim();
const plain = text => normalize(text.replace(/<[^>]+>/g, '').replaceAll('&amp;', '&'));

test('Contact preserves the published body and every passive link with one explicit binding', async () => {
    const source = await readFile(new URL('site-astro/src/pages/contact-us.astro', repo), 'utf8');
    const page = JSON.parse(await read('content/contact-us.elementor.json'));
    const rows = flatten(page.content), widgets = rows.filter(row => row.widgetType);
    assert.equal(new Set(rows.map(row => row.id)).size, rows.length);
    assert.equal(page.page_settings.template, 'templates/creditsoft-home.php');
    const hero = widgets.find(row => row.settings.header_size === 'h1');
    assert.equal(hero.settings.title, source.match(/<h1 class="display">([^<]+)<\/h1>/)[1]);
    const body = widgets.filter(row => row.widgetType === 'text-editor').map(row => plain(row.settings.editor));
    for (const match of source.matchAll(/<p(?: class="lede")?>\s*([\s\S]*?)<\/p>/g)) assert.ok(body.includes(plain(match[1])));
    for (const match of source.matchAll(/<li>([^<]+)<\/li>/g)) assert.ok(body.some(text => text.includes(match[1])));
    const expectedLinks = [...source.matchAll(/<a[^>]+href="([^"]+)"[^>]*>([^<]+)<\/a>/g)].map(match => [match[2], match[1].replaceAll('&amp;', '&')]);
    assert.deepEqual(widgets.filter(row => row.widgetType === 'button').map(row => [row.settings.text, row.settings.link.url]), expectedLinks);
    assert.deepEqual(widgets.filter(row => row.widgetType === 'shortcode').map(row => row.settings.shortcode), ['[creditsoft_intake form_id="__CREDITSOFT_FORM_ID__"]']);
    assert.ok(widgets.every(row => ['heading', 'text-editor', 'button', 'shortcode'].includes(row.widgetType)));
});

test('the explicit native form has required name/email, local storage and no delivery feeds', async () => {
    const exports = JSON.parse(await read('content/contact-form.fluentforms.json'));
    assert.equal(exports.length, 1); const form = exports[0];
    assert.equal(form.title, 'CreditSoft local capture'); assert.equal(form.status, 'published');
    assert.equal(form.type, 'form'); assert.equal(form.has_payment, 0);
    const fields = form.form_fields.fields;
    assert.deepEqual(fields.map(field => [field.element, field.attributes.name]), [['input_text', 'name'], ['input_email', 'email']]);
    assert.ok(fields.every(field => field.settings.validation_rules.required.value === true));
    assert.equal(fields[1].settings.validation_rules.email.value, true);
    assert.deepEqual(form.metas.map(meta => meta.meta_key), ['formSettings', 'notifications']);
    assert.deepEqual(JSON.parse(form.metas[1].value), []);
    const settings = JSON.parse(form.metas[0].value);
    assert.equal(settings.delete_entry_on_submission, 'no');
    assert.equal(settings.form_extra_css_class, 'creditsoft-local-capture');
    assert.equal(settings.confirmation.redirectTo, 'samePage');
    assert.equal(settings.confirmation.messageToShow, 'Submission saved.');
    assert.equal(form.form_fields.submitButton.attributes.type, 'submit');
});
