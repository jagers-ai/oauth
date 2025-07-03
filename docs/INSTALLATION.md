# Installation Guide

## Requirements

Before installing HolyOAuth, ensure your system meets these requirements:

- PHP 8.1 or higher
- Composer
- Extensions: `json`, `openssl`, `curl`

## Installation via Composer

```bash
composer require holyhabit/oauth:^0.1.0-alpha
```

## Setting up Google OAuth

### 1. Create Google OAuth Application

1. Go to [Google Cloud Console](https://console.cloud.google.com)
2. Create a new project or select existing one
3. Enable Google+ API
4. Go to "Credentials" → "Create Credentials" → "OAuth client ID"
5. Choose "Web application"
6. Add authorized redirect URIs:
   - Development: `http://localhost:8000/callback.php`
   - Production: `https://yourdomain.com/callback.php`

### 2. Configure Environment Variables

Create a `.env` file in your project root:

```env
GOOGLE_CLIENT_ID=your-client-id-here
GOOGLE_CLIENT_SECRET=your-client-secret-here
```

### 3. Basic Setup

```php
<?php
require 'vendor/autoload.php';

use HolyOAuth\Core\OAuthManager;
use HolyOAuth\Providers\GoogleProvider;
use HolyOAuth\Storage\SessionStorage;
use HolyOAuth\Security\StateManager;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

// Load environment variables (use your preferred method)
$clientId = $_ENV['GOOGLE_CLIENT_ID'];
$clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'];
$redirectUri = 'http://localhost:8000/callback.php';

// Initialize storage and dependencies
$storage = new SessionStorage();
$stateManager = new StateManager($storage);
$httpClient = new Client();
$httpFactory = new HttpFactory();

// Create OAuth manager
$oauthManager = new OAuthManager($storage, $stateManager);

// Register Google provider with full configuration
$googleProvider = new GoogleProvider(
    [
        'clientId' => $clientId,
        'clientSecret' => $clientSecret,
        'redirectUri' => $redirectUri
    ],
    [
        'storage' => $storage,
        'stateManager' => $stateManager,
        'httpClient' => $httpClient,
        'requestFactory' => $httpFactory
    ]
);

$oauthManager->registerProvider('google', $googleProvider);
```

## Running the Example

1. Clone the repository:
```bash
git clone https://github.com/holyhabit/oauth.git
cd oauth/examples/basic
```

2. Install dependencies:
```bash
composer install
```

3. Set environment variables:
```bash
export GOOGLE_CLIENT_ID="your-client-id"
export GOOGLE_CLIENT_SECRET="your-client-secret"
```

4. Start PHP development server:
```bash
php -S localhost:8000
```

5. Open browser to http://localhost:8000

## Production Considerations

### HTTPS Required

OAuth 2.0 requires HTTPS in production. Make sure your redirect URIs use HTTPS:

```php
$redirectUri = 'https://yourdomain.com/oauth/callback';
```

### Session Configuration

Configure PHP sessions for production:

```php
// Before using SessionStorage
ini_set('session.cookie_secure', '1');     // HTTPS only
ini_set('session.cookie_httponly', '1');   // No JavaScript access
ini_set('session.cookie_samesite', 'Lax'); // CSRF protection

session_start([
    'cookie_lifetime' => 86400,  // 24 hours
    'gc_maxlifetime' => 86400,
]);
```

### Error Handling

Always wrap OAuth operations in try-catch blocks:

```php
try {
    $user = $oauthManager->handleCallback('google', $_GET['code'], $_GET['state']);
} catch (\Exception $e) {
    // Log error
    error_log('OAuth error: ' . $e->getMessage());
    
    // Show user-friendly error
    echo 'Login failed. Please try again.';
}
```

## Next Steps

- Read the [Integration Guide](INTEGRATION.md) for framework-specific examples
- Check the [API Reference](API.md) for detailed documentation
- See [examples/basic](../examples/basic) for a working implementation