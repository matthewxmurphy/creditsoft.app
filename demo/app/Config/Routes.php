<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Routes extends BaseConfig
{
    public bool $autoRoute = false;

    public array $routes = [
        'default_controller' => 'auth/login',
        'translateURIToCamelCase' => false,
    ];

    public function __construct()
    {
        // Home/Landing page
        $this->routes['GET']['/'] = 'Home/index';

        // Authentication routes
        $this->routes['GET']['login'] = 'auth/login';
        $this->routes['POST']['login']['POST']['auth/doLogin'] = 'auth/doLogin';
        $this->routes['GET']['logout'] = 'auth/logout';
        $this->routes['GET']['register'] = 'auth/register';
        $this->routes['POST']['register']['POST']['auth/doRegister'] = 'auth/doRegister';

        // Dashboard
        $this->routes['GET']['dashboard'] = 'dashboard/index';

        // Clients
        $this->routes['GET']['clients'] = 'clients/index';
        $this->routes['GET']['clients/new'] = 'clients/create';
        $this->routes['POST']['clients']['POST']['clients/store'] = 'clients/store';
        $this->routes['GET']['clients/(:num)'] = 'clients/view/$1';
        $this->routes['GET']['clients/(:num)/edit'] = 'clients/edit/$1';
        $this->routes['POST']['clients/(:num)']['POST']['clients/update/$1'] = 'clients/update/$1';
        $this->routes['POST']['clients/(:num)']['DELETE']['clients/delete/$1'] = 'clients/delete/$1';

        // Reports
        $this->routes['GET']['reports'] = 'reports/index';
        $this->routes['GET']['reports/import'] = 'reports/import';
        $this->routes['POST']['reports']['POST']['reports/store'] = 'reports/store';
        $this->routes['POST']['reports/parse-html']['POST']['reports/parseHtml'] = 'reports/parseHtml';
        $this->routes['GET']['reports/(:num)'] = 'reports/view/$1';

        // Comparison
        $this->routes['GET']['comparison'] = 'comparison/index';
        $this->routes['GET']['comparison/client/(:num)'] = 'comparison/client/$1';
        $this->routes['POST']['comparison']['POST']['comparison/generate'] = 'comparison/generate';

        // Errors
        $this->routes['GET']['errors'] = 'errors/index';
        $this->routes['GET']['errors/(:num)'] = 'errors/view/$1';
        $this->routes['POST']['errors']['POST']['errors/store'] = 'errors/store';
        $this->routes['POST']['errors/(:num)']['POST']['errors/update/$1'] = 'errors/update/$1';

        // Disputes & Letters
        $this->routes['GET']['disputes'] = 'disputes/index';
        $this->routes['GET']['disputes/generate'] = 'disputes/generate';
        $this->routes['POST']['disputes']['POST']['disputes/create'] = 'disputes/create';
        $this->routes['GET']['disputes/(:num)/pdf'] = 'disputes/pdf/$1';
        $this->routes['POST']['disputes/(:num)']['POST']['disputes/send/$1'] = 'disputes/send/$1';

        // Analytics
        $this->routes['GET']['analytics'] = 'analytics/index';

        // CRM
        $this->routes['GET']['crm/notes'] = 'crm/notes';
        $this->routes['POST']['crm/notes']['POST']['crm/notesStore'] = 'crm/notesStore';
        $this->routes['POST']['crm/notes/(:num)']['POST']['crm/notesUpdate/$1'] = 'crm/notesUpdate/$1';

        // Drips
        $this->routes['GET']['drips'] = 'drips/index';
        $this->routes['GET']['drips/campaigns'] = 'drips/campaigns';
        $this->routes['POST']['drips/campaigns']['POST']['drips/campaignStore'] = 'drips/campaignStore';

        // SOPs
        $this->routes['GET']['sops'] = 'sops/index';
        $this->routes['GET']['sops/(:num)'] = 'sops/view/$1';
        $this->routes['POST']['sops']['POST']['sops/store'] = 'sops/store';

        // AI
        $this->routes['POST']['api/ai/chat']['POST']['api/aiChat'] = 'api/aiChat';
        $this->routes['POST']['api/ai/explain']['POST']['api/aiExplain'] = 'api/aiExplain';
        $this->routes['POST']['api/ai/note']['POST']['api/aiNote'] = 'api/aiNote';
        $this->routes['POST']['api/ai/drip']['POST']['api/aiDrip'] = 'api/aiDrip';

        // Extension API
        $this->routes['POST']['api/extension/ingest']['POST']['api/extensionIngest'] = 'api/extensionIngest';

        // Settings
        $this->routes['GET']['settings'] = 'settings/index';
        $this->routes['POST']['settings']['POST']['settings/update'] = 'settings/update';
    }
}
