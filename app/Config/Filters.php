<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Filters extends BaseConfig
{
    public array $aliases = [
        'csrf'      => \CodeIgniter\Filters\CSRF::class,
        'toolbar'   => \CodeIgniter\Filters\DebugToolbar::class,
        'adminauth' => \App\Filters\AdminAuthFilter::class,
    ];

    public array $uri = [
        'except' => [
            'login',
            'register',
            'auth/*',
            'api/*',
        ],
    ];
}
