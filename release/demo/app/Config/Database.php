<?php

namespace Config;

use CodeIgniter\Database\Config;

class Database extends Config
{
    public string $defaultGroup = 'default';
    
    public array $default = [
        'DSN'          => '',
        'hostname'      => 'localhost',
        'username'     => 'matthewx1_credit',
        'password'     => 'PF5_mYAdBHt$WlVO',
        'database'     => 'matthewx1_credit',
        'DBDriver'     => 'MySQLi',
        'DBPrefix'     => 'CSapp__',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8mb4',
        'DBCollat'     => 'utf8mb4_general_ci',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 3306,
        'numberNative' => false,
        'foundRows'    => false,
    ];
}
