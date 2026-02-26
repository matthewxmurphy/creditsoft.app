<?php

namespace App\Controllers;

use App\Models\CreditReportModel;
use App\Models\ClientModel;
use App\Models\ComparisonModel;

/**
 * Comparison Controller - Handle month-over-month comparisons
 * 
 * Purpose: Side-by-side comparison of credit reports
 * 
 * @method index()       - Show comparison selection form
 * @method client()      - Show client comparison page
 * @method generate()    - Generate comparison
 */
class Comparison extends BaseController
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
     * Show comparison selection form
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function index()
    {
        $clientModel = new ClientModel();
        
        $data = [
            'title' => 'Compare Reports',
            'subtitle' => 'Side-by-side month comparison',
            'clients' => $clientModel->where('status', 'active')->findAll(),
        ];
        
        return view('comparison/index', $data);
    }

    /**
     * Show client comparison page
     * 
     * @param int $clientId Client ID
     * @return \CodeIgniter\HTTP\Response
     */
    public function client($clientId)
    {
        $clientModel = new ClientModel();
        $reportModel = new CreditReportModel();
        
        $client = $clientModel->find($clientId);
        
        if (!$client) {
            return redirect()->to('/comparison')
                ->with('error', 'Client not found.');
        }
        
        // Get available months for comparison
        $reports = $reportModel->getByClient($clientId);
        
        // Group by month/year
        $availableMonths = [];
        foreach ($reports as $report) {
            $key = $report['report_year'] . '-' . str_pad($report['report_month'], 2, '0', STR_PAD_LEFT);
            if (!isset($availableMonths[$key])) {
                $availableMonths[$key] = [
                    'month' => $report['report_month'],
                    'year'  => $report['report_year'],
                    'bureaus' => [],
                ];
            }
            $availableMonths[$key]['bureaus'][] = $report['bureau'];
        }
        
        $data = [
            'title' => 'Compare: ' . $client['first_name'] . ' ' . $client['last_name'],
            'subtitle' => $client['client_code'],
            'client' => $client,
            'availableMonths' => $availableMonths,
        ];
        
        return view('comparison/client', $data);
    }

    /**
     * Generate comparison between two months
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function generate()
    {
        $clientId = $this->request->getPost('client_id');
        $monthFrom = $this->request->getPost('month_from');
        $yearFrom = $this->request->getPost('year_from');
        $monthTo = $this->request->getPost('month_to');
        $yearTo = $this->request->getPost('year_to');
        
        // Validation
        if (!$clientId || !$monthFrom || !$yearFrom || !$monthTo || !$yearTo) {
            return redirect()->to('/comparison')
                ->with('error', 'Please select all required fields.');
        }
        
        $clientModel = new ClientModel();
        $reportModel = new CreditReportModel();
        
        $client = $clientModel->find($clientId);
        
        if (!$client) {
            return redirect()->to('/comparison')
                ->with('error', 'Client not found.');
        }
        
        // Get reports for comparison
        $reportsFrom = $reportModel->getByMonth($monthFrom, $yearFrom);
        $reportsTo = $reportModel->getByMonth($monthTo, $yearTo);
        
        // Filter by client
        $reportsFrom = array_filter($reportsFrom, fn($r) => $r['client_id'] == $clientId);
        $reportsTo = array_filter($reportsTo, fn($r) => $r['client_id'] == $clientId);
        
        // Build comparison data
        $comparison = [
            'from' => [
                'month' => $monthFrom,
                'year'  => $yearFrom,
                'reports' => [],
            ],
            'to' => [
                'month' => $monthTo,
                'year'  => $yearTo,
                'reports' => [],
            ],
            'changes' => [],
        ];
        
        // Process "from" reports
        foreach ($reportsFrom as $report) {
            $comparison['from']['reports'][$report['bureau']] = $report;
        }
        
        // Process "to" reports
        foreach ($reportsTo as $report) {
            $comparison['to']['reports'][$report['bureau']] = $report;
        }
        
        // Calculate changes for each bureau
        $bureaus = ['experian', 'transunion', 'equifax'];
        
        foreach ($bureaus as $bureau) {
            $fromReport = $comparison['from']['reports'][$bureau] ?? null;
            $toReport = $comparison['to']['reports'][$bureau] ?? null;
            
            if ($fromReport && $toReport) {
                $change = [
                    'bureau' => $bureau,
                    'score' => [
                        'from' => $fromReport['score'] ?? 0,
                        'to'   => $toReport['score'] ?? 0,
                        'change' => ($toReport['score'] ?? 0) - ($fromReport['score'] ?? 0),
                    ],
                    'balance' => [
                        'from' => $fromReport['total_balance'] ?? 0,
                        'to'   => $toReport['total_balance'] ?? 0,
                        'change' => ($toReport['total_balance'] ?? 0) - ($fromReport['total_balance'] ?? 0),
                    ],
                    'accounts' => [
                        'from' => $fromReport['total_accounts'] ?? 0,
                        'to'   => $toReport['total_accounts'] ?? 0,
                    ],
                    'utilization' => [
                        'from' => $fromReport['utilization_rate'] ?? 0,
                        'to'   => $toReport['utilization_rate'] ?? 0,
                    ],
                ];
                
                $comparison['changes'][$bureau] = $change;
            }
        }
        
        $data = [
            'title' => 'Comparison: ' . $client['first_name'] . ' ' . $client['last_name'],
            'subtitle' => date('F Y', mktime(0, 0, 0, $monthFrom, 1, $yearFrom)) . ' vs ' . date('F Y', mktime(0, 0, 0, $monthTo, 1, $yearTo)),
            'client' => $client,
            'comparison' => $comparison,
        ];
        
        return view('comparison/result', $data);
    }
}
