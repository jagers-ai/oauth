# HolyOAuth

Simple and secure OAuth integration for PHP projects with PKCE support and ID token validation.

## Quick Start (10 lines)

```bash
composer require holyhabit/oauth:^0.1
```

```php
use HolyOAuth\Core\OAuthManager;
use HolyOAuth\Providers\GoogleProvider;
use HolyOAuth\Storage\SessionStorage;

$storage = new SessionStorage();
$manager = new OAuthManager($storage);
$manager->registerProvider('google', GoogleProvider::create('client-id', 'secret', 'redirect-uri'));

// Login: header('Location: ' . $manager->getAuthorizationUrl('google'));
// Callback: $user = $manager->handleCallback('google', $_GET['code'], $_GET['state']);
```

## Features

- 🔒 **PKCE (S256)** - Enhanced security with Proof Key for Code Exchange
- 🛡️ **ID Token Validation** - Verify JWT tokens from OAuth providers  
- 🔑 **CSRF Protection** - 128-bit state tokens
- 📦 **PSR Compliant** - PSR-4, PSR-12, PSR-18
- 🚀 **Easy Integration** - Works with any PHP framework
- ✅ **Well Tested** - Unit tests with mock HTTP clients

## Installation

```bash
composer require holyhabit/oauth:^0.1.0-alpha
```

## Basic Example

See [examples/basic](examples/basic) for a complete working example.

```php
<?php
require 'vendor/autoload.php';

use HolyOAuth\Core\OAuthManager;
use HolyOAuth\Providers\GoogleProvider;
use HolyOAuth\Storage\SessionStorage;
use HolyOAuth\Security\StateManager;

// Initialize
$storage = new SessionStorage();
$stateManager = new StateManager($storage);
$oauthManager = new OAuthManager($storage, $stateManager);

// Configure Google OAuth
$googleProvider = GoogleProvider::create(
    $_ENV['GOOGLE_CLIENT_ID'],
    $_ENV['GOOGLE_CLIENT_SECRET'], 
    'http://localhost:8000/callback.php'
);

$oauthManager->registerProvider('google', $googleProvider);

// Start OAuth flow
$authUrl = $oauthManager->getAuthorizationUrl('google');
header('Location: ' . $authUrl);
```

## Documentation

- [Installation Guide](docs/INSTALLATION.md)
- [Integration Guide](docs/INTEGRATION.md)
- [API Reference](docs/API.md)

## Requirements

- PHP 8.1+
- `ext-json`
- `ext-openssl`

## Security

- Uses PKCE (S256) by default for all OAuth flows
- Validates ID tokens using provider's JWK keys
- CSRF protection with cryptographically secure state tokens
- Supports HTTPS-only in production

## License

MIT License. See [LICENSE](LICENSE) file.

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Roadmap

- [x] Google OAuth with PKCE
- [ ] Refresh token support
- [ ] Kakao OAuth provider
- [ ] Naver OAuth provider
- [ ] Database storage adapter
- [ ] Laravel integration package