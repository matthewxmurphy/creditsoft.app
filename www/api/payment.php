<?php
// Load config from outside web root
$config_path = dirname(__DIR__, 2) . '/credit_config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

// Authorize.net Payment API

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return plan info
    $plans = [
        [
            'id' => 'starter',
            'name' => 'Starter',
            'price' => 49,
            'interval' => 'monthly',
            'features' => ['Up to 25 clients', 'Metro2 error detection', 'Basic dispute templates', 'Email support']
        ],
        [
            'id' => 'professional',
            'name' => 'Professional',
            'price' => 99,
            'interval' => 'monthly',
            'features' => ['Unlimited clients', '50-state CRO rules', 'AI dispute variations', 'Client portal', 'Priority support']
        ],
        [
            'id' => 'enterprise',
            'name' => 'Enterprise',
            'price' => 199,
            'interval' => 'monthly',
            'features' => ['Everything in Professional', 'Multi-user access', 'White-label', 'API access', 'Dedicated support']
        ]
    ];
    echo json_encode(['plans' => $plans]);
    exit;
}

if ($method !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if (!defined('AUTHNET_LOGIN') || !AUTHNET_LOGIN) {
    echo json_encode(['error' => 'Payment processor not configured']);
    exit;
}

switch ($action) {
    case 'create_subscription':
        createSubscription($input);
        break;
    case 'charge':
        chargeCard($input);
        break;
    case 'validate':
        validateCard($input);
        break;
    default:
        echo json_encode(['error' => 'Unknown action']);
}

function createSubscription($data) {
    $planId = $data['plan_id'] ?? '';
    $email = $data['email'] ?? '';
    $cardNumber = $data['card_number'] ?? '';
    $expDate = $data['exp_date'] ?? ''; // MM/YY
    $cvv = $data['cvv'] ?? '';
    
    $plans = [
        'starter' => ['amount' => 49, 'name' => 'CreditSoft Starter'],
        'professional' => ['amount' => 99, 'name' => 'CreditSoft Professional'],
        'enterprise' => ['amount' => 199, 'name' => 'CreditSoft Enterprise']
    ];
    
    if (!isset($plans[$planId])) {
        echo json_encode(['error' => 'Invalid plan']);
        return;
    }
    
    $plan = $plans[$planId];
    
    // Authorize.net ARB (Automated Recurring Billing)
    $xml = '<?xml version="1.0" encoding="utf-8"?>
    <ARBCreateSubscriptionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
        <merchantAuthentication>
            <name>' . AUTHNET_LOGIN . '</name>
            <transactionKey>' . AUTHNET_TRANSACTION_KEY . '</transactionKey>
        </merchantAuthentication>
        <subscription>
            <name>' . $plan['name'] . '</name>
            <paymentSchedule>
                <interval>
                    <length>1</length>
                    <unit>months</unit>
                </interval>
                <startDate>' . date('Y-m-d') . '</startDate>
                <totalOccurrences>9999</totalOccurrences>
            </paymentSchedule>
            <amount>' . $plan['amount'] . '</amount>
            <payment>
                <creditCard>
                    <cardNumber>' . $cardNumber . '</cardNumber>
                    <expirationDate>' . $expDate . '</expirationDate>
                    <cardCode>' . $cvv . '</cardCode>
                </creditCard>
            </payment>
            <customer>
                <email>' . $email . '</email>
            </customer>
        </subscription>
    </ARBCreateSubscriptionRequest>';
    
    $response = sendToAuthNet($xml);
    
    if (isset($response->subscriptionId)) {
        echo json_encode([
            'success' => true,
            'subscription_id' => (string)$response->subscriptionId,
            'plan' => $planId,
            'amount' => $plan['amount']
        ]);
    } else {
        $errorMsg = isset($response->messages->message->text) 
            ? (string)$response->messages->message->text 
            : 'Payment failed';
        echo json_encode(['error' => $errorMsg]);
    }
}

function chargeCard($data) {
    $amount = floatval($data['amount'] ?? 0);
    $cardNumber = $data['card_number'] ?? '';
    $expDate = $data['exp_date'] ?? '';
    $cvv = $data['cvv'] ?? '';
    $email = $data['email'] ?? '';
    
    if ($amount <= 0) {
        echo json_encode(['error' => 'Invalid amount']);
        return;
    }
    
    $xml = '<?xml version="1.0" encoding="utf-8"?>
    <createTransactionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
        <merchantAuthentication>
            <name>' . AUTHNET_LOGIN . '</name>
            <transactionKey>' . AUTHNET_TRANSACTION_KEY . '</transactionKey>
        </merchantAuthentication>
        <transactionRequest>
            <transactionType>authCaptureTransaction</transactionType>
            <amount>' . $amount . '</amount>
            <payment>
                <creditCard>
                    <cardNumber>' . $cardNumber . '</cardNumber>
                    <expirationDate>' . $expDate . '</expirationDate>
                    <cardCode>' . $cvv . '</cardCode>
                </creditCard>
            </payment>
            <customer>
                <email>' . $email . '</email>
            </customer>
        </transactionRequest>
    </createTransactionRequest>';
    
    $response = sendToAuthNet($xml);
    
    if (isset($response->transactionResponse) && $response->transactionResponse->responseCode == 1) {
        echo json_encode([
            'success' => true,
            'transaction_id' => (string)$response->transactionResponse->transId,
            'amount' => $amount
        ]);
    } else {
        $errorMsg = isset($response->transactionResponse->errors->error->errorText)
            ? (string)$response->transactionResponse->errors->error->errorText
            : 'Payment failed';
        echo json_encode(['error' => $errorMsg]);
    }
}

function validateCard($data) {
    $cardNumber = $data['card_number'] ?? '';
    $expDate = $data['exp_date'] ?? '';
    $cvv = $data['cvv'] ?? '';
    $amount = 1.00;
    
    $xml = '<?xml version="1.0" encoding="utf-8"?>
    <createTransactionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
        <merchantAuthentication>
            <name>' . AUTHNET_LOGIN . '</name>
            <transactionKey>' . AUTHNET_TRANSACTION_KEY . '</transactionKey>
        </merchantAuthentication>
        <transactionRequest>
            <transactionType>authOnlyTransaction</transactionType>
            <amount>' . $amount . '</amount>
            <payment>
                <creditCard>
                    <cardNumber>' . $cardNumber . '</cardNumber>
                    <expirationDate>' . $expDate . '</expirationDate>
                    <cardCode>' . $cvv . '</cardCode>
                </creditCard>
            </payment>
        </transactionRequest>
    </createTransactionRequest>';
    
    $response = sendToAuthNet($xml);
    
    if (isset($response->transactionResponse) && in_array($response->transactionResponse->responseCode, [1, 4])) {
        // Void the auth transaction
        $transId = $response->transactionResponse->transId;
        voidTransaction($transId);
        
        echo json_encode(['valid' => true]);
    } else {
        echo json_encode(['valid' => false, 'error' => 'Card validation failed']);
    }
}

function voidTransaction($transId) {
    $xml = '<?xml version="1.0" encoding="utf-8"?>
    <createTransactionRequest xmlns="AnetApi/xml/v1/schema/AnetApiSchema.xsd">
        <merchantAuthentication>
            <name>' . AUTHNET_LOGIN . '</name>
            <transactionKey>' . AUTHNET_TRANSACTION_KEY . '</transactionKey>
        </merchantAuthentication>
        <transactionRequest>
            <transactionType>voidTransaction</transactionType>
            <refTransId>' . $transId . '</refTransId>
        </transactionRequest>
    </createTransactionRequest>';
    
    sendToAuthNet($xml);
}

function sendToAuthNet($xml) {
    $url = AUTHNET_TEST_MODE 
        ? 'https://apitest.authorize.net/xml/v1/request.api'
        : 'https://api.authorize.net/xml/v1/request.api';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/xml']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return simplexml_load_string($response);
}
