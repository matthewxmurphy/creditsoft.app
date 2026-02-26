<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\Controller;

/**
 * Auth Controller - Handle user authentication
 * 
 * Purpose: Login, logout, registration functionality
 * 
 * @method login()         - Display login form
 * @method doLogin()       - Process login attempt
 * @method logout()        - End user session
 * @method register()      - Display registration form
 * @method doRegister()    - Process new user registration
 */
class Auth extends Controller
{
    public function index()
    {
        return $this->login();
    }
    
    /**
     * Display the login form
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function login()
    {
        // AUTO-LOGIN AS ADMIN FOR DEMO
        session()->set([
            'user_id' => 1,
            'email' => 'admin@credit.com',
            'name' => 'Admin',
            'role' => 'admin'
        ]);
        return redirect()->to('/dashboard');
        
        // If already logged in, redirect to dashboard
        if (session()->has('user_id')) {
            return redirect()->to('/dashboard');
        }

        $data = [
            'title' => 'Login - Credit Error Identifier System',
        ];
        
        return view('auth/login', $data);
    }

    /**
     * Process the login form submission
     * Validates credentials and creates session
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function doLogin()
    {
        // Validate input
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required|min_length[6]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        // Attempt to find user
        $userModel = new UserModel();
        $user = $userModel->where('email', $email)->first();

        // Verify user exists and password matches
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid email or password.');
        }

        // Check if user is active
        if (!$user['is_active']) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Your account has been deactivated. Please contact administrator.');
        }

        // Create session data
        $sessionData = [
            'user_id'     => $user['id'],
            'username'    => $user['username'],
            'email'       => $user['email'],
            'role'        => $user['role'],
            'first_name'  => $user['first_name'],
            'last_name'   => $user['last_name'],
            'full_name'   => $user['first_name'] . ' ' . $user['last_name'],
            'logged_in'   => true,
            'last_activity' => time(),
        ];

        session()->set($sessionData);

        // Update last login timestamp
        $userModel->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);

        // Log activity
        $this->logActivity($user['id'], 'login', 'user', $user['id'], 'User logged in');

        // Redirect to intended URL or dashboard
        $redirectUrl = session()->get('redirect_url') ?? '/dashboard';
        session()->remove('redirect_url');

        return redirect()->to($redirectUrl)
            ->with('success', 'Welcome back, ' . $user['first_name'] . '!');
    }

    /**
     * End the user session and redirect to login
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function logout()
    {
        $userId = session()->get('user_id');
        
        // Log activity before destroying session
        if ($userId) {
            $this->logActivity($userId, 'logout', 'user', $userId, 'User logged out');
        }

        // Destroy session
        session()->destroy();

        return redirect()->to('/login')
            ->with('success', 'You have been logged out successfully.');
    }

    /**
     * Display the registration form (if enabled)
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function register()
    {
        // If already logged in, redirect to dashboard
        if (session()->has('user_id')) {
            return redirect()->to('/dashboard');
        }

        // Check if registration is enabled
        $settingsModel = new \App\Models\SettingsModel();
        if (!$settingsModel->get('allow_registration', false)) {
            return redirect()->to('/login')
                ->with('error', 'Registration is currently disabled.');
        }

        $data = [
            'title' => 'Register - Credit Error Identifier System',
        ];
        
        return view('auth/register', $data);
    }

    /**
     * Process the registration form submission
     * Creates new user account
     * 
     * @return \CodeIgniter\HTTP\Response
     */
    public function doRegister()
    {
        // Validate input
        $rules = [
            'username'      => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'email'        => 'required|valid_email|is_unique[users.email]',
            'password'     => 'required|min_length[8]',
            'confirm_password' => 'required|matches[password]',
            'first_name'   => 'required|max_length[50]',
            'last_name'    => 'required|max_length[50]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $userModel = new UserModel();

        // Create new user (default role is staff)
        $userData = [
            'username'     => $this->request->getPost('username'),
            'email'        => $this->request->getPost('email'),
            'password_hash' => password_hash($this->request->getPost('password'), PASSWORD_BCRYPT),
            'role'         => 'staff',
            'first_name'   => $this->request->getPost('first_name'),
            'last_name'    => $this->request->getPost('last_name'),
            'is_active'    => 1,
        ];

        $userId = $userModel->insert($userData);

        if (!$userId) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Registration failed. Please try again.');
        }

        // Log activity
        $this->logActivity($userId, 'register', 'user', $userId, 'New user registered');

        return redirect()->to('/login')
            ->with('success', 'Registration successful! Please log in.');
    }

    /**
     * Log user activity to database
     * 
     * @param int    $userId      The user ID
     * @param string $action      The action performed
     * @param string $entityType  Type of entity affected
     * @param int    $entityId    ID of the entity
     * @param string $description Description of the action
     * @return void
     */
    private function logActivity($userId, $action, $entityType, $entityId, $description)
    {
        $db = \Config\Database::connect();
        
        $activityData = [
            'user_id'      => $userId,
            'action'       => $action,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'description'  => $description,
            'ip_address'   => $this->request->getIPAddress(),
            'user_agent'   => $this->request->getUserAgent()->getAgentString(),
            'created_at'   => date('Y-m-d H:i:s'),
        ];
        
        $db->table('activity_log')->insert($activityData);
    }
}
