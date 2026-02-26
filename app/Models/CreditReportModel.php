<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * CreditReportModel - Handle credit report database operations
 * 
 * Purpose: CRUD operations for credit_reports and report_accounts tables
 *          with Metro2 field mapping support
 * 
 * @method find($id)              - Find report by ID
 * @method findAll()             - Get all reports
 * @method insert($data)         - Create new report
 * @method update($id, $data)    - Update report
 * @method delete($id)           - Delete report
 */
class CreditReportModel extends Model
{
    protected $table            = 'credit_reports';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'client_id',
        'bureau',
        'report_date',
        'report_month',
        'report_year',
        'raw_html',
        'pdf_data',
        'parsed_data',
        'score',
        'score_factor_1',
        'score_factor_2',
        'score_factor_3',
        'score_factor_4',
        'score_factor_5',
        'total_accounts',
        'open_accounts',
        'closed_accounts',
        'derogatory_count',
        'total_balance',
        'total_credit_limit',
        'total_monthly_payment',
        'utilization_rate',
        'data_source',
        'status',
        'imported_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get reports for a specific client
     * 
     * @param int $clientId The client ID
     * @return array Array of reports
     */
    public function getByClient($clientId)
    {
        return $this->where('client_id', $clientId)
            ->orderBy('report_year DESC, report_month DESC')
            ->findAll();
    }

    /**
     * Get reports by bureau
     * 
     * @param string $bureau The bureau name (experian, transunion, equifax)
     * @return array Array of reports
     */
    public function getByBureau($bureau)
    {
        return $this->where('bureau', $bureau)->findAll();
    }

    /**
     * Get latest report for a client by bureau
     * 
     * @param int    $clientId The client ID
     * @param string $bureau   The bureau name
     * @return array|null Report data or null
     */
    public function getLatestByBureau($clientId, $bureau)
    {
        return $this->where('client_id', $clientId)
            ->where('bureau', $bureau)
            ->orderBy('report_year DESC, report_month DESC')
            ->first();
    }

    /**
     * Get reports for comparison (two months)
     * 
     * @param int $clientId    The client ID
     * @param int $monthFrom    From month
     * @param int $yearFrom     From year
     * @param int $monthTo      To month
     * @param int $yearTo       To year
     * @return array Array of reports
     */
    public function getForComparison($clientId, $monthFrom, $yearFrom, $monthTo, $yearTo)
    {
        return $this->where('client_id', $clientId)
            ->groupStart()
                ->where('report_month', $monthFrom)
                ->where('report_year', $yearFrom)
            ->groupEnd()
            ->orGroupStart()
                ->where('report_month', $monthTo)
                ->where('report_year', $yearTo)
            ->groupEnd()
            ->orderBy('report_year DESC, report_month DESC, bureau')
            ->findAll();
    }

    /**
     * Get reports for a specific month
     * 
     * @param int $month The month (1-12)
     * @param int $year  The year
     * @return array Array of reports
     */
    public function getByMonth($month, $year)
    {
        return $this->where('report_month', $month)
            ->where('report_year', $year)
            ->findAll();
    }

    /**
     * Calculate score change between two reports
     * 
     * @param array $reportFrom The "from" report
     * @param array $reportTo    The "to" report
     * @return array Score change data
     */
    public function calculateScoreChange($reportFrom, $reportTo)
    {
        $change = [
            'score_from'  => $reportFrom['score'] ?? 0,
            'score_to'    => $reportTo['score'] ?? 0,
            'score_change' => ($reportTo['score'] ?? 0) - ($reportFrom['score'] ?? 0),
            'balance_from' => $reportFrom['total_balance'] ?? 0,
            'balance_to'   => $reportTo['total_balance'] ?? 0,
            'balance_change' => ($reportTo['total_balance'] ?? 0) - ($reportFrom['total_balance'] ?? 0),
            'accounts_from' => $reportFrom['total_accounts'] ?? 0,
            'accounts_to'   => $reportTo['total_accounts'] ?? 0,
            'utilization_from' => $reportFrom['utilization_rate'] ?? 0,
            'utilization_to'   => $reportTo['utilization_rate'] ?? 0,
        ];
        
        return $change;
    }

    /**
     * Get score trend for a client
     * 
     * @param int    $clientId The client ID
     * @param string $bureau    The bureau (optional)
     * @return array Array of score history
     */
    public function getScoreTrend($clientId, $bureau = null)
    {
        $this->select('report_month, report_year, bureau, score, total_balance, utilization_rate');
        $this->where('client_id', $clientId);
        
        if ($bureau) {
            $this->where('bureau', $bureau);
        }
        
        return $this->orderBy('report_year ASC, report_month ASC')
            ->findAll();
    }

    /**
     * Get average score across all bureaus for a client
     * 
     * @param int $clientId The client ID
     * @return array Average scores per bureau
     */
    public function getAverageScores($clientId)
    {
        $db = \Config\Database::connect();
        
        $builder = $db->table('credit_reports');
        $results = $builder->select('bureau, AVG(score) as avg_score')
            ->where('client_id', $clientId)
            ->groupBy('bureau')
            ->get()
            ->getResultArray();
        
        $averages = [];
        foreach ($results as $row) {
            $averages[$row['bureau']] = $row['avg_score'];
        }
        
        return $averages;
    }
}

/**
 * ReportAccountModel - Handle individual account data
 * 
 * Purpose: CRUD operations for report_accounts table with Metro2 fields
 */
class ReportAccountModel extends Model
{
    protected $table            = 'report_accounts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'report_id',
        'metro2_field',
        'account_number',
        'subscriber_code',
        'account_type',
        'account_type_code',
        'portfolio_type',
        'date_opened',
        'date_closed',
        'date_reported',
        'date_last_activity',
        'date_last_payment',
        'credit_limit',
        'high_credit',
        'balance',
        'past_due_amount',
        'monthly_payment',
        'payment_status',
        'payment_status_code',
        'worst_payment_12m',
        'worst_payment_24m',
        'terms',
        'terms_months',
        'scheduled_monthly_payment',
        'actual_payment_amount',
        'charge_off_amount',
        'payment_history_24m',
        'comment',
        'consumer_comment',
        'account_name',
        'original_creditor',
        'current_creditor',
        'is_collection',
        'is_public_record',
        'dispute_flag',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_field';

    /**
     * Get accounts for a report
     * 
     * @param int $reportId The report ID
     * @return array Array of accounts
     */
    public function getByReport($reportId)
    {
        return $this->where('report_id', $reportId)->findAll();
    }

    /**
     * Get accounts by type
     * 
     * @param string $type The account type
     * @return array Array of accounts
     */
    public function getByType($type)
    {
        return $this->where('account_type', $type)->findAll();
    }

    /**
     * Get collection accounts
     * 
     * @param int $reportId The report ID
     * @return array Array of collection accounts
     */
    public function getCollections($reportId)
    {
        return $this->where('report_id', $reportId)
            ->where('is_collection', 1)
            ->findAll();
    }

    /**
     * Get public records
     * 
     * @param int $reportId The report ID
     * @return array Array of public records
     */
    public function getPublicRecords($reportId)
    {
        return $this->where('report_id', $reportId)
            ->where('is_public_record', 1)
            ->findAll();
    }

    /**
     * Compare accounts between two reports
     * 
     * @param int $reportFromId From report ID
     * @param int $reportToId   To report ID
     * @return array Comparison results
     */
    public function compareReports($reportFromId, $reportToId)
    {
        $accountsFrom = $this->getByReport($reportFromId);
        $accountsTo = $this->getByReport($reportToId);
        
        $fromAccounts = [];
        foreach ($accountsFrom as $acc) {
            $fromAccounts[$acc['account_number']] = $acc;
        }
        
        $toAccounts = [];
        foreach ($accountsTo as $acc) {
            $toAccounts[$acc['account_number']] = $acc;
        }
        
        $comparison = [
            'new'      => [],      // Accounts in "to" but not in "from"
            'removed'  => [],      // Accounts in "from" but not in "to"
            'changed'  => [],      // Accounts in both with changes
            'unchanged' => [],     // Accounts in both without changes
        ];
        
        foreach ($toAccounts as $accNum => $acc) {
            if (!isset($fromAccounts[$accNum])) {
                $comparison['new'][] = $acc;
            } else {
                $changes = $this->findChanges($fromAccounts[$accNum], $acc);
                if (!empty($changes)) {
                    $comparison['changed'][] = [
                        'account'  => $acc,
                        'changes'  => $changes,
                    ];
                } else {
                    $comparison['unchanged'][] = $acc;
                }
            }
        }
        
        foreach ($fromAccounts as $accNum => $acc) {
            if (!isset($toAccounts[$accNum])) {
                $comparison['removed'][] = $acc;
            }
        }
        
        return $comparison;
    }

    /**
     * Find changes between two account records
     * 
     * @param array $old Old account data
     * @param array $new New account data
     * @return array Array of changed fields
     */
    private function findChanges($old, $new)
    {
        $changes = [];
        $fieldsToCompare = [
            'balance', 'credit_limit', 'payment_status', 
            'monthly_payment', 'date_closed', 'date_last_payment'
        ];
        
        foreach ($fieldsToCompare as $field) {
            if (isset($old[$field]) && isset($new[$field]) && $old[$field] !== $new[$field]) {
                $changes[$field] = [
                    'from' => $old[$field],
                    'to'   => $new[$field],
                ];
            }
        }
        
        return $changes;
    }
}
