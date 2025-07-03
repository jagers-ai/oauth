<?php

declare(strict_types=1);

namespace HolyOAuth\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Validates ID tokens from OAuth providers
 */
class TokenValidator
{
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private LoggerInterface $logger;
    
    /**
     * Cache for JWK keys
     * @var array<string, array>
     */
    private array $jwkCache = [];
    
    /**
     * Cache expiration time in seconds (10 minutes)
     */
    private int $cacheExpiration = 600;
    
    /**
     * Google's JWKS URI
     */
    private const GOOGLE_JWKS_URI = 'https://www.googleapis.com/oauth2/v3/certs';
    
    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        ?LoggerInterface $logger = null
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->logger = $logger ?? new NullLogger();
    }
    
    /**
     * Validate a Google ID token
     * 
     * @param string $idToken The ID token to validate
     * @param string $clientId The expected client ID (audience)
     * @param string|null $nonce Optional nonce to verify
     * @return array The decoded token payload
     * @throws \Exception If validation fails
     */
    public function validateGoogleIdToken(string $idToken, string $clientId, ?string $nonce = null): array
    {
        try {
            // Get JWK keys
            $keys = $this->getGoogleJwks();
            
            // Decode and verify the token
            $payload = JWT::decode($idToken, JWK::parseKeySet($keys));
            
            // Convert to array
            $claims = (array) $payload;
            
            // Validate required claims
            $this->validateClaims($claims, $clientId, $nonce);
            
            $this->logger->info('Google ID token validated successfully', [
                'sub' => $claims['sub'] ?? 'unknown'
            ]);
            
            return $claims;
            
        } catch (\Exception $e) {
            $this->logger->error('Google ID token validation failed', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('ID token validation failed: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Validate generic JWT token with provided keys
     * 
     * @param string $token The JWT token to validate
     * @param array $keys The JWK keys
     * @param array $expectedClaims Expected claim values
     * @return array The decoded token payload
     * @throws \Exception If validation fails
     */
    public function validateJwt(string $token, array $keys, array $expectedClaims = []): array
    {
        try {
            // Decode and verify the token
            $payload = JWT::decode($token, JWK::parseKeySet($keys));
            
            // Convert to array
            $claims = (array) $payload;
            
            // Validate expected claims
            foreach ($expectedClaims as $claim => $expectedValue) {
                if (!isset($claims[$claim]) || $claims[$claim] !== $expectedValue) {
                    throw new \Exception("Invalid claim '{$claim}'");
                }
            }
            
            return $claims;
            
        } catch (\Exception $e) {
            throw new \Exception('JWT validation failed: ' . $e->getMessage(), 0, $e);
        }
    }
    
    /**
     * Get Google's JWK keys with caching
     * 
     * @return array The JWK keys
     * @throws \Exception If fetching keys fails
     */
    private function getGoogleJwks(): array
    {
        $cacheKey = 'google_jwks';
        
        // Check cache
        if (isset($this->jwkCache[$cacheKey])) {
            $cached = $this->jwkCache[$cacheKey];
            if ($cached['expires_at'] > time()) {
                return $cached['keys'];
            }
        }
        
        // Fetch new keys
        try {
            $request = $this->requestFactory->createRequest('GET', self::GOOGLE_JWKS_URI);
            $response = $this->httpClient->sendRequest($request);
            
            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to fetch JWK keys: HTTP ' . $response->getStatusCode());
            }
            
            $keys = json_decode($response->getBody()->getContents(), true);
            
            if (!isset($keys['keys'])) {
                throw new \Exception('Invalid JWK response format');
            }
            
            // Cache the keys
            $this->jwkCache[$cacheKey] = [
                'keys' => $keys,
                'expires_at' => time() + $this->cacheExpiration
            ];
            
            $this->logger->info('Google JWK keys fetched and cached');
            
            return $keys;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch Google JWK keys', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Validate ID token claims
     * 
     * @param array $claims The token claims
     * @param string $clientId Expected client ID
     * @param string|null $nonce Expected nonce
     * @throws \Exception If validation fails
     */
    private function validateClaims(array $claims, string $clientId, ?string $nonce = null): void
    {
        // Validate issuer
        $validIssuers = ['https://accounts.google.com', 'accounts.google.com'];
        if (!isset($claims['iss']) || !in_array($claims['iss'], $validIssuers)) {
            throw new \Exception('Invalid issuer');
        }
        
        // Validate audience
        if (!isset($claims['aud']) || $claims['aud'] !== $clientId) {
            throw new \Exception('Invalid audience');
        }
        
        // Validate expiration
        if (!isset($claims['exp']) || $claims['exp'] < time()) {
            throw new \Exception('Token has expired');
        }
        
        // Validate issued at time
        if (!isset($claims['iat']) || $claims['iat'] > time() + 60) {
            throw new \Exception('Invalid issued at time');
        }
        
        // Validate nonce if provided
        if ($nonce !== null) {
            if (!isset($claims['nonce']) || $claims['nonce'] !== $nonce) {
                throw new \Exception('Invalid nonce');
            }
        }
        
        // Validate subject
        if (!isset($claims['sub']) || empty($claims['sub'])) {
            throw new \Exception('Missing subject');
        }
    }
    
    /**
     * Clear the JWK cache
     */
    public function clearCache(): void
    {
        $this->jwkCache = [];
        $this->logger->info('JWK cache cleared');
    }
    
    /**
     * Set cache expiration time
     * 
     * @param int $seconds Cache expiration in seconds
     */
    public function setCacheExpiration(int $seconds): void
    {
        $this->cacheExpiration = $seconds;
    }
}