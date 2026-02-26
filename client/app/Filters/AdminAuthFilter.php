<?php

namespace App\Filters;

use CodeIgniter\Filters\BaseFilter;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AdminAuthFilter - Ensures user is logged in before accessing protected routes
 * 
 * This filter checks for a valid session and redirects to login if not authenticated.
 * 
 * Usage:
 *   Add 'adminauth' to the $filters array in Config/Filters.php
 *   Apply to routes in Config/Routes.php using $routes->filter('adminauth')
 */
class AdminAuthFilter extends BaseFilter
{
    /**
     * Process the request before it reaches the controller
     * 
     * @param RequestInterface $request The incoming request
     * @return mixed Either the request proceeds or redirects to login
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Check if user session exists
        if (!session()->has('user_id')) {
            // Store intended URL for redirect after login
            $uri = uri_string();
            if (!empty($uri) && !str_starts_with($uri, 'api/')) {
                session()->set('redirect_url', $uri);
            }
            
            // Redirect to login page
            return redirect()->to('/login')
                ->with('error', 'Please log in to access this area.');
        }

        // Optional: Check user role if arguments provided
        if ($arguments && !empty($arguments)) {
            $userRole = session()->get('role');
            if (!in_array($userRole, $arguments)) {
                return redirect()->to('/dashboard')
                    ->with('error', 'You do not have permission to access this area.');
            }
        }

        // Update last activity timestamp
        session()->set('last_activity', time());
        
        return $request;
    }

    /**
     * Process the response after the controller executes
     * 
     * @param RequestInterface $request The incoming request
     * @param ResponseInterface $response The outgoing response
     * @return ResponseInterface The modified response
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Add security headers
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
        
        return $response;
    }
}
