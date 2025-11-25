<?php
/**
 * SP Metadata Endpoint
 *
 * Provides Service Provider metadata XML for IdP configuration
 */

// Load FreePBX bootstrap
require_once '/etc/freepbx.conf';

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

try {
    // Get FreePBX object
    global $db;
    $FreePBX = \FreePBX::Create();
    $samlModule = $FreePBX->Samlauth;

    // Generate SP metadata
    $metadata = $samlModule->getSpMetadata();

    // Output as XML
    header('Content-Type: text/xml');
    echo $metadata;

} catch (Exception $e) {
    // Log error
    error_log('SAML Metadata Error: ' . $e->getMessage());

    // Return error response
    http_response_code(500);
    header('Content-Type: text/plain');
    echo 'Error generating metadata. Please check SAML configuration.';
}
