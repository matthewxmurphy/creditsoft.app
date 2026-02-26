<?php
// Load config from outside web root
$config_path = dirname(__DIR__, 2) . '/credit_config.php';
if (file_exists($config_path)) {
    require_once $config_path;
}

// Chatbot API endpoint - processes messages with RAG using YAML knowledge base

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo json_encode(['error' => 'No message provided']);
    exit;
}

// Load YAML knowledge base files
$knowledgeFiles = [
    __DIR__ . '/../soul.yaml',
    __DIR__ . '/../config.yaml',
    __DIR__ . '/../tools.yaml',
    __DIR__ . '/../persona.yaml',
    __DIR__ . '/../crew.yaml',
    __DIR__ . '/../tasks.yaml',
    __DIR__ . '/../../AGENTS.yaml',
];

$context = "CREDITSOFT KNOWLEDGE BASE:\n\n";

foreach ($knowledgeFiles as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $context .= "---\n" . $content . "\n\n";
    }
}

// Add sales/landing page context
$context .= "
ABOUT CREDITSOFT:
- Metro2-first credit repair software
- Pricing: Starter $49/mo, Professional $99/mo, Enterprise $199/mo
- 30+ Metro2 error codes built-in
- 50-state CRO rules
- FCRA/FDCPA compliant
- Clients hosted on YOUR server (not ours)
- Competitors CDM and CRC host client PII on their servers
";

$openaiApiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';

if (empty($openaiApiKey) || strlen($openaiApiKey) < 20) {
    echo json_encode(['reply' => 'Chatbot is configuring. Email hello@creditsoft.app for questions!']);
    exit;
}

$systemPrompt = "You are Ashley, a friendly and helpful Sales Assistant for CreditSoft, a credit repair software company. You're knowledgeable about credit repair, Metro2 compliance, and FCRA/FDCPA laws. 

CREDITSOFT PRODUCT INFO:
- Metro2-first credit repair software (detects 30+ error codes)
- Pricing: Starter $49/mo ($399/yr, $999 lifetime), Professional $99/mo ($799/yr, $1999 lifetime), Enterprise $199/mo
- 50-state CRO rules built-in
- FCRA/FDCPA compliant workflows
- Self-hosted: client data stays on YOUR server (not ours)
- Competitors like CDM and CRC host client PII on THEIR servers - CreditSoft does NOT
- AI-powered dispute letter generation
- Client portal (white-labeled)
- Monthly credit report comparisons

RULES:
- Be friendly and conversational - you're Ashley!
- Always introduce yourself as Ashley when you start a conversation
- Focus on converting to paid customers
- Highlight the 'your server, your data' advantage
- Mention Metro2 compliance as key differentiator
- If you don't know something, say so honestly
- Keep responses under 3 sentences unless detailed info is needed
- Always end with a soft call-to-action (join waitlist, ask more questions, etc)";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => 'gpt-3.5-turbo',
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $context . "\n\nUser Question: " . $message]
    ],
    'temperature' => 0.7,
    'max_tokens' => 300
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $openaiApiKey
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $response) {
    $result = json_decode($response, true);
    $reply = $result['choices'][0]['message']['content'] ?? 'Sorry, I had trouble processing your request. Please try again.';
} else {
    $reply = "Thanks for your interest in CreditSoft! For specific questions, please email hello@creditsoft.app. Would you like to join our waitlist for early access?";
}

echo json_encode([
    'reply' => $reply,
    'timestamp' => date('c')
]);
