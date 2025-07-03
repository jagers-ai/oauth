# Integration Guide

This guide shows how to integrate HolyOAuth with popular PHP frameworks and vanilla PHP applications.

## Vanilla PHP Integration

### Basic Implementation

```php
<?php
// login.php
session_start();
require 'vendor/autoload.php';

use HolyOAuth\Core\OAuthManager;
use HolyOAuth\Providers\GoogleProvider;
use HolyOAuth\Storage\SessionStorage;

$storage = new SessionStorage();
$manager = new OAuthManager($storage);

$googleProvider = GoogleProvider::create(
    $_ENV['GOOGLE_CLIENT_ID'],
    $_ENV['GOOGLE_CLIENT_SECRET'],
    'https://example.com/callback.php'
);

$manager->registerProvider('google', $googleProvider);

// Start OAuth flow
$authUrl = $manager->getAuthorizationUrl('google');
header('Location: ' . $authUrl);
exit;
```

```php
<?php
// callback.php
session_start();
require 'vendor/autoload.php';

use HolyOAuth\Core\OAuthManager;
use HolyOAuth\Providers\GoogleProvider;
use HolyOAuth\Storage\SessionStorage;

$storage = new SessionStorage();
$manager = new OAuthManager($storage);

// Register provider (same config as login.php)
$googleProvider = GoogleProvider::create(
    $_ENV['GOOGLE_CLIENT_ID'],
    $_ENV['GOOGLE_CLIENT_SECRET'],
    'https://example.com/callback.php'
);

$manager->registerProvider('google', $googleProvider);

try {
    // Handle OAuth callback
    $user = $manager->handleCallback('google', $_GET['code'], $_GET['state']);
    
    // Redirect to dashboard
    header('Location: /dashboard.php');
    exit;
    
} catch (Exception $e) {
    // Handle error
    die('OAuth failed: ' . $e->getMessage());
}
```

```php
<?php
// dashboard.php
session_start();
require 'vendor/autoload.php';

use HolyOAuth\Storage\SessionStorage;

$storage = new SessionStorage();
$user = $storage->getCurrentUser();

if (!$user) {
    header('Location: /login.php');
    exit;
}

echo "Welcome, " . htmlspecialchars($user['name']) . "!";
```

## Laravel Integration

### 1. Service Provider

Create a service provider for dependency injection:

```php
<?php
// app/Providers/OAuthServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use HolyOAuth\Core\OAuthManager;
use HolyOAuth\Providers\GoogleProvider;
use HolyOAuth\Storage\SessionStorage;
use HolyOAuth\Security\StateManager;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

class OAuthServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(OAuthManager::class, function ($app) {
            $storage = new SessionStorage();
            $stateManager = new StateManager($storage);
            $manager = new OAuthManager($storage, $stateManager);
            
            // Register Google provider
            $googleProvider = new GoogleProvider(
                [
                    'clientId' => config('services.google.client_id'),
                    'clientSecret' => config('services.google.client_secret'),
                    'redirectUri' => config('services.google.redirect')
                ],
                [
                    'storage' => $storage,
                    'stateManager' => $stateManager,
                    'httpClient' => new Client(),
                    'requestFactory' => new HttpFactory()
                ]
            );
            
            $manager->registerProvider('google', $googleProvider);
            
            return $manager;
        });
    }
}
```

### 2. Routes

```php
// routes/web.php
use App\Http\Controllers\Auth\OAuthController;

Route::get('/auth/{provider}', [OAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
```

### 3. Controller

```php
<?php
// app/Http/Controllers/Auth/OAuthController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use HolyOAuth\Core\OAuthManager;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class OAuthController extends Controller
{
    protected OAuthManager $oauthManager;
    
    public function __construct(OAuthManager $oauthManager)
    {
        $this->oauthManager = $oauthManager;
    }
    
    public function redirect($provider)
    {
        try {
            $url = $this->oauthManager->getAuthorizationUrl($provider);
            return redirect($url);
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'OAuth provider not found');
        }
    }
    
    public function callback(Request $request, $provider)
    {
        try {
            $oauthUser = $this->oauthManager->handleCallback(
                $provider,
                $request->get('code'),
                $request->get('state')
            );
            
            // Find or create Laravel user
            $user = User::updateOrCreate(
                ['email' => $oauthUser['email']],
                [
                    'name' => $oauthUser['name'],
                    'avatar' => $oauthUser['picture'] ?? null,
                    'oauth_provider' => $provider,
                    'oauth_id' => $oauthUser['provider_id']
                ]
            );
            
            Auth::login($user, true);
            
            return redirect()->intended('/dashboard');
            
        } catch (\Exception $e) {
            return redirect('/login')->with('error', 'Authentication failed');
        }
    }
}
```

