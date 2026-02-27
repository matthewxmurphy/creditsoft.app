<?php

namespace App\Controllers;

use App\Controllers\BaseController;

class Home extends BaseController
{
    public function index()
    {
        $data['page_title'] = 'CreditSoft - Metro2-First Credit Repair Software';
        $data['page_description'] = 'Stop relying on AI letters. Real results come from Metro2 compliance and proper dispute workflows.';
        
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
        $data['page_title'] = 'Pricing';
        $data['page_description'] = 'Credit repair software pricing. Unlimited users. No per-seat fees.';
        $data['page_hero'] = true;
        $data['hero_title'] = 'Simple, Transparent Pricing';
        $data['hero_subtitle'] = 'No hidden fees. No surprises. Just powerful credit repair software.';
        
        return view('home/pricing', $data);
    }
    
    public function quiz()
    {
        $data['page_title'] = 'Credit Repair Quiz';
        $data['page_description'] = 'Test your credit repair knowledge with our free quiz. 25 questions about Metro2, FCRA, FDCPA.';
        $data['page_hero'] = true;
        $data['hero_title'] = '📝 Credit Repair Knowledge Quiz';
        $data['hero_subtitle'] = 'Test your knowledge with 25 questions';
        
        return view('home/quiz', $data);
    }
    
    public function about()
    {
        $data['page_title'] = 'About Us';
        $data['page_description'] = 'Learn about CreditSoft - Metro2-first credit repair software built by credit repair professionals.';
        $data['page_hero'] = true;
        $data['hero_title'] = 'About CreditSoft';
        $data['hero_subtitle'] = 'Built by credit repair professionals, for credit repair professionals.';
        
        return view('home/about', $data);
    }
    
    public function croRules()
    {
        $data['page_title'] = '50-State CRO Rules';
        $data['page_description'] = 'Credit Repair Organization requirements by state. Bond amounts, registration, fee limits.';
        $data['page_hero'] = true;
        $data['hero_title'] = '50-State CRO Rules';
        $data['hero_subtitle'] = 'Complete guide to Credit Repair Organization requirements by state';
        
        return view('home/cro-rules', $data);
    }
    
    public function subscribe()
    {
        $data['page_title'] = 'Subscribe';
        $data['selected_plan'] = $this->request->getGet('plan') ?? 'professional';
        
        $config_path = dirname(__DIR__, 3) . '/credit_config.php';
        if (file_exists($config_path)) {
            require_once $config_path;
        }
        
        return view('home/subscribe', $data);
    }
    
    public function presalesTips()
    {
        $data['page_title'] = 'Pre-Sales Tips';
        return view('home/presales-tips', $data);
    }
    
    public function terms()
    {
        $data['page_title'] = 'Terms of Service';
        return view('home/terms', $data);
    }
    
    public function privacy()
    {
        $data['page_title'] = 'Privacy Policy';
        return view('home/privacy', $data);
    }
    
    public function cookies()
    {
        $data['page_title'] = 'Cookie Policy';
        return view('home/cookies', $data);
    }
    
    public function legal()
    {
        $data['page_title'] = 'Legal Information';
        return view('home/legal', $data);
    }
    
    public function security()
    {
        $data['page_title'] = 'Security';
        return view('home/security', $data);
    }
    
    public function metro2()
    {
        $data['page_title'] = 'Metro2 Credit Reporting';
        return view('home/metro2', $data);
    }
    
    public function clientPortal()
    {
        $data['page_title'] = 'Client Portal';
        return view('home/client-portal', $data);
    }
}
