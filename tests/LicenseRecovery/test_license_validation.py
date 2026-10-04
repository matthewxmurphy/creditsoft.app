"""Pure request tests plus isolated PHP/legacy-exit integration; synthetic data only.

Run: python3 -m unittest discover -s tests/LicenseRecovery -v
No production request, database, user, key or credential is used by this suite.
"""
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest


ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which('php')
KEY = 'SYNTHETICAAAAAAAAAAAAAAAAAAAAAAAA'
OTHER = 'SYNTHETICBBBBBBBBBBBBBBBBBBBBBBBB'


@unittest.skipUnless(PHP, 'PHP CLI is required')
class RequestTests(unittest.TestCase):
    def call(self, operation, **values):
        result = subprocess.run([PHP, str(Path(__file__).with_name('helper.php'))],
                                input=json.dumps({'operation': operation, **values}), text=True,
                                capture_output=True, check=True)
        self.assertEqual(result.stderr, '')
        return json.loads(result.stdout)

    def normalize(self, method='POST', query=None, form=None, raw=None, content_type='application/json',
                  content_length=None, query_string=''):
        return self.call('normalize', method=method, query=query or {}, form=form or {},
                         raw=json.dumps({'license_key': KEY}) if raw is None else raw,
                         content_type=content_type, content_length=content_length, query_string=query_string)

    def filter(self, data, request_key=''):
        return self.call('filter', raw=json.dumps(data) if not isinstance(data, str) else data, request_key=request_key)

    def test_laravel_json_and_context_use_only_license_key(self):
        result = self.normalize(raw=json.dumps({'license_key': KEY.lower(), 'admin_email': 'synthetic@example.invalid',
                                               'tailscale_hostname': 'synthetic', 'company_name': 'Synthetic company'}))
        self.assertEqual(result, {'ok': True, 'key': KEY, 'product': 'creditsoft'})

    def test_form_post_and_get_legacy_alias(self):
        result = self.normalize(form={'license_key': KEY}, raw='license_key=' + KEY,
                                content_type='application/x-www-form-urlencoded; charset=UTF-8')
        self.assertTrue(result['ok'])
        for field in ('license_key', 'key'):
            with self.subTest(field=field):
                result = self.normalize(method='GET', query={field: KEY}, raw='')
                self.assertEqual(result['key'], KEY)

    def test_dash_case_normalization_matches_legacy_lookup(self):
        dashed = KEY[:8] + '-' + KEY[8:16] + '-' + KEY[16:]
        result = self.normalize(query={'key': dashed.lower()}, raw=json.dumps({'license_key': ' ' + KEY + ' '}))
        self.assertTrue(result['ok'])
        self.assertEqual(result['key'], KEY)

    def test_missing_key_and_invalid_types_never_report_valid(self):
        for value in (None, False, 42, [], {}, '', 'tiny', 'x' * 257, 'SYNTHETIC_INVALID_KEY!'):
            with self.subTest(value=value):
                result = self.normalize(raw=json.dumps({'license_key': value}))
                self.assertFalse(result['ok'])
                self.assertFalse(result['payload']['valid'])
                self.assertEqual(result['http_status'], 400)
        result = self.normalize(method='GET', query={}, raw='')
        self.assertFalse(result['payload']['valid'])

    def test_conflicting_aliases_body_and_query_are_rejected(self):
        for query, body in [({'key': OTHER}, {'license_key': KEY}),
                            ({'key': KEY, 'license_key': OTHER}, {'license_key': KEY}),
                            ({}, {'key': OTHER, 'license_key': KEY})]:
            with self.subTest(query=query, body=body):
                self.assertFalse(self.normalize(query=query, raw=json.dumps(body))['ok'])

    def test_duplicate_json_keys_including_escaped_names_are_rejected(self):
        for raw in ['{"license_key":"' + KEY + '","license_key":"' + OTHER + '"}',
                    '{"license_key":"' + KEY + '","license\\u005fkey":"' + OTHER + '"}',
                    '{"license_key":"' + KEY + '","action":"validate","action":"create"}']:
            with self.subTest(raw=raw):
                self.assertFalse(self.normalize(raw=raw)['ok'])

    def test_duplicate_form_and_query_fields_are_rejected(self):
        result = self.normalize(form={'license_key': KEY}, raw='license_key=' + KEY + '&license_key=' + OTHER,
                                content_type='application/x-www-form-urlencoded')
        self.assertFalse(result['ok'])
        result = self.normalize(method='GET', query={'license_key': KEY}, raw='',
                                query_string='license_key=' + KEY + '&license%5fkey=' + OTHER)
        self.assertFalse(result['ok'])

    def test_state_changing_actions_and_unknown_products_cannot_reach_backend(self):
        for field, value in [('action', 'create'), ('action', 'renew'), ('action', ['validate']),
                             ('product', 'cph'), ('product', 'creator-publishing-hub'), ('product', 'CreditSoft'), ('product', [])]:
            with self.subTest(field=field, value=value):
                result = self.normalize(query={field: value})
                self.assertFalse(result['ok'])
                self.assertFalse(result['payload']['valid'])
                self.assertEqual(result['payload']['features'], [])

    def test_bounded_media_method_and_body_validation(self):
        cases = [dict(method='DELETE'), dict(method='PUT'), dict(raw='['), dict(raw='[]'),
                 dict(raw='true'), dict(raw='x' * 16385), dict(content_length='16385'),
                 dict(content_length='-1'), dict(content_length=[]), dict(content_type='text/plain'),
                 dict(method='GET', raw=json.dumps({'license_key': KEY}))]
        for change in cases:
            with self.subTest(change=change):
                self.assertFalse(self.normalize(**change)['ok'])
        result = self.normalize(method='OPTIONS', raw='')
        self.assertEqual(result['http_status'], 204)
        self.assertIsNone(result['payload'])

    def test_positive_legacy_payload_preserves_creditsoft_features_and_nullable_expiry(self):
        data = {'valid': True, 'status': 'active', 'message': 'License valid', 'plan': 'enterprise',
                'plan_key': 'enterprise', 'features': {'metro2': True, 'max_clients': 100, 'integrations': ['creditsoft']},
                'expires_at': None, 'grace_days': 7, 'can_access_workspace': True,
                'license_key': KEY, 'customer_email': 'synthetic@example.invalid'}
        result = self.filter(data)
        self.assertEqual(result['http_status'], 200)
        self.assertEqual(result['payload']['features'], data['features'])
        self.assertIsNone(result['payload']['expires_at'])
        self.assertEqual(result['payload']['product'], 'creditsoft')
        self.assertNotIn('license_key', result['payload'])
        self.assertNotIn('customer_email', result['payload'])

    def test_old_features_missing_means_no_invented_entitlements(self):
        result = self.filter({'valid': True, 'status': 'active', 'message': 'License validated', 'plan': 'basic'})
        self.assertTrue(result['payload']['valid'])
        self.assertEqual(result['payload']['features'], [])
        self.assertEqual(result['payload']['product'], 'creditsoft')

    def test_explicit_backend_product_mismatch_never_grants_other_product(self):
        for product in ('cph-umbrella', 'creator-publishing-hub', '', None, []):
            with self.subTest(product=product):
                result = self.filter({'valid': True, 'status': 'active', 'product': product,
                                      'features': {'fleet.dashboard': True}, 'can_access_workspace': True})
                self.assertEqual(result['http_status'], 503)
                self.assertFalse(result['payload']['valid'])
                self.assertFalse(result['payload']['can_access_workspace'])
                self.assertEqual(result['payload']['features'], [])
        result = self.filter({'valid': True, 'status': 'active', 'product': 'creditsoft',
                              'features': {'metro2': True}})
        self.assertTrue(result['payload']['valid'])

    def test_negative_grants_are_cleared_except_verified_legacy_grace_shape(self):
        grants = {'features': {'read': True, 'write': True}, 'can_access_workspace': True,
                  'in_grace_period': True, 'grace_expired': False, 'access_state': 'active'}
        for status in ('invalid', 'expired', 'locked', 'suspended', 'revoked', 'grace'):
            with self.subTest(status=status):
                result = self.filter({'valid': False, 'status': status, **grants})
                self.assertEqual(result['http_status'], 200)
                self.assertFalse(result['payload']['can_access_workspace'])
                self.assertFalse(result['payload']['in_grace_period'])
                self.assertEqual(result['payload']['features'], [])
                self.assertEqual(result['payload']['access_state'], 'locked')
        # This is the existing handler's validationPayload(false, 'grace') contract.
        grace = {'valid': False, 'status': 'grace', 'product': 'creditsoft',
                 'expires_at': '2026-10-01 00:00:00', 'grace_ends_at': '2026-10-08T00:00:00+00:00',
                 'grace_days': 7, 'grace_days_remaining': 4, 'grace_expired': False,
                 'in_grace_period': True, 'access_state': 'grace', 'can_access_workspace': True,
                 'features': {'metro2': True}}
        result = self.filter(grace)
        self.assertEqual(result['http_status'], 200)
        self.assertFalse(result['payload']['valid'])
        self.assertTrue(result['payload']['can_access_workspace'])
        self.assertEqual(result['payload']['features'], {'metro2': True})
        for change in ({'in_grace_period': False}, {'grace_expired': True}, {'access_state': 'active'},
                       {'grace_ends_at': 'invalid'}, {'grace_days_remaining': '4'}):
            with self.subTest(change=change):
                result = self.filter({**grace, **change})
                self.assertFalse(result['payload']['can_access_workspace'])
                self.assertEqual(result['payload']['features'], [])

    def test_fake_key_grace_and_expired_remain_negative_with_real_backend_shape(self):
        for state in ('invalid', 'grace', 'expired', 'suspended', 'locked'):
            with self.subTest(state=state):
                result = self.filter({'valid': False, 'status': state, 'message': 'Synthetic outcome', 'features': []})
                self.assertEqual(result['http_status'], 200)
                self.assertFalse(result['payload']['valid'])
                self.assertEqual(result['payload']['status'], state)

    def test_legacy_demo_and_no_db_fallback_are_always_unavailable(self):
        for change in ({'message': 'License valid (no DB)'}, {'message': 'Demo mode'}, {'demo': True},
                       {'demo_mode': True}, {'mode': 'soft'}, {'format_only': True}):
            with self.subTest(change=change):
                result = self.filter({'valid': True, 'status': 'active', 'message': 'License valid', **change})
                self.assertEqual(result['http_status'], 503)
                self.assertFalse(result['payload']['valid'])

    def test_malformed_backend_and_errors_cannot_grant_license_or_leak_details(self):
        for data in ('not JSON', '{}', 'true', {'valid': 'true', 'status': 'active'},
                     {'valid': True, 'status': 'expired'}, {'error': 'SYNTHETIC-DB-PASSWORD'},
                     {'valid': False, 'status': 'error', 'message': 'SYNTHETIC-DB-PASSWORD'}, 'x' * 65537):
            with self.subTest(data=str(data)[:100]):
                result = self.filter(data)
                self.assertEqual(result['http_status'], 503)
                self.assertFalse(result['payload']['valid'])
                self.assertNotIn('SYNTHETIC-DB-PASSWORD', json.dumps(result))

    def test_features_are_bounded_typed_and_omit_customer_credential_payloads(self):
        nested = {'allowed': True}
        for _ in range(6):
            nested = {'nested': nested}
        for features in ({'customer_email': 'synthetic@example.invalid'}, {'password': 'SYNTHETIC-PRIVATE'},
                         {'key': 'x' * 161}, nested, {str(i): True for i in range(101)}):
            with self.subTest(features=str(features)[:100]):
                result = self.filter({'valid': True, 'status': 'active', 'features': features})
                self.assertEqual(result['http_status'], 503)
                self.assertEqual(result['payload']['features'], [])

    def test_exact_request_key_is_redacted_from_accidental_backend_message(self):
        result = self.filter({'valid': True, 'status': 'active', 'message': 'License ' + KEY}, KEY)
        self.assertNotIn(KEY, json.dumps(result))


