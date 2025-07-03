<?php

declare(strict_types=1);

namespace HolyOAuth\Core;

/**
 * Core OAuth interface that all OAuth managers must implement
 */
interface OAuthInterface
{
    /**
     * Get the authorization URL for the OAuth flow
     * 
     * @param array $options Additional options like 'scope', 'prompt', etc.
     * @return string The authorization URL
     */
    public function getAuthorizationUrl(array $options = []): string;
    
    /**
     * Get the state token for CSRF protection
     * 
     * @return string The state token
     */
    public function getState(): string;
    
    /**
     * Handle the OAuth callback and exchange code for tokens
     * 
     * @param string $code The authorization code
     * @param string $state The state token to verify
     * @return array User information array
     * @throws \Exception If state verification fails or token exchange fails
     */
    public function handleCallback(string $code, string $state): array;
    
    /**
     * Get user information from the OAuth provider
     * 
     * @param string $accessToken The access token
     * @return array Normalized user information
     */
    public function getUserInfo(string $accessToken): array;
    
    /**
     * Validate an ID token (for providers that support it)
     * 
     * @param string $idToken The ID token to validate
     * @return array The decoded token payload
     * @throws \Exception If validation fails
     */
    public function validateIdToken(string $idToken): array;
}