<?php
namespace FreePBX\modules\Samlauth;

/**
 * SAML Configuration Manager
 *
 * Handles building onelogin/php-saml settings arrays
 * with proper security configuration
 */
class SamlConfigManager {

    private $db;
    private $module;

    public function __construct($db, $module) {
        $this->db = $db;
        $this->module = $module;
    }

    /**
     * Build settings array for onelogin/php-saml
     * @param int|null $idpId IdP ID (optional - can generate SP-only metadata)
     * @return array Settings array
     */
    public function buildSettingsArray($idpId = null) {
        // Get IdP configuration
        $idp = null;
        if ($idpId) {
            $idp = $this->module->getIdpConfig($idpId);
            if (!$idp) {
                throw new \Exception("IdP not found: {$idpId}");
            }
        } else {
            // Try to get first enabled IdP (optional for SP metadata)
            $idp = $this->module->getEnabledIdp();
        }

        // Build SP base URL
        $spBaseUrl = $this->getSpBaseUrl();

        // Load SP certificates
        $certDir = '/etc/freepbx/saml/certs';
        $spCert = '';
        $spKey = '';

        if (file_exists($certDir . '/sp.crt')) {
            $spCert = file_get_contents($certDir . '/sp.crt');
        }
        if (file_exists($certDir . '/sp.key')) {
            $spKey = file_get_contents($certDir . '/sp.key');
        }

        // Build settings array
        $settings = array(
            // REQUIRED: Enable strict mode for security (except for SP-only metadata)
            'strict' => ($idp !== null),

            // Disable debug in production
            'debug' => false,

            // Service Provider configuration
            'sp' => array(
                'entityId' => $spBaseUrl . '/admin/modules/samlauth/endpoints/metadata.php',
                'assertionConsumerService' => array(
                    'url' => $spBaseUrl . '/admin/modules/samlauth/endpoints/acs.php' . ($idp ? '?idp=' . $idp['id'] : ''),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
                ),
                'singleLogoutService' => array(
                    'url' => $spBaseUrl . '/admin/modules/samlauth/endpoints/sls.php' . ($idp ? '?idp=' . $idp['id'] : ''),
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ),
                'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            ),

            // Security settings (CRITICAL)
            'security' => array(
                // Signature requirements
                'nameIdEncrypted' => false,
                'authnRequestsSigned' => !empty($spKey), // Only if we have SP key
                'logoutRequestSigned' => !empty($spKey),
                'logoutResponseSigned' => !empty($spKey),

                // Validation requirements (REQUIRED when IdP configured)
                'wantMessagesSigned' => ($idp !== null),
                'wantAssertionsSigned' => ($idp !== null),
                'wantAssertionsEncrypted' => false,
                'wantNameId' => ($idp !== null),
                'wantXMLValidation' => ($idp !== null),

                // Enhanced security (REQUIRED when IdP configured)
                'rejectUnsolicitedResponsesWithInResponseTo' => ($idp !== null),
                'destinationStrictlyMatches' => ($idp !== null),
                'rejectDeprecatedAlgorithm' => true,

                // Algorithms (REQUIRED: Use SHA256 or better)
                'signatureAlgorithm' => 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256',
                'digestAlgorithm' => 'http://www.w3.org/2001/04/xmlenc#sha256',

                'lowercaseUrlencoding' => false,
            ),

            // Contact information
            'contactPerson' => array(
                'technical' => array(
                    'givenName' => 'IT Support',
                    'emailAddress' => 'support@example.com',
                ),
            ),

            // Organization information
            'organization' => array(
                'en-US' => array(
                    'name' => 'FreePBX',
                    'displayname' => 'FreePBX System',
                    'url' => $spBaseUrl,
                ),
            ),
        );

        // Add SP certificates if available
        if ($spCert && $spKey) {
            $settings['sp']['x509cert'] = $this->formatCertificate($spCert);
            $settings['sp']['privateKey'] = $spKey;
        }

        // Add IdP configuration if available
        if ($idp) {
            $settings['idp'] = array(
                'entityId' => $idp['entity_id'],
                'singleSignOnService' => array(
                    'url' => $idp['sso_url'],
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ),
                'x509cert' => $this->formatCertificate($idp['certificate']),
            );

            // Add SLO URL if configured
            if (!empty($idp['slo_url'])) {
                $settings['idp']['singleLogoutService'] = array(
                    'url' => $idp['slo_url'],
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                );
            }

            // Support certificate rollover if new certificate provided
            if (!empty($idp['certificate_new'])) {
                $settings['idp']['x509certMulti'] = array(
                    'signing' => array(
                        0 => $this->formatCertificate($idp['certificate']),
                        1 => $this->formatCertificate($idp['certificate_new']),
                    ),
                );
            }

            // Merge custom settings if provided
            if (!empty($idp['settings'])) {
                $customSettings = json_decode($idp['settings'], true);
                if ($customSettings) {
                    $settings = array_replace_recursive($settings, $customSettings);
                }
            }
        } else {
            // No IdP configured - provide minimal IdP config for SP metadata generation
            // Use SP cert as placeholder (this is only for generating SP metadata, not for actual authentication)
            $placeholderCert = $spCert ? $this->formatCertificate($spCert) : '';
            $settings['idp'] = array(
                'entityId' => 'urn:placeholder:idp',
                'singleSignOnService' => array(
                    'url' => 'https://placeholder.example.com/sso',
                    'binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
                ),
                'x509cert' => $placeholderCert,
            );
        }

        return $settings;
    }

    /**
     * Get SP base URL
     * @return string Base URL
     */
    private function getSpBaseUrl() {
        // Determine protocol
        $protocol = 'https'; // SAML MUST use HTTPS

        // For development/testing, check if HTTP is being used
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'off') {
            error_log('WARNING: SAML over HTTP is insecure. Use HTTPS in production!');
            $protocol = 'http';
        }

        // Get host
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . '://' . $host;
    }

