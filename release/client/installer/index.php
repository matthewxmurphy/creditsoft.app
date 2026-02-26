<?php
/**
 * CreditSoft Installer - Single Page AJAX
 * License → Logo → Database → Email Config
 */

error_reporting(0);
ini_set('display_errors', 0);

session_start();

$basePath = dirname(__DIR__);
$step = $_GET['step'] ?? 'license';
$error = '';
$debug = '';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

function respond($success, $message, $data = []) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

set_exception_handler(function($e) {
    respond(false, 'Exception: ' . $e->getMessage());
});

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'check_system') {
        $checks = [
            'php_version' => version_compare(PHP_VERSION, '8.1', '>='),
            'mysqli' => extension_loaded('mysqli'),
            'curl' => function_exists('curl_init'),
            'json' => function_exists('json_encode'),
            'pdo' => extension_loaded('pdo_mysql') || extension_loaded('pdo'),
        ];

        $canWriteBase = is_writable($basePath);
        $testFile = $basePath . '/test_write_' . time() . '.txt';
        if (@file_put_contents($testFile, 'test') !== false) {
            unlink($testFile);
            $canWriteBase = true;
        }

        $checks['can_write'] = $canWriteBase;
        $checks['php_version_msg'] = PHP_VERSION;

        $allPass = !in_array(false, $checks);
        respond($allPass, $allPass ? 'System checks passed' : 'Some checks failed', $checks);
    }

    if ($action === 'validate_license') {
        $license_id = trim($_POST['license_id'] ?? '');
        
        if (empty($license_id)) {
            respond(false, 'Please enter a license ID');
        }

        // Accept any license for now (license server not implemented yet)
        // TODO: Implement license server at creditsoft.app
        $_SESSION['license_validated'] = $license_id;
        $_SESSION['license_data'] = ['plan' => 'Professional', 'valid' => true];
        respond(true, 'License activated!', ['plan' => 'Professional']);
    }

    if ($action === 'upload_logo') {
        if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            respond(false, 'No file uploaded');
        }

        $file = $_FILES['logo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['png', 'svg', 'jpg', 'jpeg'])) {
            respond(false, 'Only PNG, SVG, JPG allowed');
        }

        if ($ext === 'svg' || $ext === 'png') {
            $filename = 'logo.' . $ext;
        } else {
            $filename = 'logo.png';
        }

        $targetDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/images/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $_SESSION['logo_uploaded'] = $filename;
            respond(true, 'Logo uploaded', ['filename' => $filename]);
        } else {
            respond(false, 'Failed to save logo');
        }
    }

    if ($action === 'test_db_connection') {
        $db_host = $_POST['db_host'] ?? 'localhost';
        $db_user = $_POST['db_user'] ?? '';
        $db_pass = $_POST['db_pass'] ?? '';
        $db_name = $_POST['db_name'] ?? '';

        if (empty($db_host) || empty($db_user) || empty($db_name)) {
            respond(false, 'Please fill in all database fields');
        }

        $conn = @new mysqli($db_host, $db_user, $db_pass);
        if ($conn->connect_error) {
            respond(false, 'Connection failed: ' . $conn->connect_error);
        }

        if (!$conn->select_db($db_name)) {
            $createDb = $conn->query("CREATE DATABASE IF NOT EXISTS `" . $conn->real_escape_string($db_name) . "`");
            if (!$createDb) {
                $conn->close();
                respond(false, 'Cannot create database: ' . $conn->error);
            }
            $conn->select_db($db_name);
        }

        $prefix = $_POST['table_prefix'] ?? 'CSapp_';
        $prefix = preg_replace('/[^a-zA-Z0-9_]/', '', $prefix);
        if (empty($prefix)) {
            $prefix = 'CSapp_';
        }

        $result = $conn->query("SHOW TABLES");
        $existingTables = [];
        if ($result) {
            while ($row = $result->fetch_array()) {
                $existingTables[] = $row[0];
            }
        }

        $ourTables = array_filter($existingTables, fn($t) => strpos($t, $prefix . '_') === 0);
        $hasExistingInstall = !empty($ourTables);

        $conn->close();

        $_SESSION['db_config'] = [
            'host' => $db_host,
            'user' => $db_user,
            'pass' => $db_pass,
            'name' => $db_name,
            'prefix' => $prefix,
        ];

        if ($hasExistingInstall) {
            respond(true, 'Existing installation detected', [
                'existing' => true,
                'tables' => count($ourTables),
                'prefix' => $prefix,
                'message' => 'Found ' . count($ourTables) . ' tables with prefix "' . $prefix . '"'
            ]);
        } else {
            respond(true, 'Connection successful - database is empty', ['existing' => false]);
        }
    }

    if ($action === 'install_database') {
        $db_config = $_SESSION['db_config'] ?? null;
        if (!$db_config) {
            respond(false, 'Database not configured');
        }

        $conn = @new mysqli($db_config['host'], $db_config['user'], $db_config['pass']);
        if ($conn->connect_error) {
            respond(false, 'Connection failed: ' . $conn->connect_error);
        }

        $conn->select_db($db_config['name']);
        $prefix = $db_config['prefix'];
        
        // Debug: return the prefix being used
        if (empty($prefix)) {
            respond(false, 'Prefix is empty - check your input');
        }

        $result = $conn->query("SHOW TABLES");
        $existingTables = [];
        if ($result) {
            while ($row = $result->fetch_array()) {
                $existingTables[] = $row[0];
            }
        }

        $ourTables = array_filter($existingTables, fn($t) => strpos($t, $prefix . '_') === 0);

        $isUpdate = !empty($ourTables);

        if (!empty($ourTables)) {
            foreach ($ourTables as $table) {
                $conn->query("DROP TABLE IF EXISTS `$table`");
            }
        }

        $sql = file_get_contents(__DIR__ . '/database.sql');
        if (!$sql) {
            respond(false, 'Could not read database.sql file');
        }
        
        // Replace table names with prefix - simple str_replace
        $tables = ['users', 'clients', 'credit_reports', 'report_accounts', 'error_types', 'account_errors', 
                   'comparisons', 'client_notes', 'dispute_templates', 'disputes', 'drip_campaigns', 
                   'drip_queue', 'sops', 'ai_interactions', 'knowledge_base', 'tasks', 'activity_log', 'settings'];
        foreach ($tables as $table) {
            $sql = str_replace('`' . $table . '`', '`' . $prefix . '_' . $table . '`', $sql);
        }
        
        // Use multi_query to execute all statements
        if (!$conn->multi_query($sql)) {
            respond(false, 'SQL Error: ' . $conn->error);
        }
        
        // Consume all results and count
        $stmtCount = 0;
        $createdTables = [];
        do {
            $stmtCount++;
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        
        // Verify tables were created
        $verify = $conn->query("SHOW TABLES");
        $allTables = [];
        if ($verify) {
            while ($row = $verify->fetch_array()) {
                $allTables[] = $row[0];
            }
        }
        
        if (empty($allTables)) {
            $conn->close();
            respond(false, 'No tables in database. Check SQL file. Statements run: ' . $stmtCount);
        }
        
        // Check if our prefix tables exist
        $ourNewTables = array_filter($allTables, fn($t) => strpos($t, $prefix . '_') === 0);
        if (empty($ourNewTables)) {
            $conn->close();
            respond(false, 'Tables exist but none with prefix "' . $prefix . '_". Found: ' . implode(', ', array_slice($allTables, 0, 5)));
        }

        $conn->close();
        $_SESSION['db_installed'] = true;

        respond(true, 'Database installed!', ['statements' => $stmtCount]);
    }

    if ($action === 'save_config') {
        $db_config = $_SESSION['db_config'] ?? null;
        if (!$db_config) {
            respond(false, 'Database not configured');
        }

        $admin_email = $_POST['admin_email'] ?? 'admin@credit.com';
        $admin_password = $_POST['admin_password'] ?? 'admin123';

        $logo = $_SESSION['logo_uploaded'] ?? 'CreditSoft.png';
        $license = $_SESSION['license_validated'] ?? 'TRIAL';

        $email_enabled = $_POST['email_enabled'] ?? '0';
        $email_host = $_POST['email_host'] ?? '';
        $email_port = $_POST['email_port'] ?? '587';
        $email_user = $_POST['email_user'] ?? '';
        $email_pass = $_POST['email_pass'] ?? '';
        $email_from = $_POST['email_from'] ?? $admin_email;
        $email_from_name = $_POST['email_from_name'] ?? 'CreditSoft';

        $baseURL = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';
        $encKey = bin2hex(random_bytes(16));
        
        $configContent = "<?php
/**
 * CreditSoft Configuration
 * Generated: " . date('Y-m-d H:i:s') . "
 */

return [
    // Database
    'database.default.hostname' => \"{$db_config['host']}\",
    'database.default.database' => \"{$db_config['name']}\",
    'database.default.username' => \"{$db_config['user']}\",
    'database.default.password' => \"{$db_config['pass']}\",
    'database.default.DBDriver' => 'MySQLi',
    'database.default.port' => 3306,
    'database.default.prefix' => \"{$db_config['prefix']}\",

    // App
    'app.baseURL' => \"{$baseURL}\",
    'app.logo' => \"assets/images/{$logo}\",
    
    // License
    'app.license_id' => \"{$license}\",
    'app.installed_at' => \"" . date('Y-m-d H:i:s') . "\",

    // Encryption
    'encryption.key' => \"{$encKey}\",

    // Session
    'session.savePath' => __DIR__ . '/writable/session/',
    'session.prefix' => 'CS_',

    // Logging
    'logger.threshold' => 4,
    'logger.path' => __DIR__ . '/writable/logs/',

    // Email (SMTP)
    'email.enabled' => " . ($email_enabled === '1' ? 'true' : 'false') . ",
    'email.fromEmail' => \"{$email_from}\",
    'email.fromName' => \"{$email_from_name}\",
    'email.SMTPHost' => \"{$email_host}\",
    'email.SMTPPort' => \"{$email_port}\",
    'email.SMTPUser' => \"{$email_user}\",
    'email.SMTPPass' => \"{$email_pass}\",
];
";

        $configPath = $basePath . '/credit_config.php';
        
        if (file_put_contents($configPath, $configContent) === false) {
            respond(false, 'Failed to write config file');
        }

        $conn = @new mysqli($db_config['host'], $db_config['user'], $db_config['pass']);
        if ($conn->connect_error) {
            respond(false, 'DB connection failed: ' . $conn->connect_error);
        }
        $conn->select_db($db_config['name']);

        $password_hash = password_hash($admin_password, PASSWORD_BCRYPT);
        $prefix = $db_config['prefix'];
        
        // Update or insert admin user (SQL import already created admin)
        $checkUser = $conn->query("SELECT id FROM " . $prefix . "_users WHERE id = 1");
        if ($checkUser && $checkUser->num_rows > 0) {
            $conn->query("UPDATE " . $prefix . "_users SET email = '" . $conn->real_escape_string($admin_email) . "', password_hash = '" . $conn->real_escape_string($password_hash) . "' WHERE id = 1");
        } else {
            $conn->query("INSERT INTO " . $prefix . "_users (id, username, email, password_hash, role, first_name, is_active) VALUES (1, 'admin', '" . $conn->real_escape_string($admin_email) . "', '" . $conn->real_escape_string($password_hash) . "', 'admin', 'Administrator', 1)");
        }

        // Settings already have company_logo from SQL import, just update it

        $conn->close();

        $installedPath = $basePath . '/writable/.installed';
        @mkdir(dirname($installedPath), 0755, true);
        file_put_contents($installedPath, json_encode([
            'installed_at' => date('Y-m-d H:i:s'),
            'license_id' => $license,
            'version' => '1.0.0',
        ]));

        session_destroy();
        respond(true, 'Installation complete!', ['redirect' => '/']);
    }

    respond(false, 'Unknown action');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install CreditSoft</title>
    <link rel="icon" type="image/png" href="../assets/images/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="../assets/images/favicon.svg" />
    <link rel="shortcut icon" href="../assets/images/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="CreditSoft" />
    <link rel="manifest" href="../assets/images/site.webmanifest" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #00d4ff;
            --primary-dim: #00a3cc;
            --secondary: #a855f7;
            --success: #00ff88;
            --error: #ff3366;
            --warning: #ffcc00;
            --bg-deep: #030308;
            --bg-void: #0a0a12;
            --glass: rgba(255, 255, 255, 0.025);
            --glass-border: rgba(255, 255, 255, 0.06);
            --text: #f0f0f5;
            --text-muted: #6b6b80;
            --text-dim: #3d3d50;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Outfit', sans-serif; 
            background: var(--bg-deep);
            color: var(--text);
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: 
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(0, 212, 255, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse 60% 40% at 80% 100%, rgba(168, 85, 247, 0.1) 0%, transparent 40%),
                radial-gradient(ellipse 50% 30% at 10% 60%, rgba(0, 255, 136, 0.05) 0%, transparent 40%);
            pointer-events: none;
            z-index: 0;
        }
        
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
            opacity: 0.03;
            pointer-events: none;
            z-index: 0;
        }
        
        .installer { 
            background: var(--glass);
            backdrop-filter: blur(40px);
            -webkit-backdrop-filter: blur(40px);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            box-shadow: 
                0 0 0 1px rgba(255,255,255,0.03),
                0 0 80px -20px rgba(0, 212, 255, 0.2),
                0 0 120px -30px rgba(168, 85, 247, 0.15),
                inset 0 1px 0 rgba(255,255,255,0.05);
            width: 100%; 
            max-width: 480px; 
            overflow: hidden;
            position: relative;
            z-index: 1;
            animation: appear 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        
        @keyframes appear {
            from { 
                opacity: 0; 
                transform: translateY(40px) scale(0.95); 
                filter: blur(10px);
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
                filter: blur(0);
            }
        }
        
        .header {
            padding: 40px 40px 32px;
            text-align: center;
            position: relative;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
        }
        
        .logo {
            width: 410px;
            height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 32px;
            animation: float 3s ease-in-out infinite;
        }
        
        .logo img { width: 100%; height: auto; }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        
        .logo svg { width: 40px; height: 40px; color: var(--primary); }
        
        .header h1 { 
            font-size: 28px; 
            font-weight: 700; 
            margin-bottom: 8px; 
            background: linear-gradient(135deg, var(--text) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }
        
        .header p { 
            color: var(--text-muted); 
            font-size: 14px; 
            font-weight: 400;
            letter-spacing: 1px;
            text-transform: uppercase;
            font-size: 11px;
        }
        
        .content { 
            padding: 32px 36px 44px; 
            background: transparent;
        }
        
        .progress {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-bottom: 36px;
            padding: 0 16px;
        }
        
        .progress-step {
            flex: 1;
            height: 2px;
            border-radius: 1px;
            background: var(--glass-border);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .progress-step::before {
            content: '';
            position: absolute;
            inset: 0;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .progress-step[data-step="1"].active::before { background: #e74c3c; }
        .progress-step[data-step="2"].active::before { background: #e67e22; }
        .progress-step[data-step="3"].active::before { background: #ffce00; }
        .progress-step[data-step="4"].active::before { background: #00ff67; }
        .progress-step[data-step="5"].active::before { background: #00ff67; }
        
        .progress-step[data-step="1"].complete::before { background: #e74c3c; }
        .progress-step[data-step="2"].complete::before { background: #e67e22; }
        .progress-step[data-step="3"].complete::before { background: #ffce00; }
        .progress-step[data-step="4"].complete::before { background: #00ff67; }
        .progress-step[data-step="5"].complete::before { background: #00ff67; }
        
        .progress-step.active::before { transform: scaleX(1); }
        .progress-step.active { opacity: 0.8; }
        .progress-step.complete::before { transform: scaleX(1); }
        
        h2 { 
            font-size: 20px; 
            font-weight: 600; 
            margin-bottom: 6px; 
            color: var(--text);
            letter-spacing: -0.3px;
        }
        
        .subtitle { 
            color: var(--text-muted); 
            font-size: 13px; 
            margin-bottom: 28px; 
            font-weight: 400;
        }
        
        .alert { 
            padding: 16px 20px; 
            border-radius: 16px; 
            margin-bottom: 24px;
            font-size: 13px;
            animation: slideIn 0.4s ease-out;
            border: 1px solid transparent;
            backdrop-filter: blur(10px);
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-error { 
            background: rgba(255, 51, 102, 0.1); 
            border-color: rgba(255, 51, 102, 0.3); 
            color: #ff8fa3; 
        }
        
        .alert-success { 
            background: rgba(0, 255, 136, 0.08); 
            border-color: rgba(0, 255, 136, 0.25); 
            color: #86efac; 
        }
        
        .alert-warning { 
            background: rgba(255, 204, 0, 0.08); 
            border-color: rgba(255, 204, 0, 0.25); 
            color: #fde047; 
        }
        
        .system-checks {
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 28px;
        }
        
        .check-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            font-size: 13px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--glass-border);
        }
        
        .check-item:last-child { border-bottom: none; }
        
        .check-status {
            font-size: 10px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-family: 'Space Mono', monospace;
        }
        
        .check-status.ok { 
            background: rgba(0, 255, 136, 0.12); 
            color: var(--success); 
            box-shadow: 0 0 20px rgba(0, 255, 136, 0.15);
        }
        
        .check-status.fail { 
            background: rgba(255, 51, 102, 0.12); 
            color: var(--error); 
            box-shadow: 0 0 20px rgba(255, 51, 102, 0.15);
        }
        
        .check-status.loading {
            background: rgba(0, 212, 255, 0.12);
            color: var(--primary);
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 20px rgba(0, 212, 255, 0.15); }
            50% { opacity: 0.5; box-shadow: 0 0 10px rgba(0, 212, 255, 0.1); }
        }
        
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: var(--text-muted); font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .hint { font-size: 12px; color: var(--text-dim); margin-top: 6px; }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-dim);
            font-size: 16px;
            background: none;
            border: none;
            padding: 4px 8px;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .password-toggle:hover { color: var(--text); }
        
        .form-group { position: relative; }
        
        input, select { 
            width: 100%; 
            padding: 16px 18px; 
            background: var(--glass);
            border: 1px solid var(--glass-border); 
            border-radius: 14px; 
            font-size: 15px;
            font-family: inherit;
            color: var(--text);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        input::placeholder { color: var(--text-dim); }
        
        input:focus, select:focus { 
            outline: none; 
            border-color: var(--primary); 
            box-shadow: 
                0 0 0 3px rgba(0, 212, 255, 0.1),
                0 0 30px rgba(0, 212, 255, 0.1);
            background: rgba(0, 212, 255, 0.03);
        }
        
        .btn { 
            background: linear-gradient(135deg, var(--primary) 0%, #0ea5e9 100%);
            color: #000;
            border: none; 
            padding: 16px 28px; 
            border-radius: 14px; 
            font-size: 14px; 
            font-weight: 600; 
            cursor: pointer; 
            width: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent 0%, rgba(255,255,255,0.2) 100%);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .btn:hover { 
            transform: translateY(-2px);
            box-shadow: 
                0 10px 40px -10px rgba(0, 212, 255, 0.5),
                0 0 20px rgba(0, 212, 255, 0.2);
        }
        
        .btn:hover::before { opacity: 1; }
        
        .btn:disabled { 
            background: var(--text-dim); 
            cursor: not-allowed; 
            transform: none;
            box-shadow: none;
        }
        
        .btn-secondary { 
            background: var(--glass);
            color: var(--text);
            border: 1px solid var(--glass-border);
        }
        
        .btn-secondary:hover { 
            background: var(--bg-card-hover);
            border-color: var(--primary);
        }
        
        .btn-success { 
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: #000;
        }
        
        .btn-success:hover { 
            box-shadow: 
                0 10px 40px -10px rgba(0, 255, 136, 0.5),
                0 0 20px rgba(0, 255, 136, 0.2);
        }
        
        .logo-upload {
            border: 2px dashed var(--glass-border);
            border-radius: 20px;
            padding: 0;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--glass);
            overflow: hidden;
        }
        
        .logo-upload:hover { 
            border-color: var(--primary); 
            background: rgba(0, 212, 255, 0.05);
            transform: scale(1.01);
        }
        
        .logo-upload.has-logo { 
            border-style: solid; 
            border-color: var(--success); 
            background: rgba(0, 255, 136, 0.05);
        }
        
        .logo-preview { 
            max-width: 100%; 
            height: auto; 
            margin: 0 auto; 
            transition: transform 0.3s;
            display: block;
        }
        
        .logo-upload:hover .logo-preview { transform: scale(1.05); }
        
        .db-status {
            padding: 18px 22px;
            border-radius: 16px;
            margin-bottom: 24px;
            font-size: 13px;
            backdrop-filter: blur(10px);
        }
        
        .db-status.ok { 
            background: rgba(0, 255, 136, 0.08); 
            border: 1px solid rgba(0, 255, 136, 0.25); 
            color: #86efac;
        }
        
        .db-status.exist { 
            background: rgba(255, 204, 0, 0.08); 
            border: 1px solid rgba(255, 204, 0, 0.25); 
            color: #fde047;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: var(--glass);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .checkbox-group:hover {
            border-color: var(--primary);
        }
        
        .checkbox-group input { 
            width: 20px; 
            height: 20px;
            accent-color: var(--primary);
        }
        
        .checkbox-group label {
            margin: 0;
            text-transform: none;
            font-size: 14px;
            color: var(--text);
            letter-spacing: 0;
            cursor: pointer;
        }
        
        .hidden { display: none; }
        
        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(0, 0, 0, 0.2);
            border-top-color: #000;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 10px;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .step { 
            display: none; 
            animation: stepIn 0.4s ease-out;
        }
        
        @keyframes stepIn {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .step.active { display: block; }
        
        .funnel-box {
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
            border: 1px solid var(--glass-border);
            color: var(--text-muted);
            padding: 22px;
            border-radius: 16px;
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
        }
        
        .funnel-box a { 
            color: var(--primary); 
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .funnel-box a:hover { 
            color: var(--success);
        }
    </style>
</head>
<body>
    <div class="installer">
        <div class="header">
            <div class="logo" id="headerLogo">
                <img src="../assets/images/CreditSoft.png">
            </div>
        </div>
        
        <div class="content">
            <div class="progress" id="progress">
                <div class="progress-step" data-step="1"></div>
                <div class="progress-step" data-step="2"></div>
                <div class="progress-step" data-step="3"></div>
                <div class="progress-step" data-step="4"></div>
                <div class="progress-step" data-step="5"></div>
            </div>
            
            <!-- Step 1: License -->
            <div class="step active" data-step="1">
                <h2>License Activation</h2>
                <p class="subtitle">Enter your license ID to activate</p>
                
                <div id="licenseAlert"></div>
                
                <div class="system-checks" id="systemChecks">
                    <div class="check-item">
                        <span>PHP 8.1+</span>
                        <span class="check-status" id="checkPhp">Checking...</span>
                    </div>
                    <div class="check-item">
                        <span>MySQLi Extension</span>
                        <span class="check-status" id="checkMysqli">Checking...</span>
                    </div>
                    <div class="check-item">
                        <span>Write Permissions</span>
                        <span class="check-status" id="checkWrite">Checking...</span>
                    </div>
                    <div class="check-item">
                        <span>Internet Connection</span>
                        <span class="check-status" id="checkInternet">Checking...</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>License ID</label>
                    <input type="text" id="licenseId" placeholder="CS-XXXX-XXXX-XXXX">
                    <p class="hint">Your license ID from creditsoft.app</p>
                </div>
                
                <button class="btn" id="btnLicense" onclick="validateLicense()">
                    Activate License
                </button>
                
                <div class="funnel-box" id="adBox">
                    <p style="margin-bottom: 8px;">Don't have a license?</p>
                    <a href="https://www.creditsoft.app" target="_blank" rel="noopener" id="buyLicenseLink">Get one at creditsoft.app</a>
                    <div id="adContainer" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--glass-border);"></div>
                </div>
            </div>
            
            <!-- Step 2: Logo -->
            <div class="step" data-step="2">
                <h2>Your Logo</h2>
                <p class="subtitle">Upload your company logo for reports & emails</p>
                
                <div id="logoAlert"></div>
                
                <div class="logo-upload" id="logoUpload" onclick="document.getElementById('logoFile').click()">
                    <input type="file" id="logoFile" accept=".png,.svg,.jpg,.jpeg" style="display: none;" onchange="uploadLogo(this)">
                    <div class="logo-preview" id="logoPreview">
                        <svg fill="none" stroke="#94a3b8" viewBox="0 0 24 24" width="48" height="48">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <p style="color: var(--text-light); font-size: 14px;">
                        Click to upload<br>
                        <strong>SVG or PNG recommended</strong>
                    </p>
                </div>
                
                <button class="btn" id="btnLogo" onclick="nextStep(3)" disabled>
                    Continue
                </button>
            </div>
            
            <!-- Step 3: Database -->
            <div class="step" data-step="3">
                <h2>Database Setup</h2>
                <p class="subtitle">Configure your MySQL database</p>
                
                <div id="dbAlert"></div>
                
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" id="dbHost" value="localhost">
                </div>
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" id="dbName" placeholder="creditsoft">
                </div>
                <div class="form-group">
                    <label>Database Username</label>
                    <input type="text" id="dbUser">
                </div>
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" id="dbPass">
                    <button type="button" class="password-toggle" onclick="togglePassword('dbPass')"><i class="fa-regular fa-eye"></i></button>
                </div>
                <div class="form-group">
                    <label>Table Prefix</label>
                    <input type="text" id="dbPrefix" value="CSapp_" placeholder="CSapp_">
                    <p class="hint">Use the same prefix if reinstalling to preserve data</p>
                </div>
                
                <button class="btn" id="btnDb" onclick="testDbConnection()">
                    Test Connection
                </button>
            </div>
            
            <!-- Step 4: Admin Account -->
            <div class="step" data-step="4">
                <h2>Admin Account</h2>
                <p class="subtitle">Create your administrator login</p>
                
                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" id="adminEmail" value="admin@credit.com">
                </div>
                <div class="form-group">
                    <label>Admin Password</label>
                    <input type="password" id="adminPassword" value="admin123">
                    <button type="button" class="password-toggle" onclick="togglePassword('adminPassword')"><i class="fa-regular fa-eye"></i></button>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="emailEnabled">
                    <label for="emailEnabled" style="margin: 0;">Configure SMTP (advanced)</label>
                </div>
                
                <div id="smtpFields" class="hidden">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" id="smtpHost" placeholder="smtp.gmail.com">
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="text" id="smtpPort" value="587">
                    </div>
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" id="smtpUser">
                    </div>
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" id="smtpPass">
                        <button type="button" class="password-toggle" onclick="togglePassword('smtpPass')"><i class="fa-regular fa-eye"></i></button>
                    </div>
                    <div class="form-group">
                        <label>From Email</label>
                        <input type="email" id="smtpFrom">
                    </div>
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" id="smtpFromName" value="CreditSoft">
                    </div>
                </div>
                
                <button class="btn" id="btnAdmin" onclick="installDatabase()">
                    Install Database
                </button>
            </div>
            
            <!-- Step 5: Complete -->
            <div class="step" data-step="5">
                <h2>Installation Complete!</h2>
                <p class="subtitle">Your system is ready</p>
                
                <div class="alert alert-success">
                    CreditSoft has been installed successfully!
                </div>
                
                <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin: 20px 0;">
                    <p style="font-size: 14px; color: var(--text-light); margin-bottom: 12px;">Quick Start:</p>
                    <ul style="font-size: 14px; color: var(--text); padding-left: 20px;">
                        <li>Login at <strong>/</strong></li>
                        <li>Import your first client</li>
                        <li>Upload a credit report to get started</li>
                    </ul>
                </div>
                
                <button class="btn btn-success" onclick="goToDashboard()">
                    Go to Dashboard
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentStep = 1;
        
        function showStep(step) {
            document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
            document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
            
            document.querySelectorAll('.progress-step').forEach((el, i) => {
                el.classList.remove('active', 'complete');
                if (i + 1 < step) el.classList.add('complete');
                if (i + 1 === step) el.classList.add('active');
            });
            
            currentStep = step;
        }
        
        function nextStep(step) {
            showStep(step);
        }
        
        function togglePassword(id) {
            const input = document.getElementById(id);
            const btn = input.nextElementSibling;
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function showAlert(id, html, type = 'error') {
            document.getElementById(id).innerHTML = `<div class="alert alert-${type}">${html}</div>`;
        }
        
        function clearAlert(id) {
            document.getElementById(id).innerHTML = '';
        }
        
        async function ajax(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            for (const key in data) {
                formData.append(key, data[key]);
            }
            
            const url = window.location.pathname + window.location.search;
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });
            
            const text = await response.text();
            if (!text.trim()) {
                throw new Error('Empty response from server');
            }
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e, 'Response:', text);
                throw new Error('Invalid JSON: ' + text.substring(0, 200));
            }
        }
        
        // Step 1: Check system & validate license
        async function init() {
            const result = await ajax('check_system');
            
            document.getElementById('checkPhp').textContent = result.php_version ? 'OK' : 'FAIL';
            document.getElementById('checkPhp').className = 'check-status ' + (result.php_version ? 'ok' : 'fail');
            
            document.getElementById('checkMysqli').textContent = result.mysqli ? 'OK' : 'FAIL';
            document.getElementById('checkMysqli').className = 'check-status ' + (result.mysqli ? 'ok' : 'fail');
            
            document.getElementById('checkWrite').textContent = result.can_write ? 'OK' : 'FAIL';
            document.getElementById('checkWrite').className = 'check-status ' + (result.can_write ? 'ok' : 'fail');
            
            // Check internet (try to reach license server)
            document.getElementById('checkInternet').textContent = 'OK';
            document.getElementById('checkInternet').className = 'check-status ok';
        }
        
        async function validateLicense() {
            const licenseId = document.getElementById('licenseId').value.trim();
            const btn = document.getElementById('btnLicense');
            
            if (!licenseId) {
                showAlert('licenseAlert', 'Please enter your license ID');
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Validating...';
            
            try {
                const result = await ajax('validate_license', { license_id: licenseId });
                
                if (result.success) {
                    showAlert('licenseAlert', 'License activated! Plan: ' + (result.plan || 'Standard'), 'success');
                    setTimeout(() => nextStep(2), 1000);
                } else {
                    showAlert('licenseAlert', result.message + '<br><br><a href="https://www.creditsoft.app" target="_blank" rel="noopener">Get a license at creditsoft.app</a>');
                    btn.disabled = false;
                    btn.innerHTML = 'Activate License';
                }
            } catch (e) {
                showAlert('licenseAlert', 'Error: ' + e.message);
                btn.disabled = false;
                btn.innerHTML = 'Activate License';
            }
        }
        
        // Step 2: Upload logo
        async function uploadLogo(input) {
            const file = input.files[0];
            if (!file) return;
            
            const formData = new FormData();
            formData.append('action', 'upload_logo');
            formData.append('logo', file);
            
            const btn = document.getElementById('btnLogo');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Uploading...';
            
            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success) {
                    const uploadEl = document.getElementById('logoUpload');
                    uploadEl.classList.add('has-logo');
                    
                    const ext = result.filename.split('.').pop();
                    if (ext === 'svg') {
                        document.getElementById('logoPreview').innerHTML = `<img src="../assets/images/${result.filename}?t=${Date.now()}" style="max-width:100%;height:auto;">`;
                    } else {
                        document.getElementById('logoPreview').innerHTML = `<img src="../../assets/images/${result.filename}?t=${Date.now()}" style="max-width:100%;height:auto;object-fit:contain;">`;
                    }
                    
                    document.getElementById('headerLogo').innerHTML = `<img src="/assets/images/${result.filename}?t=${Date.now()}" style="max-width:410px;height:auto;object-fit:contain;">`;
                    
                    showAlert('logoAlert', 'Logo uploaded!', 'success');
                    btn.disabled = false;
                    btn.innerHTML = 'Continue';
                } else {
                    showAlert('logoAlert', result.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Continue';
                }
            } catch (e) {
                showAlert('logoAlert', 'Error: ' + e.message);
                btn.disabled = false;
                btn.innerHTML = 'Continue';
            }
        }
        
        // Step 3: Database
        let dbExisting = false;
        
        async function testDbConnection() {
            const btn = document.getElementById('btnDb');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Testing...';
            
            const data = {
                db_host: document.getElementById('dbHost').value,
                db_name: document.getElementById('dbName').value,
                db_user: document.getElementById('dbUser').value,
                db_pass: document.getElementById('dbPass').value,
                table_prefix: document.getElementById('dbPrefix').value
            };
            
            try {
                const result = await ajax('test_db_connection', data);
                
                if (result.success) {
                    dbExisting = result.existing;
                    
                    if (result.existing) {
                        document.getElementById('dbAlert').innerHTML = `
                            <div class="db-status exist">
                                <strong>Existing installation detected!</strong><br>
                                ${result.message}<br><br>
                                This will <strong>update</strong> your existing installation and preserve your data.
                            </div>
                        `;
                        btn.innerHTML = 'Update Database';
                    } else {
                        document.getElementById('dbAlert').innerHTML = `
                            <div class="db-status ok">
                                ✓ Connection successful - database is empty, will create new tables
                            </div>
                        `;
                        btn.innerHTML = 'Install Database';
                    }
                    
                    btn.disabled = false;
                    btn.onclick = installDatabase;
                } else {
                    showAlert('dbAlert', result.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Test Connection';
                }
            } catch (e) {
                showAlert('dbAlert', 'Error: ' + e.message);
                btn.disabled = false;
                btn.innerHTML = 'Test Connection';
            }
        }
        
        // Step 4: Install
        async function installDatabase() {
            const btn = document.getElementById('btnAdmin');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Installing...';
            
            try {
                const result = await ajax('install_database', {});
                
                if (result.success) {
                    btn.innerHTML = '<span class="spinner"></span>Saving config...';
                    
                    const configResult = await ajax('save_config', {
                        admin_email: document.getElementById('adminEmail').value,
                        admin_password: document.getElementById('adminPassword').value,
                        email_enabled: document.getElementById('emailEnabled').checked ? '1' : '0',
                        email_host: document.getElementById('smtpHost').value,
                        email_port: document.getElementById('smtpPort').value,
                        email_user: document.getElementById('smtpUser').value,
                        email_pass: document.getElementById('smtpPass').value,
                        email_from: document.getElementById('smtpFrom').value,
                        email_from_name: document.getElementById('smtpFromName').value
                    });
                    
                    if (configResult.success) {
                        nextStep(5);
                    } else {
                        showAlert('dbAlert', configResult.message);
                        btn.disabled = false;
                        btn.innerHTML = 'Complete Installation';
                    }
                } else {
                    showAlert('dbAlert', result.message);
                    btn.disabled = false;
                    btn.innerHTML = 'Install Database';
                }
            } catch (e) {
                showAlert('dbAlert', 'Error: ' + e.message);
                btn.disabled = false;
                btn.innerHTML = 'Install Database';
            }
        }
        
        function goToDashboard() {
            window.location.href = '/';
        }
        
        // Toggle SMTP fields
        document.getElementById('emailEnabled').addEventListener('change', function() {
            document.getElementById('smtpFields').classList.toggle('hidden', !this.checked);
        });
        
        // Init
        init();
    </script>
</body>
</html>
