<?php
/**
 * SAML Login Initiator
 *
 * Initiates SAML authentication flow by redirecting to IdP
 */

// Start session
session_start();

// Load FreePBX bootstrap
require_once '/etc/freepbx.conf';

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

try {
    // Get FreePBX object
    global $db;
    $FreePBX = \FreePBX::Create();
    $samlModule = $FreePBX->Samlauth;

    // Get IdP ID (defaults to first enabled IdP)
    $idpId = $_GET['idp'] ?? null;

    // Get return URL
    $returnTo = $_GET['returnTo'] ?? '/admin';

    // Create auth handler
    require_once __DIR__ . '/../classes/SamlAuthHandler.php';
    require_once __DIR__ . '/../classes/SamlConfigManager.php';
    require_once __DIR__ . '/../classes/SamlAttributeMapper.php';

    $authHandler = new \FreePBX\modules\Samlauth\SamlAuthHandler($db, $samlModule);

    // Initiate login (will redirect to IdP)
    $authHandler->initiateLogin($idpId, $returnTo);

    // initiateLogin() redirects, so this line shouldn't be reached

} catch (Exception $e) {
    // Log error
    error_log('SAML Login Error: ' . $e->getMessage());

    // Show error page
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Login Error</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 50px; }
            .error { background: #ffebee; border: 1px solid #f44336; padding: 20px; border-radius: 4px; }
            h1 { color: #c62828; }
        </style>
    </head>
    <body>
        <div class="error">
            <h1>Login Error</h1>
            <p>Unable to initiate SAML login. Please check your SAML configuration.</p>
            <p><?php echo htmlspecialchars($e->getMessage()); ?></p>
            <p><a href="/admin">Return to login</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}
