<?php
namespace UCP\Modules;

use UCP\UCP;

/**
 * SAML Authentication for UCP
 *
 * Provides SAML login integration for User Control Panel
 */
class Samlauth extends \UCP\Modules {

    protected $module = 'Samlauth';

    /**
     * Initialize module
     */
    function __construct($Modules) {
        $this->Modules = $Modules;
    }

    /**
     * Get module display name
     */
    public function getDisplay($dashboard_id = null) {
        return _('SAML Authentication');
    }

    /**
     * Poll for updates (not used for auth modules)
     */
    public function poll() {
        return array();
    }

    /**
     * UCP delayed load - inject JavaScript on login page
     */
    public function ucpDelayedLoad() {
        // Check if user is not logged in (on login page)
        if (!isset($this->UCP->User)) {
            return;
        }
        $user = $this->UCP->User->getUser();
        if (empty($user)) {
            // We're on the login page, inject our JavaScript
            echo '<script src="/admin/modules/samlauth/ucp/assets/js/login-inject.js"></script>';
        }
    }

    /**
     * Get menu items (auth modules don't add menu items)
     */
    public function getMenuItems() {
        return array();
    }

    /**
     * Check if SAML authentication is enabled for UCP
     */
    public function isEnabled() {
        $saml = $this->UCP->FreePBX->Samlauth;
        $enabled = $saml->getSetting('ucp_enabled', '1');
        return ($enabled === '1');
    }

    /**
     * Get enabled IdPs for UCP
     */
    public function getEnabledIdps() {
        if (!$this->isEnabled()) {
            return array();
        }

        $saml = $this->UCP->FreePBX->Samlauth;
        $idps = $saml->getAllIdpConfigs();

        return array_filter($idps, function($idp) {
            return !empty($idp['enabled']);
        });
    }

    /**
     * Get SAML login configuration for UCP
     */
    public function getLoginConfig() {
        if (!$this->isEnabled()) {
            return array(
                'status' => true,
                'enabled' => false
            );
        }

        $saml = $this->UCP->FreePBX->Samlauth;
        $showButton = $saml->getSetting('ucp_show_button', '1');
        $redirectAll = $saml->getSetting('ucp_redirect_all', '0');

        $idps = $this->getEnabledIdps();

        if (empty($idps)) {
            return array(
                'status' => true,
                'enabled' => false
            );
        }

        // Build IdP data
        $protocol = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') ? 'http' : 'https';
        $host = $_SERVER['HTTP_HOST'];

        $idpData = array();
        foreach ($idps as $idp) {
            $providerLabels = array(
                'azure' => array('text' => _('Login with Microsoft'), 'icon' => 'fa-windows'),
                'okta' => array('text' => _('Login with Okta'), 'icon' => 'fa-key'),
                'google' => array('text' => _('Login with Google'), 'icon' => 'fa-google'),
                'onelogin' => array('text' => _('Login with OneLogin'), 'icon' => 'fa-sign-in'),
                'auth0' => array('text' => _('Login with Auth0'), 'icon' => 'fa-shield'),
                'generic' => array('text' => sprintf(_('Login with %s'), $idp['name']), 'icon' => 'fa-sign-in')
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

        return array(
            'status' => true,
            'enabled' => true,
            'show_button' => ($showButton === '1'),
            'redirect_all' => ($redirectAll === '1'),
            'idps' => $idpData
        );
    }

    /**
     * AJAX handler for UCP
     */
    public function ajaxRequest($command, $settings) {
        if ($command === 'get_login_config') {
            $settings['authenticate'] = false;
            return true;
        }
        return false;
    }

    /**
     * AJAX custom handler
     */
    public function ajaxCustomHandler() {
        $command = $_REQUEST['command'] ?? '';

        if ($command === 'get_login_config') {
            return $this->getLoginConfig();
        }

        return array('status' => false, 'message' => 'Unknown command');
    }
}
