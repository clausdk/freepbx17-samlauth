<!-- SAML Settings Page -->
<div class="container-fluid">
    <h1><?php echo _("SAML Settings"); ?></h1>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation">
            <a href="?display=samlauth&view=main"><?php echo _("Identity Providers"); ?></a>
        </li>
        <li role="presentation" class="active">
            <a href="?display=samlauth&view=settings"><?php echo _("Settings"); ?></a>
        </li>
        <li role="presentation">
            <a href="?display=samlauth&view=certificates"><?php echo _("Certificates"); ?></a>
        </li>
        <li role="presentation">
            <a href="?display=samlauth&view=logs"><?php echo _("Security Logs"); ?></a>
        </li>
    </ul>

    <div class="tab-content" style="padding: 20px 0;">
        <form method="POST" action="?display=samlauth&view=settings" class="form-horizontal">
            <input type="hidden" name="action" value="save_settings">

            <!-- User Provisioning Settings -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4><?php echo _("User Provisioning"); ?></h4>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">
                            <?php echo _("Just-in-Time (JIT) Provisioning"); ?>
                        </label>
                        <div class="col-sm-8">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="jit_provisioning_enabled" value="1"
                                           <?php echo $module->getSetting('jit_provisioning_enabled', '1') === '1' ? 'checked' : ''; ?>>
                                    <?php echo _("Automatically create users from SAML assertions"); ?>
                                </label>
                            </div>
                            <p class="help-block">
                                <?php echo _("When enabled, users will be automatically created on first SAML login. When disabled, users must be pre-created in FreePBX."); ?>
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-4 control-label" for="jit_default_sections">
                            <?php echo _("Default Sections for New Users"); ?>
                        </label>
                        <div class="col-sm-8">
                            <input type="text" name="jit_default_sections" id="jit_default_sections"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($module->getSetting('jit_default_sections', '*')); ?>">
                            <p class="help-block">
                                <?php echo _("Comma-separated list of sections new users can access. Use '*' for all sections."); ?><br>
                                <?php echo _("Examples: *, settings,reports, extensions,queues"); ?>
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-4 control-label" for="allowed_email_domains">
                            <?php echo _("Allowed Email Domains"); ?>
                        </label>
                        <div class="col-sm-8">
                            <input type="text" name="allowed_email_domains" id="allowed_email_domains"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($module->getSetting('allowed_email_domains', '')); ?>"
                                   placeholder="example.com, company.com">
                            <p class="help-block">
                                <?php echo _("Restrict JIT provisioning to specific email domains (comma-separated). Leave empty to allow all domains."); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Session Settings -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4><?php echo _("Session Settings"); ?></h4>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-sm-4 control-label" for="session_timeout">
                            <?php echo _("Session Timeout (seconds)"); ?>
                        </label>
                        <div class="col-sm-8">
                            <input type="number" name="session_timeout" id="session_timeout"
                                   class="form-control"
                                   value="<?php echo $module->getSetting('session_timeout', '28800'); ?>"
                                   min="300" max="86400">
                            <p class="help-block">
                                <?php echo _("How long SAML sessions remain valid (default: 28800 seconds = 8 hours)"); ?><br>
                                <?php echo _("Minimum: 300 seconds (5 minutes), Maximum: 86400 seconds (24 hours)"); ?>
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-4 control-label" for="assertion_cache_ttl">
                            <?php echo _("Assertion Cache TTL (seconds)"); ?>
                        </label>
                        <div class="col-sm-8">
                            <input type="number" name="assertion_cache_ttl" id="assertion_cache_ttl"
                                   class="form-control"
                                   value="<?php echo $module->getSetting('assertion_cache_ttl', '3600'); ?>"
                                   min="300" max="7200">
                            <p class="help-block">
                                <?php echo _("How long to cache processed assertion IDs for replay attack prevention (default: 3600 seconds = 1 hour)"); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security & Logging -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4><?php echo _("Security & Logging"); ?></h4>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-sm-4 control-label" for="log_retention_days">
                            <?php echo _("Security Log Retention (days)"); ?>
                        </label>
                        <div class="col-sm-8">
                            <input type="number" name="log_retention_days" id="log_retention_days"
                                   class="form-control"
                                   value="<?php echo $module->getSetting('log_retention_days', '90'); ?>"
                                   min="7" max="365">
                            <p class="help-block">
                                <?php echo _("How long to keep security audit logs (default: 90 days)"); ?>
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-4 control-label" for="alert_email">
                            <?php echo _("Security Alert Email"); ?>
                        </label>
                        <div class="col-sm-8">
                            <input type="email" name="alert_email" id="alert_email"
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($module->getSetting('alert_email', '')); ?>"
                                   placeholder="security@example.com">
                            <p class="help-block">
                                <?php echo _("Email address for security alerts (certificate expiration, replay attacks, etc.)"); ?>
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-4 control-label">
                            <?php echo _("Log Failed Logins"); ?>
                        </label>
                        <div class="col-sm-8">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="log_failed_logins" value="1"
                                           <?php echo $module->getSetting('log_failed_logins', '1') === '1' ? 'checked' : ''; ?>>
                                    <?php echo _("Log all failed authentication attempts"); ?>
                                </label>
                            </div>
                            <p class="help-block">
                                <?php echo _("Recommended for security monitoring and intrusion detection"); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Settings -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h4><?php echo _("Advanced Settings"); ?></h4>
                </div>
                <div class="panel-body">
                    <div class="form-group">
                        <label class="col-sm-4 control-label">
                            <?php echo _("Force HTTPS"); ?>
                        </label>
                        <div class="col-sm-8">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="force_https" value="1"
                                           <?php echo $module->getSetting('force_https', '1') === '1' ? 'checked' : ''; ?>>
                                    <?php echo _("Require HTTPS for all SAML endpoints"); ?>
                                </label>
                            </div>
                            <p class="help-block">
                                <?php echo _("STRONGLY RECOMMENDED: SAML should always use HTTPS in production"); ?>
                            </p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-sm-4 control-label">
                            <?php echo _("Debug Mode"); ?>
                        </label>
                        <div class="col-sm-8">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="debug_mode" value="1"
                                           <?php echo $module->getSetting('debug_mode', '0') === '1' ? 'checked' : ''; ?>>
                                    <?php echo _("Enable verbose SAML debugging"); ?>
                                </label>
                            </div>
                            <p class="help-block">
                                <span class="text-warning">
                                    <i class="fa fa-warning"></i>
                                    <?php echo _("WARNING: Only enable temporarily for troubleshooting. Logs sensitive SAML data."); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="form-group">
                <div class="col-sm-offset-4 col-sm-8">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> <?php echo _("Save Settings"); ?>
                    </button>
                    <a href="?display=samlauth" class="btn btn-default">
                        <i class="fa fa-times"></i> <?php echo _("Cancel"); ?>
                    </a>
                </div>
            </div>
        </form>

        <!-- Current Settings Summary -->
        <div class="panel panel-info">
            <div class="panel-heading">
                <h4><?php echo _("Current Configuration Summary"); ?></h4>
            </div>
            <div class="panel-body">
                <dl class="dl-horizontal">
                    <dt><?php echo _("JIT Provisioning"); ?>:</dt>
                    <dd>
                        <?php echo $module->getSetting('jit_provisioning_enabled', '1') === '1' ?
                            '<span class="label label-success">' . _("Enabled") . '</span>' :
                            '<span class="label label-default">' . _("Disabled") . '</span>';
                        ?>
                    </dd>

                    <dt><?php echo _("Session Timeout"); ?>:</dt>
                    <dd><?php echo $module->getSetting('session_timeout', '28800'); ?> seconds
                        (<?php echo round($module->getSetting('session_timeout', '28800') / 3600, 1); ?> hours)
                    </dd>

                    <dt><?php echo _("Log Retention"); ?>:</dt>
                    <dd><?php echo $module->getSetting('log_retention_days', '90'); ?> days</dd>

                    <dt><?php echo _("HTTPS Enforced"); ?>:</dt>
                    <dd>
                        <?php echo $module->getSetting('force_https', '1') === '1' ?
                            '<span class="label label-success">' . _("Yes") . '</span>' :
                            '<span class="label label-warning">' . _("No") . '</span>';
                        ?>
                    </dd>

                    <dt><?php echo _("Debug Mode"); ?>:</dt>
                    <dd>
                        <?php echo $module->getSetting('debug_mode', '0') === '1' ?
                            '<span class="label label-warning">' . _("Enabled") . '</span>' :
                            '<span class="label label-default">' . _("Disabled") . '</span>';
                        ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>
