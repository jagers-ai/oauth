<?php

declare(strict_types=1);

namespace HolyOAuth\Core;

use HolyOAuth\Security\StateManager;
use HolyOAuth\Storage\StorageInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Main OAuth manager that handles provider registration and OAuth flow
 */
class OAuthManager
{
    /**
     * @var array<string, OAuthInterface>
     */
    private array $providers = [];
    
    private StateManager $stateManager;
    private StorageInterface $storage;
    private LoggerInterface $logger;
    
    public function __construct(
        StorageInterface $storage,
        ?StateManager $stateManager = null,
        ?LoggerInterface $logger = null
    ) {
        $this->storage = $storage;
        $this->stateManager = $stateManager ?? new StateManager($storage);
        $this->logger = $logger ?? new NullLogger();
    }
    
    /**
     * Register an OAuth provider
     */
    public function registerProvider(string $name, OAuthInterface $provider): void
    {
        $this->providers[$name] = $provider;
        $this->logger->info('OAuth provider registered', ['provider' => $name]);
    }
    
    /**
     * Get a registered provider
     */
    public function getProvider(string $name): OAuthInterface
    {
        if (!isset($this->providers[$name])) {
            throw new \InvalidArgumentException("Provider '{$name}' is not registered");
        }
        
        return $this->providers[$name];
    }
    
    /**
     * Check if a provider is registered
     */
    public function hasProvider(string $name): bool
    {
        return isset($this->providers[$name]);
    }
    
    /**
     * Get all registered provider names
     */
    public function getProviderNames(): array
    {
        return array_keys($this->providers);
    }
    
    /**
     * Start OAuth flow for a provider
     */
    public function getAuthorizationUrl(string $provider, array $options = []): string
    {
        $oauthProvider = $this->getProvider($provider);
        return $oauthProvider->getAuthorizationUrl($options);
    }
    
    /**
     * Handle OAuth callback
     */
    public function handleCallback(string $provider, string $code, string $state): array
    {
        $oauthProvider = $this->getProvider($provider);
        
        try {
            $userInfo = $oauthProvider->handleCallback($code, $state);
            
            // Normalize user info
            $normalizedInfo = $this->normalizeUserInfo($provider, $userInfo);
            
            // Store or update user
            $user = $this->storage->findOrCreateUser($provider, $normalizedInfo);
            
            $this->logger->info('OAuth callback completed', [
                'provider' => $provider,
                'user_id' => $user['id']
            ]);
            
            return $user;
            
        } catch (\Exception $e) {
            $this->logger->error('OAuth callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Normalize user information across providers
     */
    protected function normalizeUserInfo(string $provider, array $userInfo): array
    {
        // Basic normalization - providers can extend this
        $normalized = [
            'provider' => $provider,
            'provider_id' => $userInfo['id'] ?? $userInfo['sub'] ?? null,
            'email' => $userInfo['email'] ?? null,
            'name' => $userInfo['name'] ?? null,
            'picture' => $userInfo['picture'] ?? $userInfo['avatar_url'] ?? null,
            'raw_data' => $userInfo
        ];
        
        // Ensure required fields
        if (empty($normalized['provider_id'])) {
            throw new \RuntimeException('Provider ID is required');
        }
        
        return $normalized;
    }
    
    /**
     * Get the storage instance
     */
    public function getStorage(): StorageInterface
    {
        return $this->storage;
    }
    
    /**
     * Get the state manager instance
     */
    public function getStateManager(): StateManager
    {
        return $this->stateManager;
    }
}