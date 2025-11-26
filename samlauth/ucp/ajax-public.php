<?php
/**
 * Public AJAX endpoint for UCP SAML (bypasses UCP auth)
 *
 * This endpoint is accessible without authentication for login page functionality
 */

// Prevent direct access from outside
if (empty($_SERVER['HTTP_REFERER']) || strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) === false) {
    header('HTTP/1.0 403 Forbidden');
    die(json_encode(['status' => false, 'message' => 'Forbidden']));
}

// Only allow get_login_config command
$command = $_REQUEST['command'] ?? '';
if ($command !== 'get_login_config') {
    header('HTTP/1.0 403 Forbidden');
    die(json_encode(['status' => false, 'message' => 'Invalid command']));
}

// Bootstrap FreePBX
$bootstrap_settings = [];
$bootstrap_settings['freepbx_auth'] = false;
$restrict_mods = true;

require_once '/etc/freepbx.conf';

try {
    $FreePBX = FreePBX::Create();

    // Check if module is available
    if (!$FreePBX->Modules->checkStatus('samlauth')) {
        header('Content-Type: application/json');
        echo json_encode(['status' => true, 'enabled' => false]);
        exit;
    }

    $saml = $FreePBX->Samlauth;

    // Get database connection for direct queries (avoids column name issues)
    $db = $FreePBX->Database;

    // Check if UCP SAML is enabled
    $stmt = $db->prepare("SELECT setting_value FROM saml_settings WHERE setting_key = ?");
    $stmt->execute(['ucp_enabled']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $ucpEnabled = $row ? $row['setting_value'] : '1';
    if ($ucpEnabled !== '1') {
        header('Content-Type: application/json');
        echo json_encode(['status' => true, 'enabled' => false]);
        exit;
    }

    $stmt = $db->prepare("SELECT setting_value FROM saml_settings WHERE setting_key = ?");
    $stmt->execute(['ucp_show_button']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $showButton = $row ? $row['setting_value'] : '1';

    $stmt = $db->prepare("SELECT setting_value FROM saml_settings WHERE setting_key = ?");
    $stmt->execute(['ucp_redirect_all']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $redirectAll = $row ? $row['setting_value'] : '0';

    // Get enabled IdPs
    $idps = $saml->getAllIdpConfigs();
    $enabledIdps = array_filter($idps, function($idp) {
        return !empty($idp['enabled']);
    });

    if (empty($enabledIdps)) {
        header('Content-Type: application/json');
        echo json_encode(['status' => true, 'enabled' => false]);
        exit;
    }

    // Build IdP data
    $protocol = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') ? 'http' : 'https';
    $host = $_SERVER['HTTP_HOST'];

    $idpData = array();
    foreach ($enabledIdps as $idp) {
        $providerLabels = array(
            'azure' => array('text' => 'Login with Microsoft', 'icon' => 'fa-windows'),
            'okta' => array('text' => 'Login with Okta', 'icon' => 'fa-key'),
            'google' => array('text' => 'Login with Google', 'icon' => 'fa-google'),
            'onelogin' => array('text' => 'Login with OneLogin', 'icon' => 'fa-sign-in'),
            'auth0' => array('text' => 'Login with Auth0', 'icon' => 'fa-shield'),
            'generic' => array('text' => 'Login with ' . $idp['name'], 'icon' => 'fa-sign-in')
        );

        $provider = $providerLabels[$idp['provider_type']] ?? $providerLabels['generic'];

        $idpData[] = array(
            'id' => $idp['id'],
            'name' => $idp['name'],
            'provider_type' => $idp['provider_type'],
            'button_text' => $provider['text'],
            'icon' => $provider['icon'],
            'login_url' => "{$protocol}://{$host}/admin/modules/samlauth/endpoints/login.php?idp={$idp['id']}&return_to=/ucp"
        );
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status' => true,
        'enabled' => true,
        'show_button' => ($showButton === '1'),
        'redirect_all' => ($redirectAll === '1'),
        'idps' => $idpData
    ]);

} catch (Exception $e) {
    error_log('SAML UCP Public AJAX Error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
