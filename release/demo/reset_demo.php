<?php
/**
 * Demo Database Reset Script
 * Run via cron to reset demo database to clean state
 * 
 * Usage: php /path/to/demo/reset_demo.php
 * Cron: 0 * * * * php /path/to/demo/reset_demo.php
 */

$configPath = dirname(__DIR__) . '/credit_config.php';

if (!file_exists($configPath)) {
    die("Config file not found\n");
}

require $configPath;

$demoDb = $config['database']['database'];
$masterDb = $demoDb . '_master';

$mysqli = new mysqli(
    $config['database']['hostname'],
    $config['database']['username'],
    $config['database']['password'],
    $config['database']['database']
);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error . "\n");
}

$mysqli->query("DROP DATABASE IF EXISTS $demoDb");
$mysqli->query("CREATE DATABASE $demoDb");
$mysqli->query("USE $demoDb");

$sql = file_get_contents(__DIR__ . '/credit_system.sql');
$mysqli->multi_query($sql);

do { } while ($mysqli->next_result());
$mysqli->close();

echo "Demo database reset complete at " . date('Y-m-d H:i:s') . "\n";
