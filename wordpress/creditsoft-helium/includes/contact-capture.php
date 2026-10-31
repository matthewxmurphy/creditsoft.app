<?php
/**
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support https://builtnotbegged.com; recorded policy identity Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later. Child 2026.10.1 UNRELEASED.
 * Explicit Fluent Forms binding only; imports, submissions and validation stay with the real plugin.
 */
defined('ABSPATH') || exit;

/** Read one existing, published, regular non-payment form without modifying it. */
function creditsoft_helium_intake_form(int $formId)
{
    if ($formId < 1 || !shortcode_exists('fluentform') || !class_exists('FluentForm\\App\\Models\\Form')) {
        return null;
    }
    $form = \FluentForm\App\Models\Form::find($formId);
    if (!$form || $form->status !== 'published' || $form->type !== 'form'
        || (int) $form->has_payment !== 0 || trim((string) $form->title) === '') {
        return null;
    }
    $fields = json_decode((string) $form->form_fields, true);
    return is_array($fields) && !empty($fields['fields']) ? $form : null;
}

/** Reusable native form placement; invalid or unavailable bindings emit no dead controls. */
function creditsoft_helium_intake_shortcode($attributes): string
{
    $attributes = shortcode_atts(['form_id' => ''], is_array($attributes) ? $attributes : [], 'creditsoft_intake');
    $value = $attributes['form_id'];
    if (!is_string($value) && !is_int($value)) {
        return '';
    }
    $formId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($formId === false || !creditsoft_helium_intake_form($formId)) {
        return '';
    }
    return do_shortcode('[fluentform id="' . $formId . '"]');
}
add_shortcode('creditsoft_intake', 'creditsoft_helium_intake_shortcode');

/** Read-only export preparation; choose an existing form and import the resulting Page deliberately. */
function creditsoft_helium_contact_template(int $formId): array
{
    if (!creditsoft_helium_intake_form($formId)) {
        throw new InvalidArgumentException('Select an existing published Fluent Forms contact form.');
    }
    $path = get_stylesheet_directory() . '/content/contact-us.elementor.json';
    if (!is_readable($path)) {
        throw new RuntimeException('CreditSoft Contact source template is unavailable.');
    }
    $template = json_decode((string) file_get_contents($path), true);
    if (!is_array($template) || !isset($template['content']) || !is_array($template['content'])) {
        throw new RuntimeException('CreditSoft Contact source template is unavailable.');
    }
    $marker = '[creditsoft_intake form_id="__CREDITSOFT_FORM_ID__"]';
    $bound = 0;
    $walk = static function (array &$elements) use (&$walk, $marker, $formId, &$bound): void {
        foreach ($elements as &$element) {
            if (!is_array($element)) {
                continue;
            }
            if (($element['widgetType'] ?? null) === 'shortcode' && ($element['settings']['shortcode'] ?? null) === $marker) {
                $element['settings']['shortcode'] = '[creditsoft_intake form_id="' . $formId . '"]';
                $bound++;
            }
            if (isset($element['elements']) && is_array($element['elements'])) {
                $walk($element['elements']);
            }
        }
    };
    $walk($template['content']);
    if ($bound !== 1) {
        throw new RuntimeException('CreditSoft Contact source binding is invalid.');
    }
    return creditsoft_helium_rebase_template_links($template);
}
