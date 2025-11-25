<?php
/**
 * Single Logout Service (SLS) Endpoint
 *
 * Handles SAML logout requests and responses from Identity Provider
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

    // Create auth handler
    require_once __DIR__ . '/../classes/SamlAuthHandler.php';
    require_once __DIR__ . '/../classes/SamlConfigManager.php';
    require_once __DIR__ . '/../classes/SamlAttributeMapper.php';

    $authHandler = new \FreePBX\modules\Samlauth\SamlAuthHandler($db, $samlModule);

    // Process logout (will handle both requests and responses)
    $authHandler->processLogout();

    // processLogout() redirects, so this line shouldn't be reached

} catch (Exception $e) {
    // Log error
    error_log('SAML SLO Error: ' . $e->getMessage());

    // Even on error, clear local session and redirect
    $_SESSION = array();
    session_destroy();

    header('Location: /admin?slo_error=1');
    exit;
}
