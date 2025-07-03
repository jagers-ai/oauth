<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use HolyOAuth\Core\OAuthManager;
use HolyOAuth\Providers\GoogleProvider;
use HolyOAuth\Storage\SessionStorage;
use HolyOAuth\Security\StateManager;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

// Start session
session_start();

// Configuration
$config = [
    'google' => [
        'clientId' => $_ENV['GOOGLE_CLIENT_ID'] ?? 'your-client-id',
        'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'your-client-secret',
        'redirectUri' => 'http://localhost:8000/callback.php'
    ]
];

// Initialize storage and state manager
$storage = new SessionStorage();
$stateManager = new StateManager($storage);

// Initialize OAuth manager
$oauthManager = new OAuthManager($storage, $stateManager);

// Create HTTP client and factory for token validation
$httpClient = new Client();
$httpFactory = new HttpFactory();

// Register Google provider
$googleProvider = new GoogleProvider(
    $config['google'],
    [
        'storage' => $storage,
        'stateManager' => $stateManager,
        'httpClient' => $httpClient,
        'requestFactory' => $httpFactory
    ]
);

$oauthManager->registerProvider('google', $googleProvider);

// Handle different actions
$action = $_GET['action'] ?? 'home';

switch ($action) {
    case 'login':
        // Start OAuth flow
        $authUrl = $oauthManager->getAuthorizationUrl('google', [
            'scope' => ['openid', 'email', 'profile']
        ]);
        header('Location: ' . $authUrl);
        exit;
        
    case 'logout':
        // Logout
        $storage->logout();
        header('Location: /');
        exit;
        
    default:
        // Show home page
        $currentUser = $storage->getCurrentUser();
        break;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>HolyOAuth Example</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .user-info {
            background: #f0f0f0;
            padding: 20px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .user-info img {
            border-radius: 50%;
            width: 80px;
            height: 80px;
            margin-right: 20px;
            vertical-align: middle;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 0;
            background: #4285f4;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #357ae8;
        }
        .btn-logout {
            background: #db4437;
        }
        .btn-logout:hover {
            background: #c23321;
        }
        pre {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>HolyOAuth Example</h1>
        
        <?php if ($currentUser): ?>
            <h2>Welcome, <?= htmlspecialchars($currentUser['name'] ?? 'User') ?>!</h2>
            
            <div class="user-info">
                <?php if (!empty($currentUser['picture'])): ?>
                    <img src="<?= htmlspecialchars($currentUser['picture']) ?>" alt="Profile">
                <?php endif; ?>
                
                <strong>Email:</strong> <?= htmlspecialchars($currentUser['email'] ?? 'N/A') ?><br>
                <strong>Provider:</strong> <?= htmlspecialchars($currentUser['provider'] ?? 'N/A') ?><br>
                <strong>User ID:</strong> <?= htmlspecialchars($currentUser['id']) ?><br>
                <strong>Joined:</strong> <?= date('Y-m-d H:i:s', $currentUser['created_at'] ?? time()) ?>
            </div>
            
            <a href="?action=logout" class="btn btn-logout">Logout</a>
            
            <h3>Debug Information</h3>
            <pre><?= htmlspecialchars(json_encode($currentUser, JSON_PRETTY_PRINT)) ?></pre>
            
        <?php else: ?>
            <p>Welcome to the HolyOAuth example application. This demonstrates how to integrate Google OAuth login.</p>
            
            <a href="?action=login" class="btn">Login with Google</a>
            
            <h3>Setup Instructions</h3>
            <ol>
                <li>Set up a Google OAuth application at <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
                <li>Add <code>http://localhost:8000/callback.php</code> to authorized redirect URIs</li>
                <li>Set environment variables:
                    <pre>export GOOGLE_CLIENT_ID="your-client-id"
export GOOGLE_CLIENT_SECRET="your-client-secret"</pre>
                </li>
                <li>Run the example:
                    <pre>composer install
php -S localhost:8000</pre>
                </li>
            </ol>
        <?php endif; ?>
    </div>
</body>
</html>