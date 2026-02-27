<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Routes extends BaseConfig
{
    public bool $autoRoute = false;

    public array $routes = [
        'default_controller' => 'home',
        'translateURIToCamelCase' => false,
    ];

    public function __construct()
    {
        // Home / Landing Page
        $this->routes['GET']['/'] = 'home/index';
        $this->routes['GET']['home'] = 'home/index';
        
        // Storefront pages
        $this->routes['GET']['pricing'] = 'home/pricing';
        $this->routes['GET']['quiz'] = 'home/quiz';
        $this->routes['GET']['about'] = 'home/about';
        $this->routes['GET']['cro-rules'] = 'home/croRules';
        $this->routes['GET']['subscribe'] = 'home/subscribe';
        $this->routes['GET']['presales-tips'] = 'home/presalesTips';
        $this->routes['GET']['terms'] = 'home/terms';
        $this->routes['GET']['privacy'] = 'home/privacy';
        $this->routes['GET']['cookies'] = 'home/cookies';
        $this->routes['GET']['legal'] = 'home/legal';
        $this->routes['GET']['security'] = 'home/security';
        $this->routes['GET']['metro2'] = 'home/metro2';
        $this->routes['GET']['client-portal'] = 'home/clientPortal';
        $this->routes['GET']['activate'] = 'home/activate';
        
        // API endpoints (public)
        $this->routes['POST']['api/chat'] = 'api/chat';
        $this->routes['POST']['api/lead'] = 'api/lead';
        $this->routes['POST']['api/payment'] = 'api/payment';
        $this->routes['GET']['api/plans'] = 'api/plans';

        // Admin routes (prefixed with admin)
        $this->routes['GET']['admin'] = 'dashboard/index';
        $this->routes['GET']['admin/dashboard'] = 'dashboard/index';

        // Authentication routes
        $this->routes['GET']['admin/login'] = 'auth/login';
        $this->routes['POST']['admin/login']['POST']['auth/doLogin'] = 'auth/doLogin';
        $this->routes['GET']['admin/logout'] = 'auth/logout';
        $this->routes['GET']['admin/register'] = 'auth/register';
        $this->routes['POST']['admin/register']['POST']['auth/doRegister'] = 'auth/doRegister';

        // Dashboard
        $this->routes['GET']['admin/dashboard'] = 'dashboard/index';

        // Clients
        $this->routes['GET']['admin/clients'] = 'clients/index';
        $this->routes['GET']['admin/clients/new'] = 'clients/create';
        $this->routes['POST']['admin/clients']['POST']['clients/store'] = 'clients/store';
        $this->routes['GET']['admin/clients/(:num)'] = 'clients/view/$1';
        $this->routes['GET']['admin/clients/(:num)/edit'] = 'clients/edit/$1';
        $this->routes['POST']['admin/clients/(:num)']['POST']['clients/update/$1'] = 'clients/update/$1';
        $this->routes['POST']['admin/clients/(:num)']['DELETE']['clients/delete/$1'] = 'clients/delete/$1';

        // Reports
        $this->routes['GET']['admin/reports'] = 'reports/index';
        $this->routes['GET']['admin/reports/import'] = 'reports/import';
        $this->routes['POST']['admin/reports']['POST']['reports/store'] = 'reports/store';
        $this->routes['POST']['admin/reports/parse-html']['POST']['reports/parseHtml'] = 'reports/parseHtml';
        $this->routes['GET']['admin/reports/(:num)'] = 'reports/view/$1';

        // Comparison
        $this->routes['GET']['admin/comparison'] = 'comparison/index';
        $this->routes['GET']['admin/comparison/client/(:num)'] = 'comparison/client/$1';
        $this->routes['POST']['admin/comparison']['POST']['comparison/generate'] = 'comparison/generate';

        // Errors
        $this->routes['GET']['admin/errors'] = 'errors/index';
        $this->routes['GET']['admin/errors/(:num)'] = 'errors/view/$1';
        $this->routes['POST']['admin/errors']['POST']['errors/store'] = 'errors/store';
        $this->routes['POST']['admin/errors/(:num)']['POST']['errors/update/$1'] = 'errors/update/$1';

        // Disputes & Letters
        $this->routes['GET']['admin/disputes'] = 'disputes/index';
        $this->routes['GET']['admin/disputes/generate'] = 'disputes/generate';
        $this->routes['POST']['admin/disputes']['POST']['disputes/create'] = 'disputes/create';
        $this->routes['GET']['admin/disputes/(:num)/pdf'] = 'disputes/pdf/$1';
        $this->routes['POST']['admin/disputes/(:num)']['POST']['disputes/send/$1'] = 'disputes/send/$1';

        // Analytics
        $this->routes['GET']['admin/analytics'] = 'analytics/index';

        // CRM
        $this->routes['GET']['admin/crm/notes'] = 'crm/notes';
        $this->routes['POST']['admin/crm/notes']['POST']['crm/notesStore'] = 'crm/notesStore';
        $this->routes['POST']['admin/crm/notes/(:num)']['POST']['crm/notesUpdate/$1'] = 'crm/notesUpdate/$1';

        // Drips
        $this->routes['GET']['admin/drips'] = 'drips/index';
        $this->routes['GET']['admin/drips/campaigns'] = 'drips/campaigns';
        $this->routes['POST']['admin/drips/campaigns']['POST']['drips/campaignStore'] = 'drips/campaignStore';

        // SOPs
        $this->routes['GET']['admin/sops'] = 'sops/index';
        $this->routes['GET']['admin/sops/(:num)'] = 'sops/view/$1';
        $this->routes['POST']['admin/sops']['POST']['sops/store'] = 'sops/store';

        // AI
        $this->routes['POST']['admin/api/ai/chat']['POST']['api/aiChat'] = 'api/aiChat';
        $this->routes['POST']['admin/api/ai/explain']['POST']['api/aiExplain'] = 'api/aiExplain';
        $this->routes['POST']['admin/api/ai/note']['POST']['api/aiNote'] = 'api/aiNote';
        $this->routes['POST']['admin/api/ai/drip']['POST']['api/aiDrip'] = 'api/aiDrip';

        // Extension API
        $this->routes['POST']['admin/api/extension/ingest']['POST']['api/extensionIngest'] = 'api/extensionIngest';

        // Settings
        $this->routes['GET']['admin/settings'] = 'settings/index';
        $this->routes['POST']['admin/settings']['POST']['settings/update'] = 'settings/update';
    }
}
