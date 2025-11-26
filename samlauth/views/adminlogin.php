<?php
/**
 * SAML Login Button for Admin Login Page
 *
 * This view is automatically included on the FreePBX login page
 * when the SAML module is enabled and configured to show the login button
 */

// Direct database access approach (doesn't require module to be loaded)
try {
    // Get database connection
    if (!isset($db)) {
        global $db;
        if (!$db) {
            $FreePBX = \FreePBX::Create();
            $db = $FreePBX->Database;
        }
    }

    // Check if we should show the login button (direct DB query)
    $stmt = $db->prepare("SELECT value FROM saml_settings WHERE setting_key = ?");
    $stmt->execute(['show_login_button']);
    $showButtonRow = $stmt->fetch(\PDO::FETCH_ASSOC);
    $showButton = $showButtonRow ? $showButtonRow['value'] : '1';

    $stmt = $db->prepare("SELECT value FROM saml_settings WHERE setting_key = ?");
    $stmt->execute(['redirect_to_saml']);
    $redirectRow = $stmt->fetch(\PDO::FETCH_ASSOC);
    $redirectAll = $redirectRow ? $redirectRow['value'] : '0';

    // Get enabled IdPs (direct DB query)
    $stmt = $db->query("SELECT * FROM saml_idp_config WHERE enabled = 1 ORDER BY id");
    $enabledIdps = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($enabledIdps)) {
        // No enabled IdPs, don't show anything
        return;
    }
} catch (\Exception $e) {
    // Database error, silently return
    error_log('SAML Login Button Error: ' . $e->getMessage());
    return;
}

// If redirect all logins is enabled, automatically redirect
if ($redirectAll === '1' && !isset($_GET['local'])) {
    $idp = reset($enabledIdps); // Get first enabled IdP
    $protocol = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') ? 'http' : 'https';
    $host = $_SERVER['HTTP_HOST'];
    $loginUrl = "{$protocol}://{$host}/admin/modules/samlauth/endpoints/login.php?idp={$idp['id']}";

    // Redirect to SAML login
    header("Location: {$loginUrl}");
    exit;
}

// Only show button if setting is enabled
if ($showButton !== '1') {
    return;
}

?>
<style>
.saml-login-separator {
    margin: 20px 0;
    text-align: center;
    position: relative;
}
.saml-login-separator:before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #ddd;
}
.saml-login-separator span {
    background: #f5f5f5;
    padding: 0 10px;
    position: relative;
    color: #666;
    font-size: 12px;
    text-transform: uppercase;
}
.saml-login-button {
    display: block;
    width: 100%;
    padding: 12px;
    margin: 10px 0;
    border: 1px solid #4285f4;
    background: #fff;
    color: #4285f4;
    border-radius: 4px;
    text-decoration: none;
    text-align: center;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
}
.saml-login-button:hover {
    background: #4285f4;
    color: #fff;
    text-decoration: none;
}
.saml-login-button i {
    margin-right: 8px;
}
.saml-provider-google {
    border-color: #4285f4;
    color: #4285f4;
}
.saml-provider-google:hover {
    background: #4285f4;
    color: #fff;
}
.saml-provider-azure {
    border-color: #00a4ef;
    color: #00a4ef;
}
.saml-provider-azure:hover {
    background: #00a4ef;
    color: #fff;
}
.saml-provider-okta {
    border-color: #007dc1;
    color: #007dc1;
}
.saml-provider-okta:hover {
    background: #007dc1;
    color: #fff;
}
.saml-provider-generic {
    border-color: #666;
    color: #666;
}
.saml-provider-generic:hover {
    background: #666;
    color: #fff;
}
</style>

<div class="saml-login-separator">
    <span><?php echo _('Or'); ?></span>
</div>

<?php
$protocol = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') ? 'http' : 'https';
$host = $_SERVER['HTTP_HOST'];

foreach ($enabledIdps as $idp):
    $loginUrl = "{$protocol}://{$host}/admin/modules/samlauth/endpoints/login.php?idp={$idp['id']}";

    // Determine button text and icon based on provider type
    $providerLabels = array(
        'azure' => array('text' => _('Login with Microsoft'), 'icon' => 'fa-windows'),
        'okta' => array('text' => _('Login with Okta'), 'icon' => 'fa-key'),
        'google' => array('text' => _('Login with Google'), 'icon' => 'fa-google'),
        'onelogin' => array('text' => _('Login with OneLogin'), 'icon' => 'fa-sign-in'),
        'auth0' => array('text' => _('Login with Auth0'), 'icon' => 'fa-shield'),
        'generic' => array('text' => sprintf(_('Login with %s'), htmlspecialchars($idp['name'])), 'icon' => 'fa-sign-in')
    );

    $provider = $providerLabels[$idp['provider_type']] ?? $providerLabels['generic'];
    $cssClass = 'saml-provider-' . $idp['provider_type'];
?>
    <a href="<?php echo htmlspecialchars($loginUrl); ?>"
       class="saml-login-button <?php echo $cssClass; ?>">
        <i class="fa <?php echo $provider['icon']; ?>"></i>
        <?php echo $provider['text']; ?>
    </a>
<?php endforeach; ?>

<?php if ($redirectAll === '1'): ?>
<div style="text-align: center; margin-top: 15px;">
    <a href="?local=1" style="font-size: 12px; color: #666;">
        <?php echo _('Use local login instead'); ?>
    </a>
</div>
<?php endif; ?>
