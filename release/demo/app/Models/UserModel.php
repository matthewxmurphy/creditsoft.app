<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * UserModel - Handle user database operations
 * 
 * Purpose: CRUD operations for users table
 * 
 * @method find($id)              - Find user by ID
 * @method findAll()               - Get all users
 * @method insert($data)           - Create new user
 * @method update($id, $data)      - Update user
 * @method delete($id)             - Delete user
 * @method where($field, $value)   - Query builder where clause
 */
class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $allowedFields    = [
        'username',
        'email',
        'password_hash',
        'role',
        'first_name',
        'last_name',
        'avatar',
        'is_active',
        'last_login',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $validationRules = [
        'username'  => 'required|min_length[3]|max_length[50]|is_unique[users.username,id,{id}]',
        'email'     => 'required|valid_email|is_unique[users.email,id,{id}]',
        'role'      => 'required|in_list[admin,manager,staff]',
    ];

    /**
     * Get users by role
     * 
     * @param string $role The role to filter by
     * @return array Array of users
     */
    public function getByRole($role)
    {
        return $this->where('role', $role)->findAll();
    }

    /**
     * Get active users only
     * 
     * @return array Array of active users
     */
    public function getActive()
    {
        return $this->where('is_active', 1)->findAll();
    }

    /**
     * Verify user credentials
     * 
     * @param string $email    User email
     * @param string $password Plain text password
     * @return array|false User data if valid, false otherwise
     */
    public function verifyCredentials($email, $password)
    {
        $user = $this->where('email', $email)->first();
        
        if (!$user) {
            return false;
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        return $user;
    }
}