@unittest.skipUnless(PHP, 'PHP CLI is required')
class LegacyExitTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.marker = self.root / 'backend-called'
        self.config = self.root / 'credit_config.php'
        self.config.write_text("<?php define('DB_HOST','localhost'); define('DB_NAME','synthetic_db'); define('DB_USER','synthetic_user'); define('DB_PASS','SYNTHETIC-CONFIG-PASSWORD'); echo 'SYNTHETIC-CONFIG-OUTPUT';")
        self.legacy = self.root / 'license.php'
        self.runner = self.root / 'runner.php'
        self.runner.write_text('''<?php
error_reporting(E_ALL);
set_error_handler(function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$in=json_decode(stream_get_contents(STDIN),true);
require $in['helper'];
ob_start(function($out){return json_encode(['http_status'=>http_response_code(),'payload'=>json_decode($out,true)]);});
$factory=function($dsn,$user,$password)use($in){
 if($in['db']==='throw'){throw new RuntimeException('SYNTHETIC-DB-PASSWORD');}
 if($in['db']==='none'){return null;}
 return new class{public function query($sql){if($sql!=='SELECT 1'){throw new RuntimeException('Unexpected SQL');}return new class{public function fetchColumn(){return 1;}};}};
};
$request=CreditSoftLicenseValidation::prepare($in['legacy'],$in['config'],$factory,$in['request']);
if($request===null){exit;}
$level=CreditSoftLicenseValidation::startLegacyResponseFilter($request['key']);
try{require $in['legacy'];}catch(Throwable $error){CreditSoftLicenseValidation::legacyException($level);exit;}
ob_end_flush();
''')

    def legacy_code(self, reply):
        marker = json.dumps(str(self.marker))
        # Runtime-only synthetic legacy script, including its real exit behavior.
        self.legacy.write_text("<?php if($_SERVER['REQUEST_METHOD']!=='GET'||$_GET['action']!=='validate'){throw new RuntimeException('Wrong action');} file_put_contents(" + marker + ",json_encode($_GET)); echo " + json.dumps(json.dumps(reply)) + "; exit;")

    def call(self, db='ready', query=None, raw=None, config=None):
        values = {'helper': str(ROOT / 'web/api/license-validate-request.php'), 'legacy': str(self.legacy),
                  'config': str(config or self.config), 'db': db,
                  'request': {'method': 'POST', 'query': query or {}, 'form': {},
                              'raw': json.dumps({'license_key': KEY}) if raw is None else raw,
                              'content_type': 'application/json', 'content_length': None, 'query_string': ''}}
        process = subprocess.run([PHP, str(self.runner)], input=json.dumps(values), text=True, capture_output=True, check=True)
        self.assertEqual(process.stderr, '')
        self.assertNotIn('SYNTHETIC-CONFIG-', process.stdout)
        self.assertNotIn('SYNTHETIC-DB-PASSWORD', process.stdout)
        self.assertNotIn(KEY, process.stdout)
        return json.loads(process.stdout)

    def test_valid_legacy_exit_preserves_positive_and_forces_validation_only(self):
        self.legacy_code({'valid': True, 'status': 'active', 'message': 'License validated', 'plan': 'enterprise',
                          'features': {'metro2': True}, 'expires_at': None})
        result = self.call()
        self.assertEqual(result['http_status'], 200)
        self.assertTrue(result['payload']['valid'])
        self.assertEqual(result['payload']['features'], {'metro2': True})
        self.assertEqual(json.loads(self.marker.read_text()), {'action': 'validate', 'key': KEY})

    def test_fake_key_exit_stays_invalid(self):
        self.legacy_code({'valid': False, 'status': 'invalid', 'message': 'License not found or inactive'})
        result = self.call()
        self.assertEqual(result['http_status'], 200)
        self.assertFalse(result['payload']['valid'])

    def test_legacy_exit_demo_never_bypasses_output_filter(self):
        self.legacy_code({'valid': True, 'status': 'active', 'message': 'License valid (no DB)'})
        result = self.call()
        self.assertEqual(result['http_status'], 503)
        self.assertFalse(result['payload']['valid'])

    def test_no_pdo_and_connection_exception_fail_before_legacy(self):
        self.legacy_code({'valid': True, 'status': 'active'})
        for db in ('none', 'throw'):
            with self.subTest(db=db):
                result = self.call(db=db)
                self.assertEqual(result['http_status'], 503)
                self.assertFalse(result['payload']['valid'])
                self.assertFalse(self.marker.exists())

    def test_missing_or_incomplete_private_config_fail_before_legacy(self):
        self.legacy_code({'valid': True, 'status': 'active'})
        result = self.call(config=self.root / 'missing.php')
        self.assertEqual(result['http_status'], 503)
        self.config.write_text("<?php define('DB_HOST','localhost');")
        result = self.call()
        self.assertEqual(result['http_status'], 503)
        self.assertFalse(self.marker.exists())

    def test_state_changing_or_other_product_requests_never_reach_legacy(self):
        self.legacy_code({'valid': True, 'status': 'active'})
        for query in ({'action': 'create'}, {'product': 'cph'}, {'license_key': OTHER}):
            with self.subTest(query=query):
                result = self.call(query=query)
                self.assertFalse(result['payload']['valid'])
                self.assertFalse(self.marker.exists())

    def test_legacy_thrown_failure_emits_static_unavailable_without_details(self):
        self.legacy.write_text("<?php echo 'SYNTHETIC-PRIVATE-BACKEND-OUTPUT'; throw new RuntimeException('SYNTHETIC-PRIVATE-BACKEND-ERROR');")
        result = self.call()
        self.assertEqual(result['http_status'], 503)
        self.assertNotIn('SYNTHETIC-PRIVATE-BACKEND', json.dumps(result))


if __name__ == '__main__':
    unittest.main()
