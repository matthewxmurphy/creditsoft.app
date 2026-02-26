<?php

namespace App\Services;

/**
 * AiService - OpenCode.ai integration for AI features
 * 
 * Purpose: Handle all AI-powered features including:
 * - Chatbot for admin
 * - Explain credit changes to clients
 * - Generate CRM notes
 * - Generate drip email content
 * - Auto-generate SOP content
 * 
 * This service wraps the opencode.ai API to provide AI capabilities.
 */
class AiService
{
    /**
     * @var string API endpoint
     */
    private $apiEndpoint = 'https://opencode.ai/api';
    
    /**
     * @var string|null API key
     */
    private $apiKey = null;
    
    /**
     * @var string Model to use
     */
    private $model = 'default';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        // Load API key from settings
        $settingsModel = new \App\Models\SettingsModel();
        $this->apiKey = $settingsModel->get('ai_api_key', '');
        $this->model = $settingsModel->get('ai_model', 'default');
    }

    /**
     * Check if AI is configured
     * 
     * @return bool
     */
    public function isConfigured()
    {
        return !empty($this->apiKey);
    }

    /**
     * Send a chat message to the AI
     * 
     * @param string $message User message
     * @param array  $context Additional context (client data, etc.)
     * @return array Response data
     */
    public function chat($message, $context = [])
    {
        $systemPrompt = $this->buildSystemPrompt($context);
        
        return $this->callApi('chat', [
            'message' => $message,
            'system'  => $systemPrompt,
            'model'   => $this->model,
        ]);
    }

    /**
     * Explain credit changes to client
     * 
     * @param array $fromData Data from previous month
     * @param array $toData   Data from current month
     * @return string Explanation text
     */
    public function explainChanges($fromData, $toData)
    {
        $prompt = $this->buildExplainPrompt($fromData, $toData);
        
        $response = $this->callApi('generate', [
            'prompt' => $prompt,
            'model'  => $this->model,
            'max_tokens' => 500,
        ]);
        
        return $response['content'] ?? 'Unable to generate explanation.';
    }

    /**
     * Generate a CRM note
     * 
     * @param string $type      Note type (general, call, email, meeting)
     * @param array  $context   Client and report context
     * @return string Generated note
     */
    public function generateNote($type, $context)
    {
        $prompt = "Generate a professional CRM note for a {$type}. ";
        $prompt .= "Include relevant details about the client's credit status. ";
        
        if (!empty($context['client_name'])) {
            $prompt .= "Client: {$context['client_name']}. ";
        }
        
        if (!empty($context['score_change'])) {
            $prompt .= "Score changed by {$context['score_change']} points. ";
        }
        
        if (!empty($context['errors_found'])) {
            $prompt .= "{$context['errors_found']} errors were identified. ";
        }
        
        $response = $this->callApi('generate', [
            'prompt' => $prompt,
            'model'  => $this->model,
            'max_tokens' => 300,
        ]);
        
        return $response['content'] ?? '';
    }

    /**
     * Generate drip email content
     * 
     *type @param string $      Email type (alert, error_found, score_improved, etc.)
     * @param array  $context   Email context
     * @return array Subject and body
     */
    public function generateDripEmail($type, $context)
    {
        $prompt = "Generate a professional email for a credit dispute service. ";
        
        switch ($type) {
            case 'report_imported':
                $prompt .= "This email notifies the client that their new credit report has been imported and is ready for review.";
                break;
            case 'error_found':
                $prompt .= "This email informs the client that errors have been found on their credit report and explains the next steps.";
                break;
            case 'score_improved':
                $prompt .= "This email celebrates a credit score improvement with the client.";
                break;
            case 'dispute_filed':
                $prompt .= "This email updates the client that a dispute has been filed on their behalf.";
                break;
            default:
                $prompt .= "This is a general update email for the client.";
        }
        
        if (!empty($context['client_name'])) {
            $prompt .= " Client name: {$context['client_name']}. ";
        }
        
        if (!empty($context['details'])) {
            $prompt .= " Additional details: {$context['details']}. ";
        }
        
        $prompt .= " Include a subject line and professional email body.";
        
        $response = $this->callApi('generate', [
            'prompt' => $prompt,
            'model'  => $this->model,
            'max_tokens' => 600,
        ]);
        
        $content = $response['content'] ?? '';
        
        // Parse subject and body
        $lines = explode("\n", trim($content));
        $subject = trim($lines[0] ?? 'Credit Report Update');
        array_shift($lines);
        $body = trim(implode("\n", $lines));
        
        return [
            'subject' => $subject,
            'body'    => $body,
        ];
    }

    /**
     * Generate SOP content
     * 
     * @param string $sopTitle SOP title
     * @param array  $context  Context data
     * @return string Generated SOP content
     */
    public function generateSop($sopTitle, $context)
    {
        $prompt = "Generate a detailed Standard Operating Procedure (SOP) for: {$sopTitle}. ";
        
        if (!empty($context['category'])) {
            $prompt .= "Category: {$context['category']}. ";
        }
        
        $prompt .= "Include step-by-step instructions, best practices, and any relevant forms or checklists. ";
        $prompt .= "Format as a professional SOP document.";
        
        $response = $this->callApi('generate', [
            'prompt' => $prompt,
            'model'  => $this->model,
            'max_tokens' => 1000,
        ]);
        
        return $response['content'] ?? '';
    }

    /**
     * Call the AI API
     * 
     * @param string $endpoint API endpoint
     * @param array  $data     Request data
     * @return array Response data
     */
    private function callApi($endpoint, $data)
    {
        // If no API key, return mock response for development
        if (empty($this->apiKey)) {
            return [
                'content' => 'AI is not configured. Please add your API key in Settings.',
                'success' => false,
            ];
        }
        
        $client = \Config\Database::connect();
        
        try {
            $ch = curl_init($this->apiEndpoint . '/' . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $result = json_decode($response, true);
                
                // Log the interaction
                $this->logInteraction('chatbot', $data['message'] ?? '', $result);
                
                return [
                    'content' => $result['content'] ?? $result['response'] ?? '',
                    'success' => true,
                ];
            }
            
            return [
                'content' => 'API request failed.',
                'success' => false,
                'error'   => 'HTTP ' . $httpCode,
            ];
            
        } catch (\Exception $e) {
            return [
                'content' => 'Error calling AI API: ' . $e->getMessage(),
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Build system prompt for chatbot
     * 
     * @param array $context Context data
     * @return string System prompt
     */
    private function buildSystemPrompt($context = [])
    {
        $system = "You are an AI assistant for a Credit Error Identifier System. ";
        $system .= "You help administrators manage credit reports, identify errors, ";
        $system .= "generate dispute letters, and create CRM notes. ";
        
        if (!empty($context['user_role'])) {
            $system .= "The current user is a {$context['user_role']}. ";
        }
        
        $system .= "Be helpful, professional, and concise. ";
        
        return $system;
    }

    /**
     * Build prompt for explaining changes
     * 
     * @param array $fromData Previous month data
     * @param array $toData   Current month data
     * @return string Prompt
     */
    private function buildExplainPrompt($fromData, $toData)
    {
        $prompt = "Explain the changes in this credit report to the client in a clear, ";
        $prompt .= "professional manner. Focus on:\n\n";
        
        if (isset($fromData['score']) && isset($toData['score'])) {
            $change = $toData['score'] - $fromData['score'];
            $direction = $change >= 0 ? 'increased' : 'decreased';
            $prompt .= "1. Score {$direction} from {$fromData['score']} to {$toData['score']} ({$change} points)\n";
        }
        
        if (isset($fromData['balance']) && isset($toData['balance'])) {
            $balanceChange = $toData['balance'] - $fromData['balance'];
            $prompt .= "2. Balance changed from $" . number_format($fromData['balance'], 2);
            $prompt .= " to $" . number_format($toData['balance'], 2);
            $prompt .= " (" . ($balanceChange >= 0 ? '+' : '') . "$" . number_format($balanceChange, 2) . ")\n";
        }
        
        $prompt .= "\nProvide a summary that helps the client understand their credit situation.";
        
        return $prompt;
    }

    /**
     * Log AI interaction to database
     * 
     * @param string $type     Interaction type
     * @param string $prompt   User prompt
     * @param array  $response API response
     * @return void
     */
    private function logInteraction($type, $prompt, $response)
    {
        $db = \Config\Database::connect();
        
        $db->table('ai_interactions')->insert([
            'user_id'      => session()->get('user_id') ?? null,
            'client_id'    => null,
            'type'         => $type,
            'prompt'       => $prompt,
            'response'     => json_encode($response),
            'model'        => $this->model,
            'tokens_used'  => $response['tokens_used'] ?? null,
            'status'       => 'success',
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }
}
