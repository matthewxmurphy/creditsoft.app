<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ErrorTypeModel - Handle error type definitions (Metro2 codes)
 * 
 * Purpose: CRUD operations for error_types table with Metro2 error codes
 */
class ErrorTypeModel extends Model
{
    protected $table            = 'error_types';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'metro2_code',
        'category',
        'name',
        'description',
        'severity',
        'dispute_letter_template',
        'requires_evidence',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';

    /**
     * Get error types by category
     * 
     * @param string $category The category
     * @return array Array of error types
     */
    public function getByCategory($category)
    {
        return $this->where('category', $category)->findAll();
    }

    /**
     * Get error types by severity
     * 
     * @param string $severity The severity level
     * @return array Array of error types
     */
    public function getBySeverity($severity)
    {
        return $this->where('severity', $severity)->findAll();
    }

    /**
     * Get all categories
     * 
     * @return array Array of unique categories
     */
    public function getCategories()
    {
        $results = $this->select('category')->distinct()->findAll();
        return array_column($results, 'category');
    }

    /**
     * Get error type by Metro2 code
     * 
     * @param string $code The Metro2 code
     * @return array|null Error type data or null
     */
    public function getByMetro2Code($code)
    {
        return $this->where('metro2_code', $code)->first();
    }

    /**
     * Get active error types
     * 
     * @return array Array of active error types
     */
    public function getActive()
    {
        return $this->where('is_active', 1)->findAll();
    }
}

/**
 * AccountErrorModel - Handle identified errors on accounts
 * 
 * Purpose: Track errors found on credit report accounts
 */
class AccountErrorModel extends Model
{
    protected $table            = 'account_errors';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'account_id',
        'error_type_id',
        'identified_by',
        'identified_date',
        'resolved_date',
        'status',
        'dispute_method',
        'dispute_date',
        'dispute_reference',
        'response_received',
        'response_action',
        'notes',
        'evidence_files',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get errors by status
     * 
     * @param string $status The status
     * @return array Array of errors
     */
    public function getByStatus($status)
    {
        return $this->where('status', $status)->findAll();
    }

    /**
     * Get errors for a client
     * 
     * @param int $clientId The client ID
     * @return array Array of errors
     */
    public function getByClient($clientId)
    {
        $db = \Config\Database::connect();
        
        return $db->table('account_errors ae')
            ->select('ae.*, et.name as error_name, et.metro2_code, et.category, et.severity, 
                      ra.account_name, ra.account_number, ra.payment_status, ra.balance,
                      cr.client_id')
            ->join('error_types et', 'et.id = ae.error_type_id')
            ->join('report_accounts ra', 'ra.id = ae.account_id')
            ->join('credit_reports cr', 'cr.id = ra.report_id')
            ->where('cr.client_id', $clientId)
            ->orderBy('ae.identified_date DESC')
            ->findAll();
    }

    /**
     * Get all open errors
     * 
     * @return array Array of open errors
     */
    public function getOpenErrors()
    {
        return $this->whereNotIn('status', ['closed', 'rejected'])->findAll();
    }

    /**
     * Get error statistics
     * 
     * @return array Statistics array
     */
    public function getStatistics()
    {
        $db = \Config\Database::connect();
        
        return [
            'total'    => $this->countAll(),
            'open'     => $this->whereNotIn('status', ['closed', 'rejected'])->countAllResults(),
            'resolved' => $this->where('status', 'corrected')->countAllResults(),
            'disputed' => $this->where('status', 'disputed')->countAllResults(),
        ];
    }

    /**
     * Get errors by error type
     * 
     * @param int $errorTypeId The error type ID
     * @return array Array of errors
     */
    public function getByErrorType($errorTypeId)
    {
        return $this->where('error_type_id', $errorTypeId)->findAll();
    }
}
