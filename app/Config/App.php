<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    public string $baseURL = 'http://localhost/';
    public string $indexPage = '';
    public string $uriProtocol = 'REQUEST_URI';
    public string $defaultLocale = 'en';
    public bool $negotiateLocale = false;
    public array $supportedLocales = ['en'];
    public string $appTimezone = 'America/New_York';
    public string $charset = 'UTF-8';
    public bool $forceGlobalSecureRequests = false;
    public int $sessionExpiration = 7200;
    public string $sessionCookieName = 'ci_session';
    public string $sessionSavePath = WRITEPATH . 'session';
    public bool $sessionMatchIP = false;
    public string $sessionRegenerateDestroy = false;
    public int $sessionTimeToUpdate = 300;
    public array $cookiePrefix = '';
    public string $cookieDomain = '';
    public string $cookiePath = '/';
    public bool $cookieSecure = false;
    public bool $cookieHTTPOnly = false;
    public bool $cookieSameSite = 'Lax';
    public array $proxyHosts = [];
    public bool $proxyHeaders = false;
    public int $CSPEnabled = false;
}
