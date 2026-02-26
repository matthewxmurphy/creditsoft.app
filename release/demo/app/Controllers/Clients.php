<?php

namespace App\Controllers;

use App\Models\ClientModel;
use App\Models\UserModel;

/**
 * Clients Controller - Handle client management
 * 
 * Purpose: CRUD operations for clients
 * 
 * @method index()     - List all clients
 * @method create()    - Show create form
 * @method store()     - Store new client
 * @method view()      - View client details
 * @method edit()      - Show edit form
 * @method update()    - Update client
 * @method delete()    - Delete client
 * @method search()    - Search clients (AJAX)
 */
class Clients extends BaseController
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
     * List all clients
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function index()
    {
        $clientModel = new ClientModel();
        
        $status = $this->request->getGet('status');
        $assigned = $this->request->getGet('assigned');
        
        $clientModel->select('*');
        
        if ($status) {
            $clientModel->where('status', $status);
        }
        
        if ($assigned) {
            $clientModel->where('assigned_to', $assigned);
        }
        
        $clients = $clientModel->orderBy('created_at', 'DESC')->findAll();
        
        $data = [
            'title' => 'Clients',
            'subtitle' => 'Manage your customer relationships',
            'clients' => $clients,
            'status_filter' => $status,
            'assigned_filter' => $assigned,
            'users' => (new UserModel())->getActive(),
        ];
        
        return $this->render('clients/index', $data);
    }

    /**
     * Show create client form
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function create()
    {
        $data = [
            'title' => 'New Client',
            'users' => (new UserModel())->getActive(),
        ];
        
        return view('clients/form', $data);
    }

    /**
     * Store new client
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function store()
    {
        $clientModel = new ClientModel();
        
        // Validation rules
        $rules = [
            'first_name' => 'required|max_length[50]',
            'last_name'  => 'required|max_length[50]',
            'email'      => 'valid_email',
            'status'     => 'in_list[active,inactive,prospect,closed]',
        ];
        
        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }
        
        // Generate client code
        $clientCode = $clientModel->generateClientCode();
        
        // Prepare data
        $data = [
            'client_code'  => $clientCode,
            'first_name'   => $this->request->getPost('first_name'),
            'last_name'    => $this->request->getPost('last_name'),
            'email'        => $this->request->getPost('email'),
            'phone'        => $this->request->getPost('phone'),
            'phone_alt'    => $this->request->getPost('phone_alt'),
            'address'      => $this->request->getPost('address'),
            'city'         => $this->request->getPost('city'),
            'state'        => $this->request->getPost('state'),
            'zip'          => $this->request->getPost('zip'),
            'employer'     => $this->request->getPost('employer'),
            'employer_phone' => $this->request->getPost('employer_phone'),
            'annual_income' => $this->request->getPost('annual_income'),
            'referred_by'  => $this->request->getPost('referred_by'),
            'status'       => $this->request->getPost('status') ?? 'prospect',
            'assigned_to'  => $this->request->getPost('assigned_to'),
            'notes'        => $this->request->getPost('notes'),
            'created_by'   => session()->get('user_id'),
        ];
        
        $clientId = $clientModel->insert($data);
        
        if (!$clientId) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create client. Please try again.');
        }
        
        // Log activity
        $this->logActivity('client_created', 'client', $clientId, 'New client created: ' . $data['first_name'] . ' ' . $data['last_name']);
        
        return redirect()->to('/clients/' . $clientId)
            ->with('success', 'Client created successfully!');
    }

    /**
     * View client details
     * 
     * @param int $id Client ID
     * @return \CodeIgniter\HTTP\Response
     */
    public function view($id)
    {
        $clientModel = new ClientModel();
        $client = $clientModel->getWithRelations($id);
        
        if (!$client) {
            return redirect()->to('/clients')
                ->with('error', 'Client not found.');
        }
        
        $data = [
            'title' => $client['first_name'] . ' ' . $client['last_name'],
            'subtitle' => 'Client Details - ' . $client['client_code'],
            'client' => $client,
        ];
        
        return view('clients/view', $data);
    }

    /**
     * Show edit client form
     * 
     * @param int $id Client ID
     * @return \CodeIgniter\HTTP\Response
     */
    public function edit($id)
    {
        $clientModel = new ClientModel();
        $client = $clientModel->find($id);
        
        if (!$client) {
            return redirect()->to('/clients')
                ->with('error', 'Client not found.');
        }
        
        $data = [
            'title' => 'Edit Client',
            'client' => $client,
            'users' => (new UserModel())->getActive(),
        ];
        
        return view('clients/form', $data);
    }

    /**
     * Update client
     * 
     * @param int $id Client ID
     * @return \CodeIgniter\HTTP\Response
     */
    public function update($id)
    {
        $clientModel = new ClientModel();
        $client = $clientModel->find($id);
        
        if (!$client) {
            return redirect()->to('/clients')
                ->with('error', 'Client not found.');
        }
        
        // Validation rules
        $rules = [
            'first_name' => 'required|max_length[50]',
            'last_name'  => 'required|max_length[50]',
            'email'      => 'valid_email',
            'status'     => 'in_list[active,inactive,prospect,closed]',
        ];
        
        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }
        
        // Prepare data
        $data = [
            'first_name'   => $this->request->getPost('first_name'),
            'last_name'    => $this->request->getPost('last_name'),
            'email'        => $this->request->getPost('email'),
            'phone'        => $this->request->getPost('phone'),
            'phone_alt'    => $this->request->getPost('phone_alt'),
            'address'      => $this->request->getPost('address'),
            'city'         => $this->request->getPost('city'),
            'state'        => $this->request->getPost('state'),
            'zip'          => $this->request->getPost('zip'),
            'employer'     => $this->request->getPost('employer'),
            'employer_phone' => $this->request->getPost('employer_phone'),
            'annual_income' => $this->request->getPost('annual_income'),
            'referred_by'  => $this->request->getPost('referred_by'),
            'status'       => $this->request->getPost('status'),
            'assigned_to'  => $this->request->getPost('assigned_to'),
            'notes'        => $this->request->getPost('notes'),
        ];
        
        $clientModel->update($id, $data);
        
        // Log activity
        $this->logActivity('client_updated', 'client', $id, 'Client updated: ' . $data['first_name'] . ' ' . $data['last_name']);
        
        return redirect()->to('/clients/' . $id)
            ->with('success', 'Client updated successfully!');
    }

    /**
     * Delete client
     * 
     * @param int $id Client ID
     * @return \CodeIgniter\HTTP\Response
     */
    public function delete($id)
    {
        $clientModel = new ClientModel();
        $client = $clientModel->find($id);
        
        if (!$client) {
            return redirect()->to('/clients')
                ->with('error', 'Client not found.');
        }
        
        // Check for related data
        $db = \Config\Database::connect();
        $reportCount = $db->table('credit_reports')->where('client_id', $id)->countAllResults();
        
        if ($reportCount > 0) {
            return redirect()->to('/clients/' . $id)
                ->with('error', 'Cannot delete client with existing credit reports. Please delete reports first.');
        }
        
        $clientModel->delete($id);
        
        // Log activity
        $this->logActivity('client_deleted', 'client', $id, 'Client deleted: ' . $client['first_name'] . ' ' . $client['last_name']);
        
        return redirect()->to('/clients')
            ->with('success', 'Client deleted successfully!');
    }

    /**
     * Search clients (AJAX)
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function search()
    {
        $query = $this->request->getGet('q');
        
        if (strlen($query) < 2) {
            return $this->response->setJSON([]);
        }
        
        $clientModel = new ClientModel();
        $results = $clientModel->search($query);
        
        // Limit to 10 results
        $results = array_slice($results, 0, 10);
        
        return $this->response->setJSON($results);
    }
}
