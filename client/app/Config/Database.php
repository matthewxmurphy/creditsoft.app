<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Database extends BaseConfig
{
    public string $defaultGroup = 'default';
    
    // Load config from external file (outside web root)
    private function loadExternalConfig() {
        $configFile = dirname(FCPATH, 2) . '/.credit_config/config.php';
        if (file_exists($configFile)) {
            $config = include $configFile;
            if (is_array($config)) {
                return $config;
            }
        }
        return [];
    }
    
    public array $default = [];
    
    public function __construct() {
        parent::__construct();
        
        $external = $this->loadExternalConfig();
        
        $this->default = [
            'DSN'      => '',
            'hostname' => $external['database.default.hostname'] ?? 'localhost',
            'username' => $external['database.default.username'] ?? 'root',
            'password' => $external['database.default.password'] ?? '',
            'database' => $external['database.default.database'] ?? 'credit_system',
            'DBDriver' => $external['database.default.DBDriver'] ?? 'MySQLi',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug'  => true,
            'charset'  => 'utf8mb4',
            'DBCollat' => 'utf8mb4_unicode_ci',
            'swapPre'  => '',
            'encrypt'  => false,
            'compress' => false,
            'strictOn' => false,
            'failover' => [],
            'saveQueries' => true,
        ];
    }
}
