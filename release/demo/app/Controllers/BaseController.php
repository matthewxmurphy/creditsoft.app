<?php

namespace App\Controllers;

use App\Models\ClientModel;
use App\Models\UserModel;
use App\Models\CreditReportModel;
use App\Models\AccountErrorModel;

/**
 * BaseController - Base controller with common functionality
 * 
 * Purpose: Shared methods for all controllers
 *          - Theme handling
 *          - Layout data
 *          - User info
 */
class BaseController extends \CodeIgniter\Controller
{
    protected $theme = 'light';
    
    /**
     * Pre-router hook - runs before any controller method
     * 
     * @return void
     */
    protected function initialize()
    {
        // Load helpers
        helper(['url', 'form', 'html']);
        
        // Set timezone
        date_default_timezone_set('America/New_York');
        
        // Set default theme
        $this->theme = 'light';
    }

    /**
     * Get data for the main layout (left rail + top rail)
     * 
     * @return array Layout data
     */
    protected function getLayoutData()
    {
        $userModel = new UserModel();
        $clientModel = new ClientModel();
        $errorModel = new AccountErrorModel();
        
        $userId = session()->get('user_id');
        $user = $userModel->find($userId);
        
        // Get statistics for badges
        $stats = [
            'clients'     => $clientModel->getStatistics(),
            'errors'      => $errorModel->getStatistics(),
        ];
        
        return [
            'user'         => $user,
            'theme'        => $this->theme,
            'stats'        => $stats,
            'notifications' => $this->getNotifications(),
        ];
    }

    /**
     * Get user notifications
     * 
     * @return array Array of notifications
     */
    protected function getNotifications()
    {
        $db = \Config\Database::connect();
        
        // Get recent open errors
        $recentErrors = $db->table('account_errors ae')
            ->select('ae.*, c.first_name, c.last_name, et.name as error_name')
            ->join('report_accounts ra', 'ra.id = ae.account_id')
            ->join('credit_reports cr', 'cr.id = ra.report_id')
            ->join('clients c', 'c.id = cr.client_id')
            ->join('error_types et', 'et.id = ae.error_type_id')
            ->where('ae.status', 'identified')
            ->orderBy('ae.identified_date', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();
        
        return [
            'open_errors' => $recentErrors,
        ];
    }

    /**
     * Render view with layout
     * 
     * @param string $view   The view file
     * @param array  $data   Data to pass to view
     * @return \CodeIgniter\HTTP\Response
     */
    protected function render($view, $data = [])
    {
        $layoutData = $this->getLayoutData();
        $data = array_merge($layoutData, $data);
        
        // Load theme CSS/JS
        $theme = $data['theme'] ?? 'light';
        
        return view('layout/header', ['theme' => $theme])
            . view('layout/left_rail', $data)
            . view($view, $data)
            . view('layout/footer');
    }

    /**
     * Check if user has permission
     * 
     * @param string $role Required role
     * @return bool
     */
    protected function hasPermission($role)
    {
        $userRole = session()->get('role');
        
        $roles = ['admin' => 3, 'manager' => 2, 'staff' => 1];
        
        if (!isset($roles[$userRole]) || !isset($roles[$role])) {
            return false;
        }
        
        return $roles[$userRole] >= $roles[$role];
    }

    /**
     * Log activity
     * 
     * @param string $action      Action performed
     * @param string $entityType  Entity type
     * @param int    $entityId    Entity ID
     * @param string $description Description
     * @return void
     */
    protected function logActivity($action, $entityType, $entityId, $description)
    {
        $db = \Config\Database::connect();
        
        $db->table('activity_log')->insert([
            'user_id'      => session()->get('user_id'),
            'action'       => $action,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'description'  => $description,
            'ip_address'   => $this->request->getIPAddress(),
            'user_agent'   => $this->request->getUserAgent()->getAgentString(),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }
}

/**
 * Dashboard Controller - Main dashboard
 * 
 * Purpose: Display overview statistics and quick actions
 * 
 * @method index() - Main dashboard view
 */
class Dashboard extends BaseController
{
    /**
     * Initialize controller
     */
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->initialize();
    }

    /**
     * Main dashboard view
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function index()
    {
        $clientModel = new ClientModel();
        $errorModel = new AccountErrorModel();
        $reportModel = new CreditReportModel();
        
        $db = \Config\Database::connect();
        
        // Get dashboard statistics
        $data = [
            'title' => 'Dashboard',
            'stats' => [
                'total_clients'    => $clientModel->countAll(),
                'active_clients'   => $clientModel->where('status', 'active')->countAllResults(),
                'prospect_clients' => $clientModel->where('status', 'prospect')->countAllResults(),
                'open_errors'      => $errorModel->whereNotIn('status', ['closed', 'rejected'])->countAllResults(),
                'resolved_errors'  => $errorModel->where('status', 'corrected')->countAllResults(),
                'total_reports'    => $reportModel->countAll(),
                'this_month_reports' => $reportModel
                    ->where('report_month', date('n'))
                    ->where('report_year', date('Y'))
                    ->countAllResults(),
            ],
            // Recent clients
            'recent_clients' => $db->table('client_summary')
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->get()
                ->getResultArray(),
            // Recent errors
            'recent_errors' => $db->table('account_errors ae')
                ->select('ae.*, c.first_name, c.last_name, et.name as error_name, et.severity')
                ->join('report_accounts ra', 'ra.id = ae.account_id')
                ->join('credit_reports cr', 'cr.id = ra.report_id')
                ->join('clients c', 'c.id = cr.client_id')
                ->join('error_types et', 'et.id = ae.error_type_id')
                ->whereNotIn('ae.status', ['closed', 'rejected'])
                ->orderBy('ae.identified_date', 'DESC')
                ->limit(5)
                ->get()
                ->getResultArray(),
            // Score trend data for chart
            'score_trends' => $this->getScoreTrends(),
        ];
        
        return $this->render('layout/content', $data);
    }

    /**
     * Get score trends for chart
     * 
     * @return array Score trend data
     */
    private function getScoreTrends()
    {
        $db = \Config\Database::connect();
        
        $reports = $db->table('credit_reports')
            ->select('report_month, report_year, AVG(score) as avg_score')
            ->where('report_year >=', date('Y') - 1)
            ->groupBy('report_year, report_month')
            ->orderBy('report_year, report_month')
            ->get()
            ->getResultArray();
        
        return $reports;
    }
}
