<?php

declare(strict_types=1);

namespace HolyOAuth\Security;

use HolyOAuth\Storage\StorageInterface;

/**
 * Manages CSRF state tokens for OAuth flows
 */
class StateManager
{
    private StorageInterface $storage;
    
    /**
     * State token length in bytes (128-bit = 16 bytes)
     */
    private int $stateLength = 16;
    
    /**
     * State token expiration time in seconds (10 minutes)
     */
    private int $stateExpiration = 600;
    
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }
    
    /**
     * Generate a cryptographically secure state token
     * 
     * @return string Base64url encoded state token
     */
    public function generateState(): string
    {
        $bytes = random_bytes($this->stateLength);
        return $this->base64urlEncode($bytes);
    }
    
    /**
     * Save state with associated data
     * 
     * @param string $state The state token
     * @param array $data Additional data to store with the state
     * @return bool Success status
     */
    public function saveState(string $state, array $data = []): bool
    {
        $data['created_at'] = time();
        $data['expires_at'] = time() + $this->stateExpiration;
        
        return $this->storage->saveState($state, $data);
    }
    
    /**
     * Verify a state token
     * 
     * @param string $state The state token to verify
     * @return array|null State data if valid, null if invalid
     */
    public function verifyState(string $state): ?array
    {
        if (empty($state)) {
            return null;
        }
        
        $data = $this->storage->verifyState($state);
        
        if ($data === null) {
            return null;
        }
        
        // Check expiration
        if (isset($data['expires_at']) && $data['expires_at'] < time()) {
            $this->storage->deleteState($state);
            return null;
        }
        
        // State is valid, delete it (one-time use)
        $this->storage->deleteState($state);
        
        return $data;
    }
    
    /**
     * Clean up expired state tokens
     * 
     * @return int Number of cleaned tokens
     */
    public function cleanupExpired(): int
    {
        return $this->storage->cleanupExpiredStates();
    }
    
    /**
     * Base64url encode
     * 
     * @param string $data The data to encode
     * @return string Base64url encoded string
     */
    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Set state token expiration time
     * 
     * @param int $seconds Expiration time in seconds
     */
    public function setStateExpiration(int $seconds): void
    {
        $this->stateExpiration = $seconds;
    }
    
    /**
     * Get state token expiration time
     * 
     * @return int Expiration time in seconds
     */
    public function getStateExpiration(): int
    {
        return $this->stateExpiration;
    }
}