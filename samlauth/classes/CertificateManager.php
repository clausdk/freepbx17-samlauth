<?php
namespace FreePBX\modules\Samlauth;

/**
 * Certificate Manager
 *
 * Handles auto-generation and management of SP certificates
 */
class CertificateManager {

    private $certDir = '/etc/freepbx/saml/certs';

    /**
     * Generate SP certificates
     * @param array $options Certificate options
     * @return bool Success status
     */
    public function generateCertificates($options = array()) {
        // Ensure certificate directory exists
        if (!is_dir($this->certDir)) {
            mkdir($this->certDir, 0700, true);
        }

        // Set default options
        $defaults = array(
            'countryName' => 'US',
            'stateOrProvinceName' => 'State',
            'localityName' => 'City',
            'organizationName' => 'FreePBX',
            'organizationalUnitName' => 'IT',
            'commonName' => $_SERVER['HTTP_HOST'] ?? 'freepbx.local',
            'emailAddress' => 'admin@' . ($_SERVER['HTTP_HOST'] ?? 'freepbx.local'),
            'validDays' => 3650, // 10 years
        );

        $options = array_merge($defaults, $options);

        // Generate private key
        $privateKey = openssl_pkey_new(array(
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ));

        if (!$privateKey) {
            throw new \Exception('Failed to generate private key: ' . openssl_error_string());
        }

        // Generate certificate signing request
        $dn = array(
            'countryName' => $options['countryName'],
            'stateOrProvinceName' => $options['stateOrProvinceName'],
            'localityName' => $options['localityName'],
            'organizationName' => $options['organizationName'],
            'organizationalUnitName' => $options['organizationalUnitName'],
            'commonName' => $options['commonName'],
            'emailAddress' => $options['emailAddress'],
        );

        $csr = openssl_csr_new($dn, $privateKey, array(
            'digest_alg' => 'sha256'
        ));

        if (!$csr) {
            throw new \Exception('Failed to generate CSR: ' . openssl_error_string());
        }

        // Generate self-signed certificate
        $cert = openssl_csr_sign(
            $csr,
            null,
            $privateKey,
            $options['validDays'],
            array('digest_alg' => 'sha256')
        );

        if (!$cert) {
            throw new \Exception('Failed to generate certificate: ' . openssl_error_string());
        }

        // Export private key
        $privateKeyPem = '';
        openssl_pkey_export($privateKey, $privateKeyPem);

        // Export certificate
        $certPem = '';
        openssl_x509_export($cert, $certPem);

        // Backup existing certificates if they exist
        if (file_exists($this->certDir . '/sp.crt')) {
            $backupSuffix = date('Y-m-d_H-i-s');
            copy($this->certDir . '/sp.crt', $this->certDir . '/sp.crt.backup.' . $backupSuffix);
            copy($this->certDir . '/sp.key', $this->certDir . '/sp.key.backup.' . $backupSuffix);
        }

        // Write certificate and private key to files
        file_put_contents($this->certDir . '/sp.crt', $certPem);
        file_put_contents($this->certDir . '/sp.key', $privateKeyPem);

        // Set proper permissions
        chmod($this->certDir . '/sp.crt', 0600);
        chmod($this->certDir . '/sp.key', 0600);

        // Set ownership to asterisk user if running as root
        if (function_exists('posix_getuid') && posix_getuid() === 0) {
            chown($this->certDir . '/sp.crt', 'asterisk');
            chown($this->certDir . '/sp.key', 'asterisk');
            chgrp($this->certDir . '/sp.crt', 'asterisk');
            chgrp($this->certDir . '/sp.key', 'asterisk');
        }

        // Note: In PHP 8.x, openssl_pkey_free() and openssl_x509_free() are deprecated
        // Resource cleanup is handled automatically by PHP's garbage collector

        error_log('SAML: SP certificates generated successfully');

        return true;
    }

    /**
     * Check if certificates exist
     * @return bool True if certificates exist
     */
    public function certificatesExist() {
        return file_exists($this->certDir . '/sp.crt') &&
               file_exists($this->certDir . '/sp.key');
    }

    /**
     * Get certificate information
     * @return array|false Certificate info or false if not found
     */
    public function getCertificateInfo() {
        $certFile = $this->certDir . '/sp.crt';

        if (!file_exists($certFile)) {
            return false;
        }

        $certContent = file_get_contents($certFile);
        $certData = openssl_x509_parse($certContent);

        if (!$certData) {
            return false;
        }

        return array(
            'subject' => $certData['subject'],
            'issuer' => $certData['issuer'],
            'validFrom' => date('Y-m-d H:i:s', $certData['validFrom_time_t']),
            'validTo' => date('Y-m-d H:i:s', $certData['validTo_time_t']),
            'daysUntilExpiry' => round(($certData['validTo_time_t'] - time()) / 86400),
            'serialNumber' => $certData['serialNumber'],
            'signatureAlgorithm' => $certData['signatureTypeSN'],
        );
    }

    /**
     * Check if certificate is expiring soon
     * @param int $warningDays Days before expiration to warn
     * @return bool True if expiring soon
     */
    public function isExpiringSoon($warningDays = 30) {
        $info = $this->getCertificateInfo();

        if (!$info) {
            return false;
        }

        return $info['daysUntilExpiry'] < $warningDays && $info['daysUntilExpiry'] > 0;
    }

    /**
     * Check if certificate is expired
     * @return bool True if expired
     */
    public function isExpired() {
        $info = $this->getCertificateInfo();

        if (!$info) {
            return true; // No cert = expired
        }

        return $info['daysUntilExpiry'] < 0;
    }

    /**
     * Get certificate fingerprint
     * @param string $algorithm Hash algorithm (sha1, sha256, md5)
     * @return string|false Certificate fingerprint or false on error
     */
    public function getCertificateFingerprint($algorithm = 'sha256') {
        $certFile = $this->certDir . '/sp.crt';

        if (!file_exists($certFile)) {
            return false;
        }

        $certContent = file_get_contents($certFile);
        $cert = openssl_x509_read($certContent);

        if (!$cert) {
            return false;
        }

        openssl_x509_export($cert, $certPem);
        $fingerprint = openssl_x509_fingerprint($cert, $algorithm);

        // Note: openssl_x509_free() is deprecated in PHP 8.x
        // Resource cleanup is handled automatically

        return $fingerprint;
    }
}
