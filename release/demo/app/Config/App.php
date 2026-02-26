<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    public string $baseURL = 'https://ashley.matthewxmurphy.com/';
    public string $indexPage = '';
    public string $uriProtocol = 'REQUEST_URI';
    public string $defaultLocale = 'en';
    public bool $negotiateLocale = false;
    public array $supportedLocales = ['en'];
    public string $appTimezone = 'America/New_York';
    public string $charset = 'UTF-8';
    public bool $forceGlobalSecureRequests = false;
    public array $allowedHostnames = [];
    public int $sessionExpiration = 7200;
    public string $sessionCookieName = 'ci_session';
    public string $sessionSavePath = WRITEPATH . 'session';
    public bool $sessionMatchIP = false;
    public bool $sessionRegenerateDestroy = false;
    public int $sessionTimeToUpdate = 300;
    public string $cookiePrefix = '';
    public string $cookieDomain = '';
    public string $cookiePath = '/';
    public bool $cookieSecure = false;
    public bool $cookieHTTPOnly = false;
    public string $cookieSameSite = 'Lax';
    public array $proxyHosts = [];
    public bool $proxyHeaders = false;
    public bool $CSPEnabled = false;
    public array $proxyIPs = [];
}
