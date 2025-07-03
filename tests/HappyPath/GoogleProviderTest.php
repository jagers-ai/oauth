<?php

declare(strict_types=1);

namespace HolyOAuth\Tests\HappyPath;

use PHPUnit\Framework\TestCase;
use HolyOAuth\Core\OAuthManager;
use HolyOAuth\Providers\GoogleProvider;
use HolyOAuth\Storage\SessionStorage;
use HolyOAuth\Security\StateManager;
use AidanCasey\MockClient\MockClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\HttpFactory;

/**
 * Happy path tests for Google OAuth provider
 */
class GoogleProviderTest extends TestCase
{
    private OAuthManager $oauthManager;
    private MockClient $mockClient;
    private HttpFactory $httpFactory;
    private SessionStorage $storage;
    private StateManager $stateManager;
    
    protected function setUp(): void
    {
        // Initialize mock HTTP client
        $this->mockClient = new MockClient();
        $this->httpFactory = new HttpFactory();
        
        // Initialize storage and state manager
        $this->storage = new SessionStorage();
        $this->stateManager = new StateManager($this->storage);
        
        // Initialize OAuth manager
        $this->oauthManager = new OAuthManager($this->storage, $this->stateManager);
        
        // Register Google provider
        $googleProvider = new GoogleProvider(
            [
                'clientId' => 'test-client-id',
                'clientSecret' => 'test-client-secret',
                'redirectUri' => 'http://localhost/callback'
            ],
            [
                'storage' => $this->storage,
                'stateManager' => $this->stateManager,
                'httpClient' => $this->mockClient,
                'requestFactory' => $this->httpFactory
            ]
        );
        
        $this->oauthManager->registerProvider('google', $googleProvider);
    }
    
    /**
     * Test 1: Login URL generation with PKCE
     */
    public function testLoginUrlGenerationWithPKCE(): void
    {
        // Generate authorization URL
        $authUrl = $this->oauthManager->getAuthorizationUrl('google');
        
        // Parse URL and query parameters
        $parsedUrl = parse_url($authUrl);
        parse_str($parsedUrl['query'] ?? '', $params);
        
        // Assert base URL is correct
        $this->assertEquals('accounts.google.com', $parsedUrl['host']);
        $this->assertEquals('/o/oauth2/v2/auth', $parsedUrl['path']);
        
        // Assert required OAuth parameters
        $this->assertArrayHasKey('client_id', $params);
        $this->assertEquals('test-client-id', $params['client_id']);
        
        $this->assertArrayHasKey('redirect_uri', $params);
        $this->assertEquals('http://localhost/callback', $params['redirect_uri']);
        
        $this->assertArrayHasKey('response_type', $params);
        $this->assertEquals('code', $params['response_type']);
        
        $this->assertArrayHasKey('scope', $params);
        $this->assertStringContainsString('openid', $params['scope']);
        $this->assertStringContainsString('email', $params['scope']);
        $this->assertStringContainsString('profile', $params['scope']);
        
        // Assert PKCE parameters
        $this->assertArrayHasKey('code_challenge', $params);
        $this->assertArrayHasKey('code_challenge_method', $params);
        $this->assertEquals('S256', $params['code_challenge_method']);
        
        // Assert state token
        $this->assertArrayHasKey('state', $params);
        $state = $params['state'];
        
        // Verify state is stored
        $stateData = $this->storage->verifyState($state);
        $this->assertNotNull($stateData);
        $this->assertArrayHasKey('code_verifier', $stateData);
        $this->assertArrayHasKey('provider', $stateData);
        $this->assertEquals('google', $stateData['provider']);
    }
    
    /**
     * Test 2: Callback handling success with user data
     */
    public function testCallbackHandlingSuccess(): void
    {
        // Step 1: Generate auth URL and get state
        $authUrl = $this->oauthManager->getAuthorizationUrl('google');
        parse_str(parse_url($authUrl, PHP_URL_QUERY), $params);
        $state = $params['state'];
        
        // Step 2: Mock token exchange response
        $tokenResponse = [
            'access_token' => 'mock-access-token',
            'refresh_token' => 'mock-refresh-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
            'scope' => 'openid email profile',
            'id_token' => $this->generateMockIdToken()
        ];
        
        $this->mockClient->addResponse(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($tokenResponse))
        );
        
        // Step 3: Mock JWK keys response
        $jwkResponse = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'kid' => 'test-key-id',
                    'n' => 'test-n-value',
                    'e' => 'AQAB'
                ]
            ]
        ];
        
        $this->mockClient->addResponse(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($jwkResponse))
        );
        
        // Step 4: Mock user info response
        $userInfoResponse = [
            'sub' => '123456789',
            'email' => 'test@example.com',
            'email_verified' => true,
            'name' => 'Test User',
            'given_name' => 'Test',
            'family_name' => 'User',
            'picture' => 'https://example.com/photo.jpg',
            'locale' => 'en'
        ];
        
        $this->mockClient->addResponse(
            new Response(200, ['Content-Type' => 'application/json'], json_encode($userInfoResponse))
        );
        
        // Step 5: Handle callback
        $user = $this->oauthManager->handleCallback('google', 'test-auth-code', $state);
        
        // Assert user data
        $this->assertIsArray($user);
        $this->assertArrayHasKey('id', $user);
        $this->assertArrayHasKey('email', $user);
        $this->assertEquals('test@example.com', $user['email']);
        $this->assertArrayHasKey('name', $user);
        $this->assertEquals('Test User', $user['name']);
        $this->assertArrayHasKey('provider', $user);
        $this->assertEquals('google', $user['provider']);
        
        // Verify user is stored in session
        $currentUser = $this->storage->getCurrentUser();
        $this->assertNotNull($currentUser);
        $this->assertEquals($user['id'], $currentUser['id']);
        
        // Verify social account is linked
        $socialUser = $this->storage->findUserBySocialAccount('google', '123456789');
        $this->assertNotNull($socialUser);
        $this->assertEquals($user['id'], $socialUser['id']);
    }
    
    /**
     * Generate a mock ID token (not cryptographically valid, just for structure)
     */
    private function generateMockIdToken(): string
    {
        $header = [
            'alg' => 'RS256',
            'kid' => 'test-key-id',
            'typ' => 'JWT'
        ];
        
        $payload = [
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-client-id',
            'sub' => '123456789',
            'email' => 'test@example.com',
            'email_verified' => true,
            'name' => 'Test User',
            'picture' => 'https://example.com/photo.jpg',
            'iat' => time(),
            'exp' => time() + 3600
        ];
        
        // Note: This is not a valid JWT, just for testing structure
        // In real tests with token validation, you'd need to properly sign this
        return base64_encode(json_encode($header)) . '.' . 
               base64_encode(json_encode($payload)) . '.' . 
               'mock-signature';
    }
}