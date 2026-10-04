<?php
declare(strict_types=1);
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require dirname(__DIR__, 2) . '/web/api/license-validate-request.php';
$input = json_decode(stream_get_contents(STDIN), true);
if ($input['operation'] === 'normalize') {
    echo json_encode(CreditSoftLicenseValidation::normalize(
        $input['method'], $input['query'], $input['form'], $input['raw'],
        $input['content_type'], $input['content_length'], $input['query_string']
    ));
} elseif ($input['operation'] === 'filter') {
    echo json_encode(CreditSoftLicenseValidation::filterResponse($input['raw'], isset($input['request_key']) ? $input['request_key'] : ''));
} else {
    throw new RuntimeException('Unknown test operation');
}