    /**
     * Format certificate string
     * Removes headers/footers and whitespace
     * @param string $cert Certificate string
     * @return string Formatted certificate
     */
    private function formatCertificate($cert) {
        // Remove headers, footers, and whitespace
        $cert = str_replace('-----BEGIN CERTIFICATE-----', '', $cert);
        $cert = str_replace('-----END CERTIFICATE-----', '', $cert);
        $cert = str_replace("\r", '', $cert);
        $cert = str_replace("\n", '', $cert);
        $cert = str_replace(' ', '', $cert);

        return $cert;
    }

    /**
     * Validate IdP configuration
     * @param array $config IdP configuration
     * @return array Array of validation errors (empty if valid)
     */
    public function validateIdpConfig($config) {
        $errors = array();

        // Required fields
        if (empty($config['name'])) {
            $errors[] = 'Name is required';
        }

        if (empty($config['entity_id'])) {
            $errors[] = 'Entity ID is required';
        }

        if (empty($config['sso_url'])) {
            $errors[] = 'SSO URL is required';
        } elseif (!filter_var($config['sso_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'SSO URL must be a valid URL';
        }

        if (!empty($config['slo_url']) && !filter_var($config['slo_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'SLO URL must be a valid URL';
        }

        if (empty($config['certificate'])) {
            $errors[] = 'Certificate is required';
        } else {
            // Validate certificate format
            $cert = $this->formatCertificate($config['certificate']);
            $certWithHeaders = "-----BEGIN CERTIFICATE-----\n" .
                             chunk_split($cert, 64) .
                             "-----END CERTIFICATE-----";

            $certData = openssl_x509_parse($certWithHeaders);
            if (!$certData) {
                $errors[] = 'Invalid certificate format';
            } else {
                // Check if certificate is expired
                if ($certData['validTo_time_t'] < time()) {
                    $errors[] = 'Certificate has expired';
                }

                // Warn if expiring soon (30 days)
                $daysUntilExpiry = round(($certData['validTo_time_t'] - time()) / 86400);
                if ($daysUntilExpiry < 30 && $daysUntilExpiry > 0) {
                    $errors[] = "Warning: Certificate expires in {$daysUntilExpiry} days";
                }
            }
        }

        return $errors;
    }
}