### 4. Configuration

```php
// config/services.php
return [
    // ...
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/auth/google/callback',
    ],
];
```

## Symfony Integration

### 1. Service Configuration

```yaml
# config/services.yaml
services:
    HolyOAuth\Core\OAuthManager:
        arguments:
            - '@HolyOAuth\Storage\SessionStorage'
            - '@HolyOAuth\Security\StateManager'
            
    HolyOAuth\Storage\SessionStorage:
        public: true
        
    HolyOAuth\Security\StateManager:
        arguments:
            - '@HolyOAuth\Storage\SessionStorage'
            
    HolyOAuth\Providers\GoogleProvider:
        arguments:
            - clientId: '%env(GOOGLE_CLIENT_ID)%'
              clientSecret: '%env(GOOGLE_CLIENT_SECRET)%'
              redirectUri: '%env(GOOGLE_REDIRECT_URI)%'
            - storage: '@HolyOAuth\Storage\SessionStorage'
              stateManager: '@HolyOAuth\Security\StateManager'
              httpClient: '@http_client'
              requestFactory: '@psr17.factory'
        tags:
            - { name: 'oauth.provider', provider: 'google' }
```

### 2. Controller

```php
<?php
// src/Controller/OAuthController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use HolyOAuth\Core\OAuthManager;

class OAuthController extends AbstractController
{
    public function __construct(
        private OAuthManager $oauthManager
    ) {}
    
    #[Route('/auth/{provider}', name: 'oauth_redirect')]
    public function redirect(string $provider): Response
    {
        $url = $this->oauthManager->getAuthorizationUrl($provider);
        return $this->redirect($url);
    }
    
    #[Route('/auth/{provider}/callback', name: 'oauth_callback')]
    public function callback(Request $request, string $provider): Response
    {
        try {
            $user = $this->oauthManager->handleCallback(
                $provider,
                $request->get('code'),
                $request->get('state')
            );
            
            // Handle user authentication
            // ...
            
            return $this->redirectToRoute('dashboard');
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Authentication failed');
            return $this->redirectToRoute('login');
        }
    }
}
```

## Custom Storage Implementation

You can implement your own storage adapter:

```php
<?php
use HolyOAuth\Storage\StorageInterface;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineStorage implements StorageInterface
{
    private EntityManagerInterface $em;
    
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }
    
    public function saveState(string $state, array $data): bool
    {
        $entity = new OAuthState();
        $entity->setState($state);
        $entity->setData($data);
        $entity->setExpiresAt(new \DateTime('@' . $data['expires_at']));
        
        $this->em->persist($entity);
        $this->em->flush();
        
        return true;
    }
    
    public function verifyState(string $state): ?array
    {
        $entity = $this->em->getRepository(OAuthState::class)
            ->findOneBy(['state' => $state]);
            
        if (!$entity || $entity->getExpiresAt() < new \DateTime()) {
            return null;
        }
        
        return $entity->getData();
    }
    
    // Implement other methods...
}
```

## Security Best Practices

1. **Always use HTTPS** in production
2. **Validate redirect URIs** to prevent open redirects
3. **Set secure session cookies**:
   ```php
   ini_set('session.cookie_secure', '1');
   ini_set('session.cookie_httponly', '1');
   ini_set('session.cookie_samesite', 'Lax');
   ```
4. **Implement rate limiting** on OAuth endpoints
5. **Log OAuth events** for security monitoring
6. **Rotate client secrets** periodically

## Next Steps

- Review the [API Reference](API.md) for detailed method documentation
- Check [examples/basic](../examples/basic) for a complete working example
- Consider implementing custom storage for production use