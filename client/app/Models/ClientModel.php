<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ClientModel - Handle client database operations
 * 
 * Purpose: CRUD operations for clients table with Metro2 field mapping
 * 
 * @method find($id)              - Find client by ID
 * @method findAll()             - Get all clients
 * @method insert($data)         - Create new client
 * @method update($id, $data)    - Update client
 * @method delete($id)           - Delete client
 */
class ClientModel extends Model
{
    protected $table            = 'clients';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'client_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_alt',
        'ssn',
        'date_of_birth',
        'address',
        'city',
        'state',
        'zip',
        'employer',
        'employer_phone',
        'annual_income',
        'referred_by',
        'status',
        'assigned_to',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Generate unique client code
     * 
     * @return string New client code (e.g., CLI-00001)
     */
    public function generateClientCode()
    {
        $lastClient = $this->orderBy('id', 'DESC')->first();
        
        if ($lastClient && isset($lastClient['client_code'])) {
            $lastNumber = (int)substr($lastClient['client_code'], 4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return 'CLI-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Get clients by status
     * 
     * @param string $status The status to filter by
     * @return array Array of clients
     */
    public function getByStatus($status)
    {
        return $this->where('status', $status)->findAll();
    }

    /**
     * Get clients assigned to a user
     * 
     * @param int $userId The user ID
     * @return array Array of clients
     */
    public function getByAssignedUser($userId)
    {
        return $this->where('assigned_to', $userId)->findAll();
    }

    /**
     * Get active clients with their latest report scores
     * 
     * @return array Array of clients with scores
     */
    public function getActiveWithScores()
    {
        $db = \Config\Database::connect();
        
        $builder = $db->table('client_summary');
        return $builder->where('status', 'active')->findAll();
    }

    /**
     * Search clients by name, email, or client code
     * 
     * @param string $search The search term
     * @return array Array of matching clients
     */
    public function search($search)
    {
        $searchTerm = '%' . $search . '%';
        
        return $this->groupStart()
            ->like('first_name', $search)
            ->orLike('last_name', $search)
            ->orLike('email', $search)
            ->orLike('client_code', $search)
            ->groupEnd()
            ->findAll();
    }

    /**
     * Get client with all related data
     * 
     * @param int $id The client ID
     * @return array Client data with reports and errors
     */
    public function getWithRelations($id)
    {
        $client = $this->find($id);
        
        if (!$client) {
            return null;
        }

        $db = \Config\Database::connect();
        
        // Get credit reports
        $client['reports'] = $db->table('credit_reports')
            ->where('client_id', $id)
            ->orderBy('report_date', 'DESC')
            ->findAll();
        
        // Get open errors count
        $client['open_errors'] = $db->table('account_errors ae')
            ->join('report_accounts ra', 'ra.id = ae.account_id')
            ->join('credit_reports cr', 'cr.id = ra.report_id')
            ->where('cr.client_id', $id)
            ->where('ae.status NOT IN ("closed", "rejected")')
            ->countAllResults();
        
        // Get recent notes
        $client['notes'] = $db->table('client_notes')
            ->where('client_id', $id)
            ->orderBy('created_at', 'DESC')
            ->limit(5)
            ->findAll();
        
        return $client;
    }

    /**
     * Get client statistics
     * 
 array Statistics array     * @return
     */
    public function getStatistics()
    {
        $db = \Config\Database::connect();
        
        return [
            'total'        => $this->countAll(),
            'active'       => $this->where('status', 'active')->countAllResults(),
            'prospect'     => $this->where('status', 'prospect')->countAllResults(),
            'inactive'     => $this->where('status', 'inactive')->countAllResults(),
            'this_month'   => $this->where('created_at >=', date('Y-m-01'))->countAllResults(),
        ];
    }
}
