<?php
/**
 * SAML Logout Initiator
 *
 * Initiates SAML Single Logout flow
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

    // Get return URL
    $returnTo = $_GET['returnTo'] ?? '/admin';

    // Create auth handler
    require_once __DIR__ . '/../classes/SamlAuthHandler.php';
    require_once __DIR__ . '/../classes/SamlConfigManager.php';
    require_once __DIR__ . '/../classes/SamlAttributeMapper.php';

    $authHandler = new \FreePBX\modules\Samlauth\SamlAuthHandler($db, $samlModule);

    // Initiate logout (will redirect to IdP or clear local session)
    $authHandler->initiateLogout($returnTo);

    // initiateLogout() redirects, so this line shouldn't be reached

} catch (Exception $e) {
    // Log error
    error_log('SAML Logout Error: ' . $e->getMessage());

    // Clear local session anyway
    $_SESSION = array();
    session_destroy();

    header('Location: /admin?logout_error=1');
    exit;
}
