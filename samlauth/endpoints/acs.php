<?php
/**
 * Assertion Consumer Service (ACS) Endpoint
 *
 * Handles SAML responses from Identity Provider
 * This is where the IdP sends the user after authentication
 */

// Start session
session_start();

// Load FreePBX bootstrap
require_once '/etc/freepbx.conf';

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Force HTTPS in production
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
    error_log('WARNING: SAML ACS accessed over HTTP. Use HTTPS in production!');
}

try {
    // Get FreePBX object
    global $db;
    $FreePBX = \FreePBX::Create();
    $samlModule = $FreePBX->Samlauth;

    // Create auth handler
    require_once __DIR__ . '/../classes/SamlAuthHandler.php';
    require_once __DIR__ . '/../classes/SamlConfigManager.php';
    require_once __DIR__ . '/../classes/SamlAttributeMapper.php';

    $authHandler = new \FreePBX\modules\Samlauth\SamlAuthHandler($db, $samlModule);

    // Process SAML response
    $userData = $authHandler->processResponse();

    // Get user from FreePBX
    $username = $userData['attributes']['email'][0] ?? $userData['nameid'];

    // Set FreePBX session
    $_SESSION['AMP_user'] = array(
        'username' => $username,
        'auth_type' => 'saml',
    );

    // Get RelayState (return URL)
    $relayState = $_POST['RelayState'] ?? '/admin';

    // Validate RelayState (prevent open redirect)
    if (strpos($relayState, '/') !== 0 || strpos($relayState, '//') === 0) {
        $relayState = '/admin';
    }

    // Redirect to original destination
    header('Location: ' . $relayState);
    exit;

} catch (Exception $e) {
    // Log error securely
    error_log('SAML ACS Error: ' . $e->getMessage());

    // Show generic error to user
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Authentication Error</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 50px; }
            .error { background: #ffebee; border: 1px solid #f44336; padding: 20px; border-radius: 4px; }
            h1 { color: #c62828; }
        </style>
    </head>
    <body>
        <div class="error">
            <h1>Authentication Error</h1>
            <p>An error occurred during authentication. Please contact your system administrator.</p>
            <p><a href="/admin">Return to login</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
