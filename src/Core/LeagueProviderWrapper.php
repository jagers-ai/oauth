<?php

declare(strict_types=1);

namespace HolyOAuth\Core;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use HolyOAuth\Security\StateManager;
use HolyOAuth\Storage\StorageInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Wrapper for League OAuth2 Client providers
 * Adds PKCE support, state management, and additional security features
 */
abstract class LeagueProviderWrapper implements OAuthInterface
{
    protected AbstractProvider $provider;
    protected StateManager $stateManager;
    protected StorageInterface $storage;
    protected LoggerInterface $logger;
    protected array $defaultOptions = [];
    
    /**
     * PKCE method (S256 recommended)
     */
    protected string $pkceMethod = 'S256';
    
    /**
     * Current PKCE verifier
     */
    protected ?string $codeVerifier = null;
    
    public function __construct(
        AbstractProvider $provider,
        StateManager $stateManager,
        StorageInterface $storage,
        ?LoggerInterface $logger = null
    ) {
        $this->provider = $provider;
        $this->stateManager = $stateManager;
        $this->storage = $storage;
        $this->logger = $logger ?? new NullLogger();
    }
    
    /**
     * Get the authorization URL with PKCE parameters
     */
    public function getAuthorizationUrl(array $options = []): string
    {
        $options = array_merge($this->defaultOptions, $options);
        
        // Generate state token
        $state = $this->stateManager->generateState();
        $options['state'] = $state;
        
        // Add PKCE parameters if enabled
        if ($this->pkceMethod) {
            $this->codeVerifier = $this->generateCodeVerifier();
            $codeChallenge = $this->generateCodeChallenge($this->codeVerifier);
            
            $options['code_challenge'] = $codeChallenge;
            $options['code_challenge_method'] = $this->pkceMethod;
            
            // Store verifier with state
            $this->storage->saveState($state, [
                'code_verifier' => $this->codeVerifier,
                'provider' => $this->getProviderName(),
                'expires_at' => time() + 600 // 10 minutes
            ]);
        }
        
        $url = $this->provider->getAuthorizationUrl($options);
        
        $this->logger->info('Generated authorization URL', [
            'provider' => $this->getProviderName(),
            'state' => $state,
            'pkce_enabled' => $this->pkceMethod !== null
        ]);
        
        return $url;
    }
    
    /**
     * Get the current state token
     */
    public function getState(): string
    {
        return $this->provider->getState();
    }
    
    /**
     * Handle OAuth callback with PKCE verification
     */
    public function handleCallback(string $code, string $state): array
    {
        // Verify state
        $stateData = $this->stateManager->verifyState($state);
        if (!$stateData) {
            throw new \Exception('Invalid state token');
        }
        
        $tokenOptions = ['code' => $code];
        
        // Add PKCE verifier if stored
        if (isset($stateData['code_verifier'])) {
            $tokenOptions['code_verifier'] = $stateData['code_verifier'];
        }
        
        try {
            // Exchange code for token
            $accessToken = $this->provider->getAccessToken('authorization_code', $tokenOptions);
            
            // Get user info
            $userInfo = $this->getUserInfo($accessToken->getToken());
            
            // Store tokens if needed
            $userInfo['access_token'] = $accessToken->getToken();
            $userInfo['refresh_token'] = $accessToken->getRefreshToken();
            $userInfo['expires_at'] = $accessToken->getExpires();
            
            // Validate ID token if available
            if ($accessToken instanceof AccessTokenInterface && $idToken = $accessToken->getValues()['id_token'] ?? null) {
                try {
                    $claims = $this->validateIdToken($idToken);
                    $userInfo['id_token_claims'] = $claims;
                } catch (\Exception $e) {
                    $this->logger->warning('ID token validation failed', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $this->logger->info('OAuth callback handled successfully', [
                'provider' => $this->getProviderName(),
                'user_id' => $userInfo['id'] ?? 'unknown'
            ]);
            
            return $userInfo;
            
        } catch (\Exception $e) {
            $this->logger->error('OAuth callback failed', [
                'provider' => $this->getProviderName(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Generate PKCE code verifier
     */
    protected function generateCodeVerifier(): string
    {
        $random = bin2hex(random_bytes(32));
        return rtrim(strtr(base64_encode($random), '+/', '-_'), '=');
    }
    
    /**
     * Generate PKCE code challenge
     */
    protected function generateCodeChallenge(string $verifier): string
    {
        if ($this->pkceMethod === 'S256') {
            $hash = hash('sha256', $verifier, true);
            return rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');
        }
        
        return $verifier; // plain method
    }
    
    /**
     * Get the provider name
     */
    abstract protected function getProviderName(): string;
    
    /**
     * Validate ID token (must be implemented by providers that support it)
     */
    public function validateIdToken(string $idToken): array
    {
        throw new \BadMethodCallException('This provider does not support ID token validation');
    }
}