<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class Home extends BaseController
{
    public function index()
    {
        $data['page_title'] = 'CreditSoft - Metro2-First Credit Repair Software';
        $data['page_description'] = 'Stop relying on AI letters. Real results come from Metro2 compliance and proper dispute workflows.';
        
        // Load config
        $config_path = dirname(__DIR__, 3) . '/credit_config.php';
        if (file_exists($config_path)) {
            require_once $config_path;
        }
        
        $data['config'] = [
            'turnstile_site_key' => defined('TURNSTILE_SITE_KEY') ? TURNSTILE_SITE_KEY : '',
        ];
        
        return view('home/index', $data);
    }
    
    public function pricing()
    {
        $data['page_title'] = 'Pricing - CreditSoft';
        return view('home/pricing', $data);
    }
    
    public function subscribe()
    {
        $data['page_title'] = 'Subscribe - CreditSoft';
        $data['selected_plan'] = $this->request->getGet('plan') ?? 'professional';
        
        $config_path = dirname(__DIR__, 3) . '/credit_config.php';
        if (file_exists($config_path)) {
            require_once $config_path;
        }
        
        return view('home/subscribe', $data);
    }
    
    public function presalesTips()
    {
        $data['page_title'] = 'Pre-Sales Tips - CreditSoft';
        return view('home/presales-tips', $data);
    }
    
    public function terms()
    {
        $data['page_title'] = 'Terms of Service - CreditSoft';
        return view('home/terms', $data);
    }
    
    public function privacy()
    {
        $data['page_title'] = 'Privacy Policy - CreditSoft';
        return view('home/privacy', $data);
    }
    
    public function cookies()
    {
        $data['page_title'] = 'Cookie Policy - CreditSoft';
        return view('home/cookies', $data);
    }
    
    public function legal()
    {
        $data['page_title'] = 'Legal Information - CreditSoft';
        return view('home/legal', $data);
    }
}
