<?php
namespace FreePBX\modules;

/**
 * SAML Authentication Module for FreePBX 17
 *
 * Provides SAML 2.0 Single Sign-On authentication with support for
 * multiple Identity Providers, Just-in-Time user provisioning, and
 * comprehensive security features.
 *
 * @author FreePBX Community
 * @license GPLv3
 * @version 17.0.1
 */
class Samlauth extends \FreePBX\FreePBX_Helpers implements \FreePBX\BMO {

    /**
     * Constructor
     * @param object $freepbx FreePBX object
     */
    public function __construct($freepbx = null) {
        if ($freepbx == null) {
            throw new \Exception('Not given a FreePBX Object');
        }
        $this->FreePBX = $freepbx;
        $this->db = $freepbx->Database;

        // Load composer autoloader
        $autoloadPath = __DIR__ . '/vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
        }
    }

    /**
     * Install method - called when module is installed
     */
    public function install() {
        // Initialize default settings
        $this->setSetting('jit_provisioning_enabled', '1');
        $this->setSetting('jit_default_sections', '*');
        $this->setSetting('session_timeout', '28800'); // 8 hours
        $this->setSetting('assertion_cache_ttl', '3600'); // 1 hour
        $this->setSetting('schema_version', '1');
        $this->setSetting('setup_completed', '0');

        // Create certificate directory if it doesn't exist
        $certDir = '/etc/freepbx/saml/certs';
        if (!is_dir($certDir)) {
            mkdir($certDir, 0700, true);
            // Set ownership to asterisk user if running as root
            if (function_exists('posix_getuid') && posix_getuid() === 0) {
                chown($certDir, 'asterisk');
                chgrp($certDir, 'asterisk');
            }
        }

        out('SAML Authentication module installed successfully');
    }

    /**
     * Uninstall method - called when module is uninstalled
     */
    public function uninstall() {
        // Cleanup will be handled by module.xml database drops
        out('SAML Authentication module uninstalled');
    }

    /**
     * Backup method - returns data to be backed up
     * @return array Data to backup
     */
    public function backup() {
        $backup = array();

        // Backup IdP configurations
        $sql = "SELECT * FROM saml_idp_config";
        $stmt = $this->db->query($sql);
        $backup['idp_configs'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Backup user settings
        $sql = "SELECT * FROM saml_user_settings";
        $stmt = $this->db->query($sql);
        $backup['user_settings'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Backup group settings
        $sql = "SELECT * FROM saml_group_settings";
        $stmt = $this->db->query($sql);
        $backup['group_settings'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Backup module settings
        $sql = "SELECT * FROM saml_settings";
        $stmt = $this->db->query($sql);
        $backup['settings'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Backup certificates (excluding private keys for security)
        $certDir = '/etc/freepbx/saml/certs';
        if (file_exists($certDir . '/sp.crt')) {
            $backup['sp_certificate'] = file_get_contents($certDir . '/sp.crt');
        }

        return $backup;
    }

    /**
     * Restore method - restores from backup data
     * @param array $backup Backup data array
     */
    public function restore($backup) {
        if (isset($backup['idp_configs'])) {
            foreach ($backup['idp_configs'] as $config) {
                $this->saveIdpConfig($config, $config['id']);
            }
        }

        if (isset($backup['user_settings'])) {
            foreach ($backup['user_settings'] as $setting) {
                $sql = "INSERT INTO saml_user_settings (user_id, saml_enabled, idp_id, saml_nameid, last_login)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE saml_enabled = VALUES(saml_enabled),
                                               idp_id = VALUES(idp_id)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array(
                    $setting['user_id'],
                    $setting['saml_enabled'],
                    $setting['idp_id'],
                    $setting['saml_nameid'],
                    $setting['last_login']
                ));
            }
        }

        if (isset($backup['group_settings'])) {
            foreach ($backup['group_settings'] as $setting) {
                $sql = "INSERT INTO saml_group_settings (group_id, saml_enabled, idp_id)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE saml_enabled = VALUES(saml_enabled),
                                               idp_id = VALUES(idp_id)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array(
                    $setting['group_id'],
                    $setting['saml_enabled'],
                    $setting['idp_id']
                ));
            }
        }

        if (isset($backup['settings'])) {
            foreach ($backup['settings'] as $setting) {
                $this->setSetting($setting['setting_key'], $setting['setting_value']);
            }
        }

        if (isset($backup['sp_certificate'])) {
            $certDir = '/etc/freepbx/saml/certs';
            if (is_dir($certDir)) {
                file_put_contents($certDir . '/sp.crt', $backup['sp_certificate']);
                chmod($certDir . '/sp.crt', 0600);
            }
        }
    }

    /**
     * Configuration page initialization
     * @param string $page The page being loaded
     */
    public function doConfigPageInit($page) {
        // Handle form submissions
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'save_idp':
                    $this->handleSaveIdp();
                    break;
                case 'delete_idp':
                    $this->handleDeleteIdp();
                    break;
                case 'save_settings':
                    $this->handleSaveSettings();
                    break;
                case 'generate_certificates':
                    $this->handleGenerateCertificates();
                    break;
            }
        }
    }

    /**
     * Get action buttons for admin page
     * @param array $request Request data
     * @return array Button definitions
     */
    public function getActionBar($request) {
        $buttons = array();

        if (isset($request['view']) && $request['view'] === 'edit') {
            $buttons = array(
                'submit' => array(
                    'name' => 'submit',
                    'id' => 'submit',
                    'value' => _('Save'),
                ),
                'reset' => array(
                    'name' => 'reset',
                    'id' => 'reset',
                    'value' => _('Reset'),
                )
            );
        }

        return $buttons;
    }

    /**
     * AJAX handler
     * @return array Response data
     */
    public function ajaxRequest($req, &$setting) {
        switch ($req) {
            case 'test_idp':
                return $this->testIdpConnection($_POST['idp_id']);
            case 'get_metadata':
                return $this->getSpMetadata();
            case 'load_template':
                return $this->loadIdpTemplate($_POST['provider']);
            default:
                return array('status' => false, 'message' => 'Unknown request');
        }
    }

    // =========================================================================
    // IdP Configuration Methods
    // =========================================================================

    /**
     * Get all IdP configurations
     * @return array Array of IdP configurations
     */
    public function getAllIdpConfigs() {
        $sql = "SELECT * FROM saml_idp_config ORDER BY name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get specific IdP configuration
     * @param int $id IdP ID
     * @return array|false IdP configuration or false if not found
     */
    public function getIdpConfig($id) {
        $sql = "SELECT * FROM saml_idp_config WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array($id));
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get enabled IdP configuration (for single IdP mode)
     * @return array|false First enabled IdP or false if none
     */
    public function getEnabledIdp() {
        $sql = "SELECT * FROM saml_idp_config WHERE enabled = 1 LIMIT 1";
        $stmt = $this->db->query($sql);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Save IdP configuration
     * @param array $data IdP configuration data
     * @param int|null $id IdP ID for update, null for insert
     * @return int IdP ID
     */
    public function saveIdpConfig($data, $id = null) {
        $now = time();

        if ($id) {
            // Update existing
            $sql = "UPDATE saml_idp_config SET
                    name = ?, provider_type = ?, entity_id = ?, sso_url = ?,
                    slo_url = ?, certificate = ?, certificate_new = ?,
                    settings = ?, enabled = ?, updated_at = ?
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                $data['name'],
                $data['provider_type'],
                $data['entity_id'],
                $data['sso_url'],
                $data['slo_url'] ?? null,
                $data['certificate'],
                $data['certificate_new'] ?? null,
                $data['settings'] ?? null,
                isset($data['enabled']) ? 1 : 0,
                $now,
                $id
            ));
            return $id;
        } else {
            // Insert new
            $sql = "INSERT INTO saml_idp_config
                    (name, provider_type, entity_id, sso_url, slo_url, certificate,
                     certificate_new, settings, enabled, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                $data['name'],
                $data['provider_type'],
                $data['entity_id'],
                $data['sso_url'],
                $data['slo_url'] ?? null,
                $data['certificate'],
                $data['certificate_new'] ?? null,
                $data['settings'] ?? null,
                isset($data['enabled']) ? 1 : 0,
                $now,
                $now
            ));
            return $this->db->lastInsertId();
        }
    }

    /**
     * Delete IdP configuration
     * @param int $id IdP ID
     * @return bool Success status
     */
    public function deleteIdpConfig($id) {
        $sql = "DELETE FROM saml_idp_config WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(array($id));
    }

    /**
     * Load IdP template
     * @param string $provider Provider name (azure, okta, google, onelogin, auth0)
     * @return array|false Template data or false if not found
     */
    public function loadIdpTemplate($provider) {
        $templateFile = __DIR__ . '/templates/' . $provider . '.json';
        if (file_exists($templateFile)) {
            $content = file_get_contents($templateFile);
            return json_decode($content, true);
        }
        return false;
    }

    // =========================================================================
    // Settings Methods
    // =========================================================================

    /**
     * Get a setting value
     * @param string $key Setting key
     * @param mixed $default Default value if not found
     * @return mixed Setting value
     */
    public function getSetting($key, $default = null) {
        $sql = "SELECT setting_value FROM saml_settings WHERE setting_key = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array($key));
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    }

    /**
     * Set a setting value
     * @param string $key Setting key
     * @param mixed $value Setting value
     * @return bool Success status
     */
    public function setSetting($key, $value) {
        $now = time();
        $sql = "INSERT INTO saml_settings (setting_key, setting_value, updated_at)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                       updated_at = VALUES(updated_at)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(array($key, $value, $now));
    }

    /**
     * Get all settings
     * @return array Associative array of settings
     */
    public function getAllSettings() {
        $sql = "SELECT setting_key, setting_value FROM saml_settings";
        $stmt = $this->db->query($sql);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $settings = array();
        foreach ($results as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    }

    // =========================================================================
    // Security Logging Methods
    // =========================================================================

    /**
     * Log security event
     * @param string $eventType Event type (login_success, login_failure, etc.)
     * @param array $details Event details
     */
    public function logSecurityEvent($eventType, $details = array()) {
        $sql = "INSERT INTO saml_security_log
                (event_type, user_id, idp_id, message, details, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            $eventType,
            $details['user_id'] ?? null,
            $details['idp_id'] ?? null,
            $details['message'] ?? '',
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            time()
        ));

        // Also log to FreePBX log
        $this->freepbx_log(FPBX_LOG_INFO, "SAML {$eventType}: " . ($details['message'] ?? ''));
    }

    /**
     * Get security log entries
     * @param int $limit Number of entries to retrieve
     * @param int $offset Offset for pagination
     * @return array Log entries
     */
    public function getSecurityLog($limit = 100, $offset = 0) {
        $sql = "SELECT * FROM saml_security_log
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array($limit, $offset));
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Log message to FreePBX log
     * @param int $level Log level
     * @param string $message Message to log
     */
    private function freepbx_log($level, $message) {
        if (function_exists('freepbx_log')) {
            freepbx_log($level, $message);
        } else {
            error_log("[SAML] {$message}");
        }
    }

    // =========================================================================
    // Form Handlers
    // =========================================================================

    /**
     * Handle save IdP form submission
     */
    private function handleSaveIdp() {
        try {
            $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;

            $data = array(
                'name' => $_POST['name'],
                'provider_type' => $_POST['provider_type'],
                'entity_id' => $_POST['entity_id'],
                'sso_url' => $_POST['sso_url'],
                'slo_url' => $_POST['slo_url'] ?? null,
                'certificate' => $_POST['certificate'],
                'enabled' => isset($_POST['enabled'])
            );

            $idpId = $this->saveIdpConfig($data, $id);

            $this->logSecurityEvent('idp_config_saved', array(
                'idp_id' => $idpId,
                'message' => 'IdP configuration saved: ' . $data['name']
            ));

            $_SESSION['saml_message'] = array(
                'type' => 'success',
                'message' => _('IdP configuration saved successfully')
            );

            header('Location: ?display=samlauth');
            exit;

        } catch (\Exception $e) {
            $_SESSION['saml_message'] = array(
                'type' => 'danger',
                'message' => _('Error saving IdP configuration: ') . $e->getMessage()
            );
        }
    }

    /**
     * Handle delete IdP form submission
     */
    private function handleDeleteIdp() {
        try {
            $id = (int)$_POST['id'];
            $this->deleteIdpConfig($id);

            $this->logSecurityEvent('idp_config_deleted', array(
                'idp_id' => $id,
                'message' => 'IdP configuration deleted'
            ));

            $_SESSION['saml_message'] = array(
                'type' => 'success',
                'message' => _('IdP configuration deleted successfully')
            );

        } catch (\Exception $e) {
            $_SESSION['saml_message'] = array(
                'type' => 'danger',
                'message' => _('Error deleting IdP configuration: ') . $e->getMessage()
            );
        }
    }

    /**
     * Handle save settings form submission
     */
    private function handleSaveSettings() {
        try {
            $this->setSetting('jit_provisioning_enabled', isset($_POST['jit_provisioning_enabled']) ? '1' : '0');
            $this->setSetting('jit_default_sections', $_POST['jit_default_sections'] ?? '*');
            $this->setSetting('session_timeout', (int)$_POST['session_timeout']);

            $_SESSION['saml_message'] = array(
                'type' => 'success',
                'message' => _('Settings saved successfully')
            );

        } catch (\Exception $e) {
            $_SESSION['saml_message'] = array(
                'type' => 'danger',
                'message' => _('Error saving settings: ') . $e->getMessage()
            );
        }
    }

    /**
     * Handle generate certificates form submission
     */
    private function handleGenerateCertificates() {
        try {
            require_once __DIR__ . '/classes/CertificateManager.php';
            $certManager = new Samlauth\CertificateManager();
            $certManager->generateCertificates();

            $_SESSION['saml_message'] = array(
                'type' => 'success',
                'message' => _('Certificates generated successfully')
            );

        } catch (\Exception $e) {
            $_SESSION['saml_message'] = array(
                'type' => 'danger',
                'message' => _('Error generating certificates: ') . $e->getMessage()
            );
        }
    }

    /**
     * Test IdP connection
     * @param int $idpId IdP ID
     * @return array Test result
     */
    private function testIdpConnection($idpId) {
        try {
            $idp = $this->getIdpConfig($idpId);
            if (!$idp) {
                return array('status' => false, 'message' => 'IdP not found');
            }

            // Basic validation
            if (empty($idp['entity_id']) || empty($idp['sso_url']) || empty($idp['certificate'])) {
                return array('status' => false, 'message' => 'IdP configuration incomplete');
            }

            // Try to parse certificate
            $cert = "-----BEGIN CERTIFICATE-----\n" .
                    chunk_split($idp['certificate'], 64) .
                    "-----END CERTIFICATE-----";

            $certData = openssl_x509_parse($cert);
            if (!$certData) {
                return array('status' => false, 'message' => 'Invalid certificate format');
            }

            // Check certificate expiration
            $expiryDate = date('Y-m-d', $certData['validTo_time_t']);
            $daysUntilExpiry = round(($certData['validTo_time_t'] - time()) / 86400);

            if ($daysUntilExpiry < 0) {
                return array('status' => false, 'message' => 'Certificate expired on ' . $expiryDate);
            }

            if ($daysUntilExpiry < 30) {
                return array(
                    'status' => true,
                    'message' => 'Configuration valid',
                    'warning' => "Certificate expires in {$daysUntilExpiry} days ({$expiryDate})"
                );
            }

            return array(
                'status' => true,
                'message' => 'Configuration valid. Certificate expires on ' . $expiryDate
            );

        } catch (\Exception $e) {
            return array('status' => false, 'message' => 'Test failed: ' . $e->getMessage());
        }
    }

    /**
     * Get SP metadata XML
     * @return string SP metadata XML
     */
    public function getSpMetadata() {
        try {
            require_once __DIR__ . '/classes/SamlConfigManager.php';
            $configManager = new Samlauth\SamlConfigManager($this->db, $this);

            // Get first enabled IdP or use generic settings
            $idp = $this->getEnabledIdp();
            $idpId = $idp ? $idp['id'] : null;

            $settings = $configManager->buildSettingsArray($idpId);

            $settingsObj = new \OneLogin\Saml2\Settings($settings);
            $metadata = $settingsObj->getSPMetadata();

            $errors = $settingsObj->validateMetadata($metadata);
            if (!empty($errors)) {
                throw new \Exception('Invalid metadata: ' . implode(', ', $errors));
            }

            return $metadata;

        } catch (\Exception $e) {
            $this->freepbx_log(FPBX_LOG_ERROR, "Error generating SP metadata: " . $e->getMessage());
            throw $e;
        }
    }
}
