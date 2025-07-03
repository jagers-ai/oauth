<?php

declare(strict_types=1);

namespace HolyOAuth\Providers;

use HolyOAuth\Core\LeagueProviderWrapper;
use HolyOAuth\Security\TokenValidator;
use League\OAuth2\Client\Provider\Google as LeagueGoogleProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Google OAuth provider with PKCE and ID token validation
 */
class GoogleProvider extends LeagueProviderWrapper
{
    private TokenValidator $tokenValidator;
    private string $clientId;
    
    /**
     * Create a new Google OAuth provider
     * 
     * @param array $options Provider options (clientId, clientSecret, redirectUri)
     * @param array $collaborators Dependencies (httpClient, requestFactory, storage, stateManager, logger)
     */
    public function __construct(array $options, array $collaborators = [])
    {
        // Create League Google provider
        $provider = new LeagueGoogleProvider([
            'clientId' => $options['clientId'],
            'clientSecret' => $options['clientSecret'],
            'redirectUri' => $options['redirectUri'],
            'accessType' => 'offline', // Request refresh token
            'hostedDomain' => $options['hostedDomain'] ?? null,
            'prompt' => $options['prompt'] ?? 'select_account'
        ]);
        
        $this->clientId = $options['clientId'];
        
        // Set default options
        $this->defaultOptions = [
            'scope' => $options['scope'] ?? ['openid', 'email', 'profile'],
            'access_type' => 'offline',
            'prompt' => 'select_account'
        ];
        
        // Initialize parent
        parent::__construct(
            $provider,
            $collaborators['stateManager'],
            $collaborators['storage'],
            $collaborators['logger'] ?? null
        );
        
        // Initialize token validator
        if (isset($collaborators['httpClient']) && isset($collaborators['requestFactory'])) {
            $this->tokenValidator = new TokenValidator(
                $collaborators['httpClient'],
                $collaborators['requestFactory'],
                $this->logger
            );
        }
    }
    
    /**
     * Get user info from Google
     */
    public function getUserInfo(string $accessToken): array
    {
        try {
            $user = $this->provider->getResourceOwner(
                $this->provider->getAccessToken('authorization_code', ['code' => ''])
            );
            
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'picture' => $user->getAvatar(),
                'given_name' => $user->getFirstName(),
                'family_name' => $user->getLastName(),
                'locale' => $user->getLocale(),
                'hosted_domain' => $user->getHostedDomain()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to get Google user info', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Validate Google ID token
     */
    public function validateIdToken(string $idToken): array
    {
        if (!isset($this->tokenValidator)) {
            throw new \RuntimeException('Token validator not initialized. HTTP client and request factory required.');
        }
        
        return $this->tokenValidator->validateGoogleIdToken($idToken, $this->clientId);
    }
    
    /**
     * Handle OAuth callback with ID token validation
     */
    public function handleCallback(string $code, string $state): array
    {
        // Call parent to handle standard OAuth flow
        $userInfo = parent::handleCallback($code, $state);
        
        // Additional Google-specific processing
        if (isset($userInfo['id_token_claims'])) {
            // Merge ID token claims with user info
            $claims = $userInfo['id_token_claims'];
            
            // Use ID token data as authoritative source
            $userInfo['id'] = $claims['sub'];
            $userInfo['email'] = $claims['email'] ?? $userInfo['email'];
            $userInfo['email_verified'] = $claims['email_verified'] ?? false;
            $userInfo['name'] = $claims['name'] ?? $userInfo['name'];
            $userInfo['picture'] = $claims['picture'] ?? $userInfo['picture'];
            $userInfo['locale'] = $claims['locale'] ?? $userInfo['locale'] ?? null;
            
            // Add Google-specific fields
            $userInfo['google_hd'] = $claims['hd'] ?? null; // Hosted domain
        }
        
        return $userInfo;
    }
    
    /**
     * Get the provider name
     */
    protected function getProviderName(): string
    {
        return 'google';
    }
    
    /**
     * Create GoogleProvider with minimal configuration
     * 
     * @param string $clientId Google OAuth client ID
     * @param string $clientSecret Google OAuth client secret
     * @param string $redirectUri OAuth redirect URI
     * @param array $collaborators Optional dependencies
     * @return self
     */
    public static function create(
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        array $collaborators = []
    ): self {
        return new self([
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri
        ], $collaborators);
    }
}