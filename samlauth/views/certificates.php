<!-- Certificates Management Page -->
<?php
require_once __DIR__ . '/../classes/CertificateManager.php';
$certManager = new \FreePBX\modules\Samlauth\CertificateManager();
$certsExist = $certManager->certificatesExist();
$certInfo = $certsExist ? $certManager->getCertificateInfo() : null;
?>

<div class="container-fluid">
    <h1><?php echo _("Certificate Management"); ?></h1>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation">
            <a href="?display=samlauth&view=main"><?php echo _("Identity Providers"); ?></a>
        </li>
        <li role="presentation">
            <a href="?display=samlauth&view=settings"><?php echo _("Settings"); ?></a>
        </li>
        <li role="presentation" class="active">
            <a href="?display=samlauth&view=certificates"><?php echo _("Certificates"); ?></a>
        </li>
        <li role="presentation">
            <a href="?display=samlauth&view=logs"><?php echo _("Security Logs"); ?></a>
        </li>
    </ul>

    <div class="tab-content" style="padding: 20px 0;">

        <!-- SP Certificate Status -->
        <div class="panel panel-<?php echo $certsExist ? 'success' : 'warning'; ?>">
            <div class="panel-heading">
                <h4>
                    <?php if ($certsExist): ?>
                        <i class="fa fa-check-circle"></i> <?php echo _("Service Provider Certificate"); ?>
                    <?php else: ?>
                        <i class="fa fa-exclamation-triangle"></i> <?php echo _("No Certificate Generated"); ?>
                    <?php endif; ?>
                </h4>
            </div>
            <div class="panel-body">
                <?php if ($certsExist && $certInfo): ?>
                    <!-- Certificate Information -->
                    <div class="row">
                        <div class="col-md-6">
                            <h5><?php echo _("Certificate Details"); ?></h5>
                            <dl class="dl-horizontal">
                                <dt><?php echo _("Subject"); ?>:</dt>
                                <dd><?php echo htmlspecialchars($certInfo['subject']['CN'] ?? 'N/A'); ?></dd>

                                <dt><?php echo _("Valid From"); ?>:</dt>
                                <dd><?php echo $certInfo['validFrom']; ?></dd>

                                <dt><?php echo _("Valid To"); ?>:</dt>
                                <dd><?php echo $certInfo['validTo']; ?></dd>

                                <dt><?php echo _("Days Until Expiry"); ?>:</dt>
                                <dd>
                                    <?php
                                    $daysLeft = $certInfo['daysUntilExpiry'];
                                    if ($daysLeft < 0) {
                                        echo '<span class="label label-danger">' . _("EXPIRED") . '</span>';
                                    } elseif ($daysLeft < 30) {
                                        echo '<span class="label label-warning">' . $daysLeft . ' days</span>';
                                    } else {
                                        echo '<span class="label label-success">' . $daysLeft . ' days</span>';
                                    }
                                    ?>
                                </dd>

                                <dt><?php echo _("Serial Number"); ?>:</dt>
                                <dd><code><?php echo $certInfo['serialNumber']; ?></code></dd>

                                <dt><?php echo _("Signature Algorithm"); ?>:</dt>
                                <dd><?php echo $certInfo['signatureAlgorithm']; ?></dd>

                                <dt><?php echo _("Fingerprint (SHA256)"); ?>:</dt>
                                <dd><code><?php echo $certManager->getCertificateFingerprint('sha256'); ?></code></dd>
                            </dl>
                        </div>

                        <div class="col-md-6">
                            <h5><?php echo _("Certificate Actions"); ?></h5>
                            <div class="btn-group-vertical" style="width: 100%; margin-bottom: 15px;">
                                <button type="button" class="btn btn-warning" id="generate-certificates">
                                    <i class="fa fa-refresh"></i> <?php echo _("Regenerate Certificates"); ?>
                                </button>
                                <a href="/etc/freepbx/saml/certs/sp.crt" download class="btn btn-default">
                                    <i class="fa fa-download"></i> <?php echo _("Download Certificate (.crt)"); ?>
                                </a>
                            </div>

                            <?php if ($daysLeft < 30 && $daysLeft > 0): ?>
                            <div class="alert alert-warning">
                                <i class="fa fa-warning"></i>
                                <strong><?php echo _("Certificate Expiring Soon!"); ?></strong><br>
                                <?php echo sprintf(_("Your SP certificate will expire in %d days. Please regenerate soon."), $daysLeft); ?>
                            </div>
                            <?php elseif ($daysLeft < 0): ?>
                            <div class="alert alert-danger">
                                <i class="fa fa-exclamation-circle"></i>
                                <strong><?php echo _("Certificate Expired!"); ?></strong><br>
                                <?php echo _("Your SP certificate has expired. Please regenerate immediately."); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- No Certificate -->
                    <div class="alert alert-warning">
                        <h4><?php echo _("No SP Certificate Found"); ?></h4>
                        <p><?php echo _("You need to generate a Service Provider certificate before you can use SAML authentication."); ?></p>
                        <button type="button" class="btn btn-primary btn-lg" id="generate-certificates">
                            <i class="fa fa-key"></i> <?php echo _("Generate Certificates Now"); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- SP Metadata -->
        <div class="panel panel-info">
            <div class="panel-heading">
                <h4><?php echo _("Service Provider Metadata"); ?></h4>
            </div>
            <div class="panel-body">
                <p><?php echo _("Provide this information to your Identity Provider:"); ?></p>

                <?php
                $protocol = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') ? 'http' : 'https';
                $host = $_SERVER['HTTP_HOST'];
                $metadataUrl = "{$protocol}://{$host}/admin/modules/samlauth/endpoints/metadata.php";
                $acsUrl = "{$protocol}://{$host}/admin/modules/samlauth/endpoints/acs.php";
                $slsUrl = "{$protocol}://{$host}/admin/modules/samlauth/endpoints/sls.php";
                ?>

                <dl class="dl-horizontal">
                    <dt><?php echo _("SP Entity ID"); ?>:</dt>
                    <dd>
                        <code><?php echo $metadataUrl; ?></code>
                        <button class="btn btn-xs btn-default copy-to-clipboard" data-text="<?php echo $metadataUrl; ?>">
                            <i class="fa fa-copy"></i>
                        </button>
                    </dd>

                    <dt><?php echo _("ACS URL"); ?>:</dt>
                    <dd>
                        <code><?php echo $acsUrl; ?></code>
                        <button class="btn btn-xs btn-default copy-to-clipboard" data-text="<?php echo $acsUrl; ?>">
                            <i class="fa fa-copy"></i>
                        </button>
                    </dd>

                    <dt><?php echo _("SLS URL"); ?>:</dt>
                    <dd>
                        <code><?php echo $slsUrl; ?></code>
                        <button class="btn btn-xs btn-default copy-to-clipboard" data-text="<?php echo $slsUrl; ?>">
                            <i class="fa fa-copy"></i>
                        </button>
                    </dd>

                    <dt><?php echo _("NameID Format"); ?>:</dt>
                    <dd><code>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</code></dd>
                </dl>

                <div class="btn-group" style="margin-top: 15px;">
                    <a href="<?php echo $metadataUrl; ?>" target="_blank" class="btn btn-primary">
                        <i class="fa fa-file-code-o"></i> <?php echo _("View SP Metadata XML"); ?>
                    </a>
                    <a href="<?php echo $metadataUrl; ?>" download="freepbx-sp-metadata.xml" class="btn btn-default">
                        <i class="fa fa-download"></i> <?php echo _("Download Metadata"); ?>
                    </a>
                </div>

                <?php if ($protocol === 'http'): ?>
                <div class="alert alert-danger" style="margin-top: 15px;">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong><?php echo _("WARNING"); ?>:</strong>
                    <?php echo _("You are accessing FreePBX over HTTP. SAML requires HTTPS in production for security. These URLs will not work with most Identity Providers."); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Certificate Generation Options -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><?php echo _("Certificate Generation Options"); ?></h4>
            </div>
            <div class="panel-body">
                <form method="POST" action="?display=samlauth&view=certificates" class="form-horizontal" id="cert-options-form">
                    <input type="hidden" name="action" value="generate_certificates">

                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="cert_cn">
                            <?php echo _("Common Name (CN)"); ?>
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="cert_cn" id="cert_cn" class="form-control"
                                   value="<?php echo $_SERVER['HTTP_HOST'] ?? 'freepbx.local'; ?>">
                            <p class="help-block">
                                <?php echo _("Usually your FreePBX hostname"); ?>
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="cert_org">
                            <?php echo _("Organization"); ?>
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="cert_org" id="cert_org" class="form-control"
                                   value="FreePBX">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="cert_country">
                            <?php echo _("Country Code"); ?>
                        </label>
                        <div class="col-sm-9">
                            <input type="text" name="cert_country" id="cert_country" class="form-control"
                                   value="US" maxlength="2" pattern="[A-Z]{2}">
                            <p class="help-block">
                                <?php echo _("2-letter country code (e.g., US, GB, CA)"); ?>
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="cert_validity">
                            <?php echo _("Validity (days)"); ?>
                        </label>
                        <div class="col-sm-9">
                            <input type="number" name="cert_validity" id="cert_validity" class="form-control"
                                   value="3650" min="365" max="7300">
                            <p class="help-block">
                                <?php echo _("Default: 3650 days (10 years)"); ?>
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-9">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i>
                                <?php echo _("Certificates are 4096-bit RSA with SHA-256 signature algorithm. Private keys are stored securely in /etc/freepbx/saml/certs/ with restricted permissions."); ?>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Certificate Security Tips -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><?php echo _("Certificate Security Best Practices"); ?></h4>
            </div>
            <div class="panel-body">
                <ul>
                    <li><?php echo _("Keep your private key secure and never share it"); ?></li>
                    <li><?php echo _("Backup your certificates regularly (stored in /etc/freepbx/saml/certs/)"); ?></li>
                    <li><?php echo _("Monitor certificate expiration dates (automated alerts enabled)"); ?></li>
                    <li><?php echo _("Regenerate certificates at least 30 days before expiration"); ?></li>
                    <li><?php echo _("After regenerating, update the metadata in all configured Identity Providers"); ?></li>
                    <li><?php echo _("Use CA-signed certificates for production (self-signed are OK for development)"); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
// Copy to clipboard functionality
$('.copy-to-clipboard').on('click', function() {
    var text = $(this).data('text');
    var $temp = $('<input>');
    $('body').append($temp);
    $temp.val(text).select();
    document.execCommand('copy');
    $temp.remove();

    var $btn = $(this);
    var originalHtml = $btn.html();
    $btn.html('<i class="fa fa-check"></i> Copied!');
    setTimeout(function() {
        $btn.html(originalHtml);
    }, 2000);
});
</script>
