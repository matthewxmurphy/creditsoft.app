<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * ClientNoteModel - Handle client CRM notes
 * 
 * Purpose: CRUD operations for client_notes table
 */
class ClientNoteModel extends Model
{
    protected $table            = 'client_notes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'client_id',
        'user_id',
        'month',
        'year',
        'title',
        'content',
        'note_type',
        'is_private',
        'ai_generated',
        'ai_prompt',
        'attachments',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get notes for a client
     * 
     * @param int $clientId The client ID
     * @return array Array of notes
     */
    public function getByClient($clientId)
    {
        return $this->where('client_id', $clientId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get notes for a client by month/year
     * 
     * @param int $clientId The client ID
     * @param int $month    The month
     * @param int $year     The year
     * @return array Array of notes
     */
    public function getByPeriod($clientId, $month, $year)
    {
        return $this->where('client_id', $clientId)
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get notes by type
     * 
     * @param string $type The note type
     * @return array Array of notes
     */
    public function getByType($type)
    {
        return $this->where('note_type', $type)->findAll();
    }

    /**
     * Get AI-generated notes
     * 
     * @return array Array of AI notes
     */
    public function getAiGenerated()
    {
        return $this->where('ai_generated', 1)->findAll();
    }
}

/**
 * SettingsModel - Handle system settings
 * 
 * Purpose: CRUD operations for settings table
 */
class SettingsModel extends Model
{
    protected $table            = 'settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'setting_key',
        'setting_value',
        'setting_type',
        'description',
        'is_public',
    ];
    protected $useTimestamps = false;

    /**
     * Get a setting value
     * 
     * @param string $key     The setting key
     * @param mixed  $default Default value if not found
     * @return mixed The setting value
     */
    public function get($key, $default = null)
    {
        $setting = $this->where('setting_key', $key)->first();
        
        if (!$setting) {
            return $default;
        }
        
        return $setting['setting_value'];
    }

    /**
     * Set a setting value
     * 
     * @param string $key   The setting key
     * @param mixed  $value The setting value
     * @return bool Success status
     */
    public function set($key, $value)
    {
        $existing = $this->where('setting_key', $key)->first();
        
        if ($existing) {
            return $this->update($existing['id'], [
                'setting_value' => $value,
            ]);
        }
        
        return $this->insert([
            'setting_key'   => $key,
            'setting_value' => $value,
        ]);
    }

    /**
     * Get all public settings
     * 
     * @return array Array of public settings
     */
    public function getPublicSettings()
    {
        return $this->where('is_public', 1)->findAll();
    }

    /**
     * Get settings by type
     * 
     * @param string $type The setting type
     * @return array Array of settings
     */
    public function getByType($type)
    {
        return $this->where('setting_type', $type)->findAll();
    }
}

/**
 * ComparisonModel - Handle month-over-month comparisons
 * 
 * Purpose: Store and retrieve comparison snapshots
 */
class ComparisonModel extends Model
{
    protected $table            = 'comparisons';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'client_id',
        'month_from',
        'year_from',
        'month_to',
        'year_to',
        'score_changes',
        'balance_changes',
        'account_changes',
        'error_changes',
        'summary',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';

    /**
     * Get comparisons for a client
     * 
     * @param int $clientId The client ID
     * @return array Array of comparisons
     */
    public function getByClient($clientId)
    {
        return $this->where('client_id', $clientId)
            ->orderBy('year_to DESC, month_to DESC')
            ->findAll();
    }

    /**
     * Get a specific comparison
     * 
     * @param int $clientId   The client ID
     * @param int $monthFrom  From month
     * @param int $yearFrom   From year
     * @param int $monthTo    To month
     * @param int $yearTo     To year
     * @return array|null Comparison data or null
     */
    public function getComparison($clientId, $monthFrom, $yearFrom, $monthTo, $yearTo)
    {
        return $this->where('client_id', $clientId)
            ->where('month_from', $monthFrom)
            ->where('year_from', $yearFrom)
            ->where('month_to', $monthTo)
            ->where('year_to', $yearTo)
            ->first();
    }

    /**
     * Get latest comparison for a client
     * 
     * @param int $clientId The client ID
     * @return array|null Comparison data or null
     */
    public function getLatest($clientId)
    {
        return $this->where('client_id', $clientId)
            ->orderBy('year_to DESC, month_to DESC')
            ->first();
    }
}

/**
 * DisputeTemplateModel - Handle dispute letter templates
 * 
 * Purpose: CRUD operations for dispute_templates table
 */
class DisputeTemplateModel extends Model
{
    protected $table            = 'dispute_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'type',
        'name',
        'description',
        'content_template',
        'variables',
        'is_active',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get templates by type
     * 
     * @param string $type The template type (consumer, bureau, creditor)
     * @return array Array of templates
     */
    public function getByType($type)
    {
        return $this->where('type', $type)
            ->where('is_active', 1)
            ->findAll();
    }

    /**
     * Get active templates
     * 
     * @return array Array of active templates
     */
    public function getActive()
    {
        return $this->where('is_active', 1)->findAll();
    }

    /**
     * Parse template with variables
     * 
     * @param int   $templateId The template ID
     * @param array $data        Variable data
     * @return string Parsed content
     */
    public function parseTemplate($templateId, $data)
    {
        $template = $this->find($templateId);
        
        if (!$template) {
            return '';
        }
        
        $content = $template['content_template'];
        
        foreach ($data as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        return $content;
    }
}

/**
 * DisputeLetterModel - Handle generated dispute letters
 * 
 * Purpose: CRUD operations for dispute_letters table
 */
class DisputeLetterModel extends Model
{
    protected $table            = 'dispute_letters';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'client_id',
        'template_id',
        'account_error_id',
        'account_id',
        'letter_type',
        'generated_pdf',
        'pdf_path',
        'content_generated',
        'status',
        'delivery_method',
        'sent_to_client_at',
        'mailed_at',
        'notes',
        'created_by',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get letters for a client
     * 
     * @param int $clientId The client ID
     * @return array Array of letters
     */
    public function getByClient($clientId)
    {
        return $this->where('client_id', $clientId)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get letters by status
     * 
     * @param string $status The status
     * @return array Array of letters
     */
    public function getByStatus($status)
    {
        return $this->where('status', $status)->findAll();
    }

    /**
     * Get letter with template
     * 
     * @param int $id The letter ID
     * @return array Letter with template data
     */
    public function getWithTemplate($id)
    {
        $db = \Config\Database::connect();
        
        return $db->table('dispute_letters dl')
            ->select('dl.*, dt.name as template_name, dt.type as template_type')
            ->join('dispute_templates dt', 'dt.id = dl.template_id')
            ->where('dl.id', $id)
            ->first();
    }
}
