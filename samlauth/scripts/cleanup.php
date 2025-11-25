<?php
/**
 * SAML Cleanup Script
 *
 * Maintenance script to clean up expired data
 * Run via cron: star-slash-5 * * * * /usr/bin/php /var/www/html/admin/modules/samlauth/scripts/cleanup.php
 * (Replace star-slash with the actual characters)
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

    // Load auth handler for cleanup methods
    require_once __DIR__ . '/../classes/SamlAuthHandler.php';
    require_once __DIR__ . '/../classes/SamlConfigManager.php';
    require_once __DIR__ . '/../classes/SamlAttributeMapper.php';

    $authHandler = new \FreePBX\modules\Samlauth\SamlAuthHandler($db, $samlModule);

    $output = array();

    // Clean expired assertions (replay attack prevention)
    $deletedAssertions = $authHandler->cleanExpiredAssertions();
    $output[] = "Cleaned {$deletedAssertions} expired assertions";

    // Clean expired sessions
    $deletedSessions = $authHandler->cleanExpiredSessions();
    $output[] = "Cleaned {$deletedSessions} expired sessions";

    // Archive old security logs (keep 90 days)
    $logRetentionDays = (int)$samlModule->getSetting('log_retention_days', 90);
    $cutoffTime = time() - ($logRetentionDays * 86400);

    $sql = "DELETE FROM saml_security_log WHERE created_at < ?";
    $stmt = $db->prepare($sql);
    $stmt->execute(array($cutoffTime));
    $deletedLogs = $stmt->rowCount();
    $output[] = "Archived {$deletedLogs} old security log entries";

    // Check for expiring certificates
    require_once __DIR__ . '/../classes/CertificateManager.php';
    $certManager = new \FreePBX\modules\Samlauth\CertificateManager();

    if ($certManager->certificatesExist()) {
        $certInfo = $certManager->getCertificateInfo();

        if ($certInfo && $certInfo['daysUntilExpiry'] < 30 && $certInfo['daysUntilExpiry'] > 0) {
            $daysLeft = $certInfo['daysUntilExpiry'];
            $message = "WARNING: SP certificate expires in {$daysLeft} days!";
            $output[] = $message;

            // Log warning
            $samlModule->logSecurityEvent('certificate_expiring', array(
                'message' => $message,
                'days_until_expiry' => $daysLeft,
                'expiry_date' => $certInfo['validTo']
            ));

            // Send email alert if configured
            $alertEmail = $samlModule->getSetting('alert_email');
            if ($alertEmail) {
                $subject = "FreePBX SAML: Certificate Expiring Soon";
                $body = "The SAML SP certificate will expire in {$daysLeft} days.\n";
                $body .= "Expiry Date: {$certInfo['validTo']}\n\n";
                $body .= "Please generate a new certificate and update your IdP configuration.";

                mail($alertEmail, $subject, $body);
                $output[] = "Sent expiry alert to {$alertEmail}";
            }
        }

        if ($certInfo && $certInfo['daysUntilExpiry'] < 0) {
            $message = "ERROR: SP certificate has EXPIRED!";
            $output[] = $message;

            $samlModule->logSecurityEvent('certificate_expired', array(
                'message' => $message,
                'expiry_date' => $certInfo['validTo']
            ));
        }
    }

    // Check IdP certificate expiration
    $idps = $samlModule->getAllIdpConfigs();
    foreach ($idps as $idp) {
        if ($idp['enabled']) {
            $cert = "-----BEGIN CERTIFICATE-----\n" .
                    chunk_split($idp['certificate'], 64) .
                    "-----END CERTIFICATE-----";

            $certData = openssl_x509_parse($cert);
            if ($certData) {
                $daysUntilExpiry = round(($certData['validTo_time_t'] - time()) / 86400);

                if ($daysUntilExpiry < 30 && $daysUntilExpiry > 0) {
                    $message = "IdP '{$idp['name']}' certificate expires in {$daysUntilExpiry} days";
                    $output[] = "WARNING: {$message}";

                    $samlModule->logSecurityEvent('idp_certificate_expiring', array(
                        'idp_id' => $idp['id'],
                        'message' => $message,
                        'days_until_expiry' => $daysUntilExpiry
                    ));
                }

                if ($daysUntilExpiry < 0) {
                    $message = "IdP '{$idp['name']}' certificate has EXPIRED";
                    $output[] = "ERROR: {$message}";

                    $samlModule->logSecurityEvent('idp_certificate_expired', array(
                        'idp_id' => $idp['id'],
                        'message' => $message
                    ));
                }
            }
        }
    }

    // Output results
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] SAML Cleanup completed:\n";
    foreach ($output as $line) {
        echo "  - {$line}\n";
    }

    exit(0);

} catch (Exception $e) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] SAML Cleanup ERROR: " . $e->getMessage() . "\n";
    error_log('SAML cleanup error: ' . $e->getMessage());
    exit(1);
}
