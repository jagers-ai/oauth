<?php

declare(strict_types=1);

namespace HolyOAuth\Storage;

/**
 * Session-based storage implementation for OAuth state and user data
 * This is the MVP implementation - suitable for single-server applications
 */
class SessionStorage implements StorageInterface
{
    /**
     * Session key prefix to avoid conflicts
     */
    private string $prefix = 'holy_oauth_';
    
    /**
     * Ensure session is started
     */
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Save state token with associated data
     */
    public function saveState(string $state, array $data): bool
    {
        $key = $this->prefix . 'state_' . $state;
        $_SESSION[$key] = $data;
        return true;
    }
    
    /**
     * Verify and retrieve state data
     */
    public function verifyState(string $state): ?array
    {
        $key = $this->prefix . 'state_' . $state;
        
        if (!isset($_SESSION[$key])) {
            return null;
        }
        
        $data = $_SESSION[$key];
        
        // Check expiration
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            unset($_SESSION[$key]);
            return null;
        }
        
        return $data;
    }
    
    /**
     * Delete a state token
     */
    public function deleteState(string $state): bool
    {
        $key = $this->prefix . 'state_' . $state;
        unset($_SESSION[$key]);
        return true;
    }
    
    /**
     * Find or create a user from OAuth provider data
     */
    public function findOrCreateUser(string $provider, array $userData): array
    {
        $providerId = $userData['provider_id'];
        
        // Try to find existing user by social account
        $user = $this->findUserBySocialAccount($provider, $providerId);
        
        if ($user !== null) {
            // Update last login
            $this->updateLastLogin($user['id'], $provider);
            return $user;
        }
        
        // Try to find user by email
        if (!empty($userData['email'])) {
            $user = $this->findUserByEmail($userData['email']);
            if ($user !== null) {
                // Link the social account
                $this->linkSocialAccount($user['id'], $provider, $userData);
                return $user;
            }
        }
        
        // Create new user
        return $this->createUser($userData);
    }
    
    /**
     * Find a user by their social account
     */
    public function findUserBySocialAccount(string $provider, string $providerId): ?array
    {
        $socialKey = $this->prefix . 'social_' . $provider . '_' . $providerId;
        
        if (!isset($_SESSION[$socialKey])) {
            return null;
        }
        
        $userId = $_SESSION[$socialKey];
        return $this->getUserById($userId);
    }
    
    /**
     * Create a new user
     */
    public function createUser(array $userData): array
    {
        // Generate unique user ID
        $userId = uniqid('user_', true);
        
        // Create user data
        $user = [
            'id' => $userId,
            'email' => $userData['email'] ?? null,
            'name' => $userData['name'] ?? null,
            'picture' => $userData['picture'] ?? null,
            'provider' => $userData['provider'],
            'provider_id' => $userData['provider_id'],
            'created_at' => time(),
            'updated_at' => time()
        ];
        
        // Save user data
        $userKey = $this->prefix . 'user_' . $userId;
        $_SESSION[$userKey] = $user;
        
        // Save email index if available
        if (!empty($user['email'])) {
            $emailKey = $this->prefix . 'email_' . $user['email'];
            $_SESSION[$emailKey] = $userId;
        }
        
        // Save social account mapping
        $this->linkSocialAccount($userId, $userData['provider'], $userData);
        
        // Store current user ID
        $_SESSION[$this->prefix . 'current_user_id'] = $userId;
        
        return $user;
    }
    
    /**
     * Link a social account to an existing user
     */
    public function linkSocialAccount($userId, string $provider, array $providerData): bool
    {
        $socialKey = $this->prefix . 'social_' . $provider . '_' . $providerData['provider_id'];
        $_SESSION[$socialKey] = $userId;
        
        // Update user's social accounts list
        $accountsKey = $this->prefix . 'user_social_' . $userId;
        if (!isset($_SESSION[$accountsKey])) {
            $_SESSION[$accountsKey] = [];
        }
        
        $_SESSION[$accountsKey][$provider] = [
            'provider_id' => $providerData['provider_id'],
            'linked_at' => time(),
            'data' => $providerData['raw_data'] ?? []
        ];
        
        return true;
    }
    
    /**
     * Clean up expired state tokens
     */
    public function cleanupExpiredStates(): int
    {
        $count = 0;
        $prefix = $this->prefix . 'state_';
        
        foreach ($_SESSION as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                if (is_array($value) && isset($value['expires_at']) && $value['expires_at'] < time()) {
                    unset($_SESSION[$key]);
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser(): ?array
    {
        $userIdKey = $this->prefix . 'current_user_id';
        
        if (!isset($_SESSION[$userIdKey])) {
            return null;
        }
        
        return $this->getUserById($_SESSION[$userIdKey]);
    }
    
    /**
     * Set current user
     */
    public function setCurrentUser(string $userId): void
    {
        $_SESSION[$this->prefix . 'current_user_id'] = $userId;
    }
    
    /**
     * Logout current user
     */
    public function logout(): void
    {
        unset($_SESSION[$this->prefix . 'current_user_id']);
    }
    
    /**
     * Get user by ID
     */
    private function getUserById(string $userId): ?array
    {
        $userKey = $this->prefix . 'user_' . $userId;
        
        if (!isset($_SESSION[$userKey])) {
            return null;
        }
        
        return $_SESSION[$userKey];
    }
    
    /**
     * Find user by email
     */
    private function findUserByEmail(string $email): ?array
    {
        $emailKey = $this->prefix . 'email_' . $email;
        
        if (!isset($_SESSION[$emailKey])) {
            return null;
        }
        
        $userId = $_SESSION[$emailKey];
        return $this->getUserById($userId);
    }
    
    /**
     * Update last login time
     */
    private function updateLastLogin(string $userId, string $provider): void
    {
        $userKey = $this->prefix . 'user_' . $userId;
        
        if (isset($_SESSION[$userKey])) {
            $_SESSION[$userKey]['last_login_at'] = time();
            $_SESSION[$userKey]['last_login_provider'] = $provider;
            $_SESSION[$userKey]['updated_at'] = time();
        }
        
        // Update current user
        $this->setCurrentUser($userId);
    }
}