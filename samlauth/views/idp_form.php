<!-- IdP Add/Edit Form -->
<div class="container-fluid">
    <?php
    $isEdit = ($idp !== null && $idp !== false);
    $pageTitle = $isEdit ? _("Edit Identity Provider") : _("Add Identity Provider");
    ?>

    <h1><?php echo $pageTitle; ?></h1>

    <form method="POST" action="?display=samlauth" id="idp-form" class="form-horizontal">
        <input type="hidden" name="action" value="save_idp">
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?php echo $idp['id']; ?>">
        <?php endif; ?>

        <!-- Provider Type Selection -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><?php echo _("Provider Type"); ?></h4>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="provider_type">
                        <?php echo _("Provider Type"); ?>
                        <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-9">
                        <select name="provider_type" id="provider_type" class="form-control" required>
                            <option value="">-- <?php echo _("Select a provider"); ?> --</option>
                            <option value="azure" <?php echo ($isEdit && $idp['provider_type'] === 'azure') ? 'selected' : ''; ?>>
                                Microsoft Azure AD / Entra ID
                            </option>
                            <option value="okta" <?php echo ($isEdit && $idp['provider_type'] === 'okta') ? 'selected' : ''; ?>>
                                Okta
                            </option>
                            <option value="google" <?php echo ($isEdit && $idp['provider_type'] === 'google') ? 'selected' : ''; ?>>
                                Google Workspace
                            </option>
                            <option value="onelogin" <?php echo ($isEdit && $idp['provider_type'] === 'onelogin') ? 'selected' : ''; ?>>
                                OneLogin
                            </option>
                            <option value="auth0" <?php echo ($isEdit && $idp['provider_type'] === 'auth0') ? 'selected' : ''; ?>>
                                Auth0
                            </option>
                            <option value="generic" <?php echo ($isEdit && $idp['provider_type'] === 'generic') ? 'selected' : ''; ?>>
                                Generic SAML 2.0
                            </option>
                        </select>
                        <p class="help-block">
                            <?php echo _("Select your Identity Provider type. This will load a template with recommended settings."); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Setup Instructions -->
        <div class="panel panel-info" id="setup-instructions-panel" style="display: none;">
            <div class="panel-heading">
                <h4><?php echo _("Setup Instructions"); ?></h4>
            </div>
            <div class="panel-body">
                <pre id="setup-instructions" style="white-space: pre-wrap; background: #f5f5f5; padding: 15px; border-radius: 4px;"></pre>
            </div>
        </div>

        <!-- Basic Configuration -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><?php echo _("Basic Configuration"); ?></h4>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="name">
                        <?php echo _("Name"); ?>
                        <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-9">
                        <input type="text" name="name" id="name" class="form-control"
                               value="<?php echo $isEdit ? htmlspecialchars($idp['name']) : ''; ?>"
                               placeholder="e.g., Company Azure AD" required>
                        <p class="help-block">
                            <?php echo _("Friendly name for this Identity Provider"); ?>
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="entity_id">
                        <?php echo _("Entity ID / Issuer"); ?>
                        <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-9">
                        <input type="text" name="entity_id" id="entity_id" class="form-control"
                               value="<?php echo $isEdit ? htmlspecialchars($idp['entity_id']) : ''; ?>"
                               placeholder="https://idp.example.com/saml/metadata" required>
                        <p class="help-block">
                            <?php echo _("The unique identifier for your Identity Provider (found in IdP metadata)"); ?>
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="sso_url">
                        <?php echo _("Single Sign-On URL"); ?>
                        <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-9">
                        <input type="url" name="sso_url" id="sso_url" class="form-control"
                               value="<?php echo $isEdit ? htmlspecialchars($idp['sso_url']) : ''; ?>"
                               placeholder="https://idp.example.com/saml/sso" required>
                        <p class="help-block">
                            <?php echo _("The SAML SSO endpoint URL from your Identity Provider"); ?>
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="slo_url">
                        <?php echo _("Single Logout URL"); ?>
                    </label>
                    <div class="col-sm-9">
                        <input type="url" name="slo_url" id="slo_url" class="form-control"
                               value="<?php echo $isEdit ? htmlspecialchars($idp['slo_url'] ?? '') : ''; ?>"
                               placeholder="https://idp.example.com/saml/slo">
                        <p class="help-block">
                            <?php echo _("The SAML SLO endpoint URL (optional)"); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certificate Configuration -->
        <div class="panel panel-default">
            <div class="panel-heading">
                <h4><?php echo _("Certificate Configuration"); ?></h4>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label class="col-sm-3 control-label" for="certificate">
                        <?php echo _("X.509 Certificate"); ?>
                        <span class="text-danger">*</span>
                    </label>
                    <div class="col-sm-9">
                        <textarea name="certificate" id="certificate" class="form-control saml-cert-preview"
                                  rows="10" required><?php echo $isEdit ? htmlspecialchars($idp['certificate']) : ''; ?></textarea>
                        <p class="help-block">
                            <?php echo _("Paste the IdP's X.509 certificate (Base64 encoded, without headers)"); ?>
                        </p>
                        <div class="alert alert-info">
                            <strong><?php echo _("Note"); ?>:</strong>
                            <?php echo _("Remove the certificate headers (-----BEGIN CERTIFICATE----- and -----END CERTIFICATE-----) and paste only the Base64 encoded content."); ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="certificate_new">
                        <?php echo _("New Certificate (Rollover)"); ?>
                    </label>
                    <div class="col-sm-9">
                        <textarea name="certificate_new" id="certificate_new" class="form-control saml-cert-preview"
                                  rows="6"><?php echo $isEdit ? htmlspecialchars($idp['certificate_new'] ?? '') : ''; ?></textarea>
                        <p class="help-block">
                            <?php echo _("Optional: New certificate for certificate rollover (supports both old and new during transition)"); ?>
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
                    <label class="col-sm-3 control-label">
                        <?php echo _("Status"); ?>
                    </label>
                    <div class="col-sm-9">
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="enabled" value="1"
                                       <?php echo ($isEdit && $idp['enabled']) ? 'checked' : ''; ?>>
                                <?php echo _("Enable this Identity Provider"); ?>
                            </label>
                        </div>
                        <p class="help-block">
                            <?php echo _("Uncheck to disable this IdP without deleting the configuration"); ?>
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label class="col-sm-3 control-label" for="settings">
                        <?php echo _("Custom Settings (JSON)"); ?>
                    </label>
                    <div class="col-sm-9">
                        <textarea name="settings" id="settings" class="form-control"
                                  rows="6"><?php echo $isEdit ? htmlspecialchars($idp['settings'] ?? '') : ''; ?></textarea>
                        <p class="help-block">
                            <?php echo _("Advanced: Provider-specific settings in JSON format"); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="form-group">
            <div class="col-sm-offset-3 col-sm-9">
                <button type="submit" class="btn btn-primary">
                    <i class="fa fa-save"></i> <?php echo _("Save"); ?>
                </button>
                <a href="?display=samlauth" class="btn btn-default">
                    <i class="fa fa-times"></i> <?php echo _("Cancel"); ?>
                </a>
                <?php if ($isEdit): ?>
                <button type="button" class="btn btn-info saml-test-connection pull-right"
                        data-idp-id="<?php echo $idp['id']; ?>">
                    <i class="fa fa-check-circle"></i> <?php echo _("Test Connection"); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
