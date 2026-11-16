// Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
// Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
// SPDX-License-Identifier: GPL-2.0-or-later. Child 2026.10.1 UNRELEASED.
import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
const root = new URL('../', import.meta.url);
const read = name => readFile(new URL(name, root), 'utf8');

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
