<?php
/**
 * Focused disposable WordPress/Elementor/Fluent Forms Contact capture acceptance.
 * Copyright 2026 Matthew C. Murphy <https://MatthewxMurphy.com>.
 * Product/support https://builtnotbegged.com; recorded identity Matthew X Murphy.
 * SPDX-License-Identifier: GPL-2.0-or-later
 * Synthetic local entries only. No DNS, CRM, transport or existing calculator suite.
 */
if (!defined('WP_CLI') || !WP_CLI || !defined('CREDITSOFT_CONTACT_TEST_SITE')
    || CREDITSOFT_CONTACT_TEST_SITE !== 'task118-contact-disposable'
    || preg_match('/^cscontact118_[a-f0-9]{8}$/D', DB_NAME) !== 1
    || wp_parse_url(home_url(), PHP_URL_HOST) !== '127.0.0.1') throw new RuntimeException('Use only the disposable Contact fixture.');
$origin = getenv('CREDITSOFT_CONTACT_HTTP_ORIGIN');
if (!is_string($origin) || preg_match('~^http://cscontact118-[a-f0-9]{8}-wp$~D', $origin) !== 1) throw new RuntimeException('Missing owned HTTP origin.');
$checks = 0; $requests = 0;
$check = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) throw new RuntimeException($label); };
$request = static function (string $path, ?array $body = null) use ($origin, &$requests): array {
    ++$requests;
    $args = ['redirection' => 0, 'timeout' => 25, 'headers' => ['Host' => wp_parse_url(home_url(), PHP_URL_HOST) . ':' . wp_parse_url(home_url(), PHP_URL_PORT)]];
    if ($body !== null) { $args['method'] = 'POST'; $args['body'] = $body; }
    $response = wp_remote_request($origin . $path, $args);
    if (is_wp_error($response)) throw new RuntimeException('Owned HTTP request failed.');
    return [(int) wp_remote_retrieve_response_code($response), wp_remote_retrieve_body($response)];
};
$dom = static function (string $html): DOMXPath {
    $old = libxml_use_internal_errors(true);
    try { $doc = new DOMDocument(); $doc->loadHTML($html, LIBXML_NONET); return new DOMXPath($doc); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
};
$plain = static fn (string $html): string => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
$collectShortcodes = static function (array $elements) use (&$collectShortcodes): array {
    $out = [];
    foreach ($elements as $element) {
        if (($element['widgetType'] ?? null) === 'shortcode') $out[] = $element['settings']['shortcode'] ?? null;
        if (isset($element['elements']) && is_array($element['elements'])) $out = array_merge($out, $collectShortcodes($element['elements']));
    }
    return $out;
};
$heading = 'Talk to CreditSoft before checkout if the rollout needs a human plan.';
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
if (!$admins) throw new RuntimeException('Synthetic core administrator required.');
wp_set_current_user((int) $admins[0]);
try {
    $check(defined('FLUENTFORM_VERSION') && FLUENTFORM_VERSION === '6.2.16', 'Actual official Fluent Forms Free release is loaded.');
    $check(!defined('FLUENTFORMPRO'), 'No Fluent Forms Pro integration is loaded.');
    $frontBefore = get_option('page_on_front');
    $formsBefore = \FluentForm\App\Models\Form::count();
    $file = get_stylesheet_directory() . '/content/contact-form.fluentforms.json';
    $import = \FluentForm\App\Services\Transfer\TransferService::importForms(new \FluentForm\Framework\Http\Request\File($file, basename($file), 'application/json'));
    $ids = array_keys($import['inserted_forms']);
    $check(count($ids) === 1 && \FluentForm\App\Models\Form::count() === $formsBefore + 1, 'Explicit native importer creates exactly one new local form.');
    $formId = (int) $ids[0];
    $form = \FluentForm\App\Models\Form::find($formId);
    $check($form->status === 'published' && !$form->has_payment && $form->type === 'form', 'Imported form is published, regular and non-payment.');
    $check(\FluentForm\App\Models\FormMeta::retrieve('notifications', $formId, []) === [], 'Imported notifications are disabled.');
    $metaKeys = \FluentForm\App\Models\FormMeta::where('form_id', $formId)->pluck('meta_key')->all();
    $check(count(array_filter($metaKeys, static fn ($key): bool => str_contains($key, 'feed') || str_contains($key, 'webhook'))) === 0, 'No integration feed or webhook is imported.');
    $data = creditsoft_helium_contact_template($formId);
    $shortcode = '[creditsoft_intake form_id="' . $formId . '"]';
    $check($collectShortcodes($data['content']) === [$shortcode], 'Prepared Contact binds exactly the explicitly chosen real form.');
    $check(get_option('page_on_front') === $frontBefore && \FluentForm\App\Models\Form::count() === $formsBefore + 1, 'Reading prepared source does not import another form or select a homepage.');
    $check(do_shortcode('[creditsoft_intake]') === '' && do_shortcode('[creditsoft_intake form_id="999999"]') === '', 'Missing and nonexistent bindings render no form.');
    $thrown = false;
    try { creditsoft_helium_contact_template(999999); } catch (InvalidArgumentException $failure) { $thrown = true; }
    $check($thrown, 'Explicit preparation rejects a nonexistent binding.');
    $bindingOriginal = ['status' => $form->status, 'type' => $form->type, 'has_payment' => $form->has_payment, 'form_fields' => $form->form_fields];
    foreach ([['status' => 'unpublished'], ['has_payment' => 1], ['form_fields' => '{}']] as $override) {
        try {
            \FluentForm\App\Models\Form::where('id', $formId)->update($override);
            $check(do_shortcode($shortcode) === '', 'Unavailable, payment or malformed real form binding renders no controls.');
            $rejected = false;
            try { creditsoft_helium_contact_template($formId); } catch (InvalidArgumentException $failure) { $rejected = true; }
            $check($rejected, 'Explicit preparation refuses this unusable real form binding.');
        } finally { \FluentForm\App\Models\Form::where('id', $formId)->update($bindingOriginal); }
    }
    $check(do_shortcode('[creditsoft_intake form_id="1x"]') === '', 'Malformed selector is not coerced to another form.');
    $beforeBase = home_url();
    $base = static fn (string $url, string $path): string => 'http://[2001:db8::118]/~fixture' . ($path === '' ? '' : '/' . ltrim($path, '/'));
    add_filter('home_url', $base, 10, 2);
    try { $rebased = wp_json_encode(creditsoft_helium_contact_template($formId), JSON_UNESCAPED_SLASHES); }
    finally { remove_filter('home_url', $base, 10); }
    $check(str_contains($rebased, 'http://[2001:db8::118]/~fixture/pricing-plan') && home_url() === $beforeBase, 'Contact links preserve an explicit IPv6 preview base without fetching it.');

    $savePage = static function (string $slug, array $template): object {
        $page = \Elementor\Plugin::instance()->documents->create('wp-page', ['post_title' => $template['title'], 'post_status' => 'publish', 'post_name' => $slug]);
        if (is_wp_error($page)) throw new RuntimeException('Owned page creation failed.');
        if (!$page->save(['elements' => $template['content'], 'settings' => array_merge($template['page_settings'] ?? [], ['template' => 'templates/creditsoft-home.php'])])) throw new RuntimeException('Owned Elementor save failed.');
        update_post_meta($page->get_main_id(), '_wp_page_template', 'templates/creditsoft-home.php');
        return $page;
    };
    $contact = $savePage('contact-us', $data);
    [$status, $html] = $request('/contact-us/'); $xp = $dom($html); $copy = $plain($html);
    $check($status === 200 && $xp->query('//h1[normalize-space(.)="' . $heading . '"]')->length === 1, 'Actual Contact renders its source heading once.');
    foreach (['How many active clients the office manages', 'Which monitoring system or report source is in use', 'Who should own the CreditSoft account dashboard and license email', 'Checkout lane', 'What helps us route it'] as $text) $check(str_contains($copy, $text), 'Source Contact routing copy is retained.');
    $check(str_contains($html, 'mailto:hello@creditsoft.app') && str_contains($html, '/checkout/?plan=enterprise'), 'Source Contact links are retained without following them.');
    $check($xp->query('//form[contains(concat(" ", normalize-space(@class), " "), " fluent_form_")]')->length === 1, 'Actual Contact contains one native Fluent Form.');
    $check($xp->query('//input[@name="name"]')->length === 1 && $xp->query('//input[@name="email" and @type="email"]')->length === 1, 'Required name and email controls are delivered through the real plugin.');
    $hidden = [];
    foreach ($xp->query('//form//input[@type="hidden" and @name]') as $input) $hidden[$input->getAttribute('name')] = $input->getAttribute('value');
    $entryCount = static fn (): int => \FluentForm\App\Models\Submission::where('form_id', $formId)->count();
    $beforeEntries = $entryCount();
    $mailBefore = is_file('/tmp/contact-mail-attempts') ? file_get_contents('/tmp/contact-mail-attempts') : '';
    $externalBefore = is_file('/tmp/contact-http-attempts') ? file_get_contents('/tmp/contact-http-attempts') : '';
    foreach ([['email' => 'missing@example.invalid'], ['name' => 'Missing email'], ['name' => 'Malformed email', 'email' => 'not-an-email']] as $bad) {
        [$status, $body] = $request('/wp-admin/admin-ajax.php', ['action' => 'fluentform_submit', 'form_id' => $formId, 'data' => http_build_query(array_merge($hidden, $bad))]);
        $json = json_decode($body, true);
        $check($status === 423 && is_array($json) && !empty($json['errors']), 'Actual public AJAX rejects missing or malformed input.');
        $check($entryCount() === $beforeEntries, 'Rejected input creates no local entry.');
    }
    [$status, $body] = $request('/wp-admin/admin-ajax.php', ['action' => 'fluentform_submit', 'form_id' => $formId, 'data' => http_build_query(array_merge($hidden, ['name' => 'Native Local Capture', 'email' => 'native@example.invalid']))]);
    $json = json_decode($body, true);
    $check($status === 200 && ($json['success'] ?? false) === true && $entryCount() === $beforeEntries + 1, 'Actual public AJAX saves exactly one synthetic local entry.');
    $saved = json_decode(\FluentForm\App\Models\Submission::where('form_id', $formId)->orderBy('id', 'DESC')->first()->response, true);
    $check(($saved['name'] ?? null) === 'Native Local Capture' && ($saved['email'] ?? null) === 'native@example.invalid', 'Stored local fields match the submitted synthetic input.');
    $check((is_file('/tmp/contact-mail-attempts') ? file_get_contents('/tmp/contact-mail-attempts') : '') === $mailBefore, 'Capture made no WordPress mail call.');
    $check((is_file('/tmp/contact-http-attempts') ? file_get_contents('/tmp/contact-http-attempts') : '') === $externalBefore, 'Capture made no external WordPress HTTP call.');

    $originalFields = $form->form_fields; $originalTitle = $form->title;
    $fields = json_decode($originalFields, true);
    foreach ($fields['fields'] as &$field) if (($field['attributes']['name'] ?? '') === 'name') $field['settings']['label'] = 'Temporary locally edited name';
    unset($field);
    $service = new \FluentForm\App\Services\Form\FormService();
    $service->update(['form_id' => $formId, 'title' => $originalTitle, 'formFields' => wp_json_encode($fields), 'status' => 'published']);
    creditsoft_helium_contact_template($formId);
    [$status, $editedForm] = $request('/contact-us/');
    $check($status === 200 && str_contains($editedForm, 'Temporary locally edited name'), 'Reading Contact source preserves an actual native form edit.');
    $service->update(['form_id' => $formId, 'title' => $originalTitle, 'formFields' => $originalFields, 'status' => 'published']);
    $edited = $data['content']; $changed = false;
    $walk = static function (array &$elements) use (&$walk, &$changed, $heading): void {
        foreach ($elements as &$element) {
            if (($element['widgetType'] ?? '') === 'heading' && ($element['settings']['title'] ?? '') === $heading) { $element['settings']['title'] = 'Temporary local Contact edit'; $changed = true; }
            if (isset($element['elements']) && is_array($element['elements'])) $walk($element['elements']);
        }
    };
    $walk($edited);
    $check($changed && $contact->save(['elements' => $edited, 'settings' => ['template' => 'templates/creditsoft-home.php']]), 'Actual Contact document remains editable.');
    creditsoft_helium_contact_template($formId);
    [$status, $editedHtml] = $request('/contact-us/');
    $check($status === 200 && str_contains($editedHtml, 'Temporary local Contact edit'), 'Source preparation preserves the saved page edit.');
    $check($contact->save(['elements' => $data['content'], 'settings' => ['template' => 'templates/creditsoft-home.php']]), 'Original Contact content is explicitly restored.');

    $widget = ['id' => 'capture1', 'elType' => 'widget', 'widgetType' => 'shortcode', 'settings' => ['shortcode' => $shortcode], 'elements' => []];
    $homepage = creditsoft_helium_homepage_template(); $homepage['content'][] = $widget;
    $home = $savePage('capture-home', $homepage);
    update_option('show_on_front', 'page'); update_option('page_on_front', $home->get_main_id());
    [$status, $homeHtml] = $request('/');
    $check($status === 200 && $dom($homeHtml)->query('//input[@name="email"]')->length === 1, 'Explicit native homepage placement delivers the chosen form.');
    $parent = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'tools', 'post_title' => 'Local tools placement']);
    $tools = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'budget', 'post_parent' => $parent, 'post_title' => 'Local tools capture', 'post_content' => '[creditsoft_tool slug="budget"]' . $shortcode]);
    update_post_meta($tools, '_wp_page_template', 'templates/creditsoft-home.php');
    [$status, $toolsHtml] = $request('/tools/budget/');
    $check($status === 200 && $dom($toolsHtml)->query('//input[@name="email"]')->length === 1 && str_contains($toolsHtml, 'data-financial-tool'), 'Explicit chosen tools page consumes the same native form beside its existing calculator.');
    $check(\FluentForm\App\Models\Form::count() === $formsBefore + 1 && $entryCount() === $beforeEntries + 1, 'Rendering placements neither creates a second form nor submits another entry.');
    echo 'PASS: ' . $checks . ' Contact capture checks; ' . $requests . ' actual HTTP requests; WP ' . get_bloginfo('version') . '; PHP ' . PHP_VERSION . '; Fluent Forms ' . FLUENTFORM_VERSION . ".\n";
} catch (Throwable $failure) {
    echo 'FAIL after ' . $checks . ' checks; ' . $requests . " HTTP requests.\n";
    throw $failure;
}
