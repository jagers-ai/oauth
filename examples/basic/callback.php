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

// Handle OAuth callback
try {
    // Get parameters
    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error'] ?? null;
    
    if ($error) {
        throw new Exception('OAuth error: ' . $error . ' - ' . ($_GET['error_description'] ?? 'Unknown error'));
    }
    
    if (!$code || !$state) {
        throw new Exception('Missing required parameters');
    }
    
    // Handle callback
    $user = $oauthManager->handleCallback('google', $code, $state);
    
    // Redirect to home
    header('Location: /');
    exit;
    
} catch (Exception $e) {
    // Handle error
    $errorMessage = $e->getMessage();
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>OAuth Callback - HolyOAuth Example</title>
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
        .error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c00;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 0;
            background: #4285f4;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>OAuth Callback Error</h1>
        
        <div class="error">
            <strong>Error:</strong> <?= htmlspecialchars($errorMessage) ?>
        </div>
        
        <a href="/" class="btn">Back to Home</a>
    </div>
</body>
</html>