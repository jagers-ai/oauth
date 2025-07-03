<?php

declare(strict_types=1);

namespace HolyOAuth\Storage;

/**
 * Storage interface for OAuth state and user data
 */
interface StorageInterface
{
    /**
     * Save state token with associated data
     * 
     * @param string $state The state token
     * @param array $data Associated data (provider, code_verifier, expires_at, etc.)
     * @return bool Success status
     */
    public function saveState(string $state, array $data): bool;
    
    /**
     * Verify and retrieve state data
     * 
     * @param string $state The state token to verify
     * @return array|null State data if valid, null if invalid or expired
     */
    public function verifyState(string $state): ?array;
    
    /**
     * Delete a state token
     * 
     * @param string $state The state token to delete
     * @return bool Success status
     */
    public function deleteState(string $state): bool;
    
    /**
     * Find or create a user from OAuth provider data
     * 
     * @param string $provider The OAuth provider name
     * @param array $userData User data from the provider
     * @return array User data with at least 'id' field
     */
    public function findOrCreateUser(string $provider, array $userData): array;
    
    /**
     * Find a user by their social account
     * 
     * @param string $provider The OAuth provider name
     * @param string $providerId The user's ID from the provider
     * @return array|null User data if found, null otherwise
     */
    public function findUserBySocialAccount(string $provider, string $providerId): ?array;
    
    /**
     * Create a new user
     * 
     * @param array $userData User data
     * @return array Created user data with 'id' field
     */
    public function createUser(array $userData): array;
    
    /**
     * Link a social account to an existing user
     * 
     * @param int|string $userId The user ID
     * @param string $provider The OAuth provider name
     * @param array $providerData Data from the OAuth provider
     * @return bool Success status
     */
    public function linkSocialAccount($userId, string $provider, array $providerData): bool;
    
    /**
     * Clean up expired state tokens
     * 
     * @return int Number of deleted tokens
     */
    public function cleanupExpiredStates(): int;
}