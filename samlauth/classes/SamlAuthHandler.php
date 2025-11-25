<?php
namespace FreePBX\modules\Samlauth;

/**
 * SAML Authentication Handler
 *
 * Handles SAML authentication flow with comprehensive security features:
 * - Replay attack prevention
 * - Session fixation prevention
 * - Signature validation
 * - User provisioning
 */
class SamlAuthHandler {

    private $db;
    private $module;
    private $configManager;

    public function __construct($db, $module) {
        $this->db = $db;
        $this->module = $module;
        $this->configManager = new SamlConfigManager($db, $module);
    }

    /**
     * Initiate SAML login
     * @param int|null $idpId IdP ID
     * @param string|null $returnTo Return URL after authentication
     * @return void Redirects to IdP
     */
    public function initiateLogin($idpId = null, $returnTo = null) {
        try {
            // Load SAML settings
            $settings = $this->configManager->buildSettingsArray($idpId);
            $auth = new \OneLogin\Saml2\Auth($settings);

            // Validate returnTo URL (prevent open redirect)
            if ($returnTo && !$this->isValidReturnUrl($returnTo)) {
                $returnTo = '/admin';
            }

            // Initiate login and get request ID
            $requestId = $auth->login($returnTo);

            // Store request ID in session for validation (replay attack prevention)
            if (!isset($_SESSION)) {
                session_start();
            }
            $_SESSION['saml_authn_request_id'] = $requestId;
            $_SESSION['saml_idp_id'] = $idpId;

            // Log initiation
            $this->module->logSecurityEvent('login_initiated', array(
                'idp_id' => $idpId,
                'message' => 'SAML login initiated'
            ));

        } catch (\Exception $e) {
            $this->module->logSecurityEvent('login_error', array(
                'idp_id' => $idpId,
                'message' => 'Login initiation failed: ' . $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Process SAML response from IdP
     * @return array User data on success
     * @throws \Exception on authentication failure
     */
    public function processResponse() {
        if (!isset($_SESSION)) {
            session_start();
        }

        try {
            // Get stored request ID and IdP ID
            $requestId = $_SESSION['saml_authn_request_id'] ?? null;
            $idpId = $_SESSION['saml_idp_id'] ?? null;

            if (!$idpId) {
                throw new \Exception('No SAML session found');
            }

            // Load SAML settings
            $settings = $this->configManager->buildSettingsArray($idpId);
            $auth = new \OneLogin\Saml2\Auth($settings);

            // Process response with request ID validation
            $auth->processResponse($requestId);

            // Clear stored request ID
            unset($_SESSION['saml_authn_request_id']);

            // Check for errors
            $errors = $auth->getErrors();
            if (!empty($errors)) {
                $errorMsg = implode(', ', $errors);
                $this->module->logSecurityEvent('login_failure', array(
                    'idp_id' => $idpId,
                    'message' => 'Authentication errors: ' . $errorMsg,
                    'reason' => $auth->getLastErrorReason()
                ));
                throw new \Exception('Authentication failed: ' . $errorMsg);
            }

            // Check if authenticated
            if (!$auth->isAuthenticated()) {
                $this->module->logSecurityEvent('login_failure', array(
                    'idp_id' => $idpId,
                    'message' => 'Not authenticated after processing response'
                ));
                throw new \Exception('Authentication failed');
            }

            // REPLAY ATTACK PREVENTION
            $assertionId = $auth->getLastAssertionId();
            $messageId = $auth->getLastMessageId();

            if ($this->isAssertionProcessed($assertionId)) {
                $this->module->logSecurityEvent('replay_attempt', array(
                    'idp_id' => $idpId,
                    'message' => 'Replay attack detected',
                    'assertion_id' => $assertionId
                ));
                throw new \Exception('Invalid authentication attempt');
            }

            // Store processed assertion ID
            $this->storeProcessedAssertion($assertionId, $messageId);

            // Get user data from SAML assertion
            $nameId = $auth->getNameId();
            $nameIdFormat = $auth->getNameIdFormat();
            $sessionIndex = $auth->getSessionIndex();
            $attributes = $auth->getAttributes();

            // SESSION FIXATION PREVENTION
            session_regenerate_id(true);

            // Process user data
            $userData = array(
                'nameid' => $nameId,
                'nameid_format' => $nameIdFormat,
                'session_index' => $sessionIndex,
                'attributes' => $attributes,
                'idp_id' => $idpId
            );

            // Auto-provision or update user
            $userId = $this->provisionUser($userData);
            $userData['user_id'] = $userId;

            // Create SAML session
            $this->createSamlSession($userId, $idpId, $sessionIndex, $userData);

            // Store in PHP session
            $_SESSION['saml_authenticated'] = true;
            $_SESSION['saml_user_id'] = $userId;
            $_SESSION['saml_nameid'] = $nameId;
            $_SESSION['saml_nameid_format'] = $nameIdFormat;
            $_SESSION['saml_session_index'] = $sessionIndex;
            $_SESSION['saml_idp_id'] = $idpId;

            // Log successful authentication
            $this->module->logSecurityEvent('login_success', array(
                'user_id' => $userId,
                'idp_id' => $idpId,
                'message' => 'SAML authentication successful',
                'nameid' => $nameId
            ));

            return $userData;

        } catch (\Exception $e) {
            // Log error but don't expose details to user
            error_log('SAML authentication error: ' . $e->getMessage());
            throw new \Exception('Authentication failed. Please contact support.');
        }
    }

    /**
     * Initiate SAML logout (SLO)
     * @param string|null $returnTo Return URL after logout
     * @return void Redirects to IdP
     */
    public function initiateLogout($returnTo = null) {
        if (!isset($_SESSION)) {
            session_start();
        }

        try {
            $nameId = $_SESSION['saml_nameid'] ?? null;
            $sessionIndex = $_SESSION['saml_session_index'] ?? null;
            $nameIdFormat = $_SESSION['saml_nameid_format'] ?? null;
            $idpId = $_SESSION['saml_idp_id'] ?? null;

            if (!$nameId || !$idpId) {
                // No SAML session, just clear local session
                $this->clearLocalSession();
                header('Location: ' . ($returnTo ?? '/admin'));
                exit;
            }

            // Load SAML settings
            $settings = $this->configManager->buildSettingsArray($idpId);
            $auth = new \OneLogin\Saml2\Auth($settings);

            // Store logout request ID
            $logoutRequestId = $auth->logout(
                $returnTo,
                array(),
                $nameId,
                $sessionIndex,
                false,
                $nameIdFormat
            );

            $_SESSION['saml_logout_request_id'] = $logoutRequestId;

            // Log logout initiation
            $this->module->logSecurityEvent('logout_initiated', array(
                'user_id' => $_SESSION['saml_user_id'] ?? null,
                'idp_id' => $idpId,
                'message' => 'SAML logout initiated'
            ));

        } catch (\Exception $e) {
            error_log('SAML logout error: ' . $e->getMessage());
            // Even if SLO fails, clear local session
            $this->clearLocalSession();
            header('Location: ' . ($returnTo ?? '/admin'));
            exit;
        }
    }

    /**
     * Process SAML logout response/request (SLO)
     * @return void Redirects after processing
     */
    public function processLogout() {
        if (!isset($_SESSION)) {
            session_start();
        }

        try {
            $idpId = $_SESSION['saml_idp_id'] ?? $_GET['idp'] ?? null;

            if (!$idpId) {
                $this->clearLocalSession();
                header('Location: /admin?logged_out=1');
                exit;
            }

            // Load SAML settings
            $settings = $this->configManager->buildSettingsArray($idpId);
            $auth = new \OneLogin\Saml2\Auth($settings);

            // Get stored logout request ID
            $requestId = $_SESSION['saml_logout_request_id'] ?? null;

            // Process logout (will clear local session)
            $auth->processSLO(false, $requestId);

            $errors = $auth->getErrors();
            if (!empty($errors)) {
                error_log('SAML SLO errors: ' . implode(', ', $errors));
            }

            // Log logout
            $this->module->logSecurityEvent('logout_success', array(
                'user_id' => $_SESSION['saml_user_id'] ?? null,
                'idp_id' => $idpId,
                'message' => 'SAML logout processed'
            ));

            // Clear session
            $this->clearLocalSession();

            // Redirect to login
            header('Location: /admin?logged_out=1');
            exit;

        } catch (\Exception $e) {
            error_log('SAML SLO processing error: ' . $e->getMessage());
            $this->clearLocalSession();
            header('Location: /admin?slo_error=1');
            exit;
        }
    }

    // =========================================================================
    // Replay Attack Prevention
    // =========================================================================

    /**
     * Check if assertion has been processed
     * @param string $assertionId Assertion ID
     * @return bool True if already processed
     */
    private function isAssertionProcessed($assertionId) {
        $sql = "SELECT assertion_id FROM saml_processed_assertions WHERE assertion_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array($assertionId));
        return $stmt->fetch() !== false;
    }

    /**
     * Store processed assertion ID
     * @param string $assertionId Assertion ID
     * @param string|null $messageId Message ID
     */
    private function storeProcessedAssertion($assertionId, $messageId = null) {
        // Get cache TTL from settings
        $ttl = (int)$this->module->getSetting('assertion_cache_ttl', 3600);
        $expiresAt = time() + $ttl;

        $sql = "INSERT INTO saml_processed_assertions
                (assertion_id, message_id, user_id, processed_at, expires_at, ip_address)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            $assertionId,
            $messageId,
            $_SESSION['saml_user_id'] ?? null,
            time(),
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? null
        ));
    }

    /**
     * Clean expired assertions (should be run via cron)
     */
    public function cleanExpiredAssertions() {
        $sql = "DELETE FROM saml_processed_assertions WHERE expires_at < ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(time()));
        return $stmt->rowCount();
    }

    // =========================================================================
    // User Provisioning
    // =========================================================================

    /**
     * Provision or update user from SAML data
     * @param array $userData User data from SAML
     * @return int User ID
     */
    private function provisionUser($userData) {
        $jitEnabled = $this->module->getSetting('jit_provisioning_enabled', '1') === '1';

        // Map attributes
        $attributeMapper = new SamlAttributeMapper();
        $mappedAttributes = $attributeMapper->mapAttributes($userData['attributes']);

        // Check if user exists by NameID
        $sql = "SELECT user_id FROM saml_user_mappings WHERE saml_nameid = ? AND idp_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array($userData['nameid'], $userData['idp_id']));
        $mapping = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($mapping) {
            // User exists, update mapping
            $userId = $mapping['user_id'];
            $this->updateUserMapping($userId, $userData, $mappedAttributes);
            return $userId;
        }

        // User doesn't exist
        if (!$jitEnabled) {
            throw new \Exception('User not found and JIT provisioning is disabled');
        }

        // Create new user (JIT provisioning)
        return $this->createUserFromSaml($userData, $mappedAttributes);
    }

    /**
     * Create new user from SAML data
     * @param array $userData SAML user data
     * @param array $mappedAttributes Mapped attributes
     * @return int User ID
     */
    private function createUserFromSaml($userData, $mappedAttributes) {
        // Use email as username
        $username = $mappedAttributes['email'];

        // Get default sections
        $defaultSections = $this->module->getSetting('jit_default_sections', '*');

        // Create user in FreePBX userman
        // Note: This is a simplified version. In production, integrate with FreePBX Userman module
        $sql = "INSERT INTO ampusers (username, password_sha1, extension_low, extension_high, deptname, sections)
                VALUES (?, '', '', '', ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            $username,
            trim($mappedAttributes['firstName'] . ' ' . $mappedAttributes['lastName']),
            $defaultSections
        ));

        $userId = $this->db->lastInsertId();

        // Create SAML user mapping
        $sql = "INSERT INTO saml_user_mappings
                (user_id, idp_id, saml_nameid, saml_nameid_format, saml_attributes, last_login, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            $userId,
            $userData['idp_id'],
            $userData['nameid'],
            $userData['nameid_format'],
            json_encode($userData['attributes']),
            time(),
            time()
        ));

        error_log("SAML: Created new user - ID: {$userId}, Email: {$username}");

        return $userId;
    }

    /**
     * Update user SAML mapping
     * @param int $userId User ID
     * @param array $userData SAML user data
     * @param array $mappedAttributes Mapped attributes
     */
    private function updateUserMapping($userId, $userData, $mappedAttributes) {
        $sql = "UPDATE saml_user_mappings SET
                saml_attributes = ?, last_login = ?
                WHERE user_id = ? AND idp_id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            json_encode($userData['attributes']),
            time(),
            $userId,
            $userData['idp_id']
        ));
    }

    // =========================================================================
    // Session Management
    // =========================================================================

    /**
     * Create SAML session record
     * @param int $userId User ID
     * @param int $idpId IdP ID
     * @param string|null $sessionIndex SAML session index
     * @param array $data Session data
     */
    private function createSamlSession($userId, $idpId, $sessionIndex, $data) {
        $sessionTimeout = (int)$this->module->getSetting('session_timeout', 28800);
        $expiresAt = time() + $sessionTimeout;

        $sql = "INSERT INTO saml_sessions
                (session_id, user_id, idp_id, session_index, data, created_at, expires_at, ip_address)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(
            session_id(),
            $userId,
            $idpId,
            $sessionIndex,
            json_encode($data),
            time(),
            $expiresAt,
            $_SERVER['REMOTE_ADDR'] ?? null
        ));
    }

    /**
     * Clear local session and SAML data
     */
    private function clearLocalSession() {
        // Delete SAML session from database
        if (isset($_SESSION['saml_user_id'])) {
            $sql = "DELETE FROM saml_sessions WHERE session_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(session_id()));
        }

        // Clear session variables
        $_SESSION = array();

        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(
                session_name(),
                '',
                time() - 3600,
                '/',
                $_SERVER['HTTP_HOST'] ?? '',
                true,  // secure
                true   // httponly
            );
        }

        // Destroy session
        session_destroy();
    }

    /**
     * Clean expired sessions (should be run via cron)
     */
    public function cleanExpiredSessions() {
        $sql = "DELETE FROM saml_sessions WHERE expires_at < ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array(time()));
        return $stmt->rowCount();
    }

    // =========================================================================
    // Validation Helpers
    // =========================================================================

    /**
     * Validate return URL (prevent open redirect)
     * @param string $url URL to validate
     * @return bool True if valid
     */
    private function isValidReturnUrl($url) {
        // Whitelist of allowed paths
        $allowedPaths = array('/admin', '/ucp');

        // Must start with allowed path
        foreach ($allowedPaths as $path) {
            if (strpos($url, $path) === 0) {
                return true;
            }
        }

        // Also allow relative URLs
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) {
            return true;
        }

        return false;
    }
}
