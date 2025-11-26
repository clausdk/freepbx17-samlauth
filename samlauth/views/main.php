<!-- Main SAML Dashboard -->
<div class="container-fluid">
    <h1><?php echo _("SAML Authentication"); ?></h1>

    <div class="row">
        <div class="col-md-12">
            <!-- Navigation Tabs -->
            <ul class="nav nav-tabs" role="tablist">
                <li role="presentation" class="active">
                    <a href="?display=samlauth&view=main"><?php echo _("Identity Providers"); ?></a>
                </li>
                <li role="presentation">
                    <a href="?display=samlauth&view=settings"><?php echo _("Settings"); ?></a>
                </li>
                <li role="presentation">
                    <a href="?display=samlauth&view=certificates"><?php echo _("Certificates"); ?></a>
                </li>
                <li role="presentation">
                    <a href="?display=samlauth&view=logs"><?php echo _("Security Logs"); ?></a>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane active" style="padding: 20px 0;">

                    <?php
                    // Get all IdPs
                    $idps = $module->getAllIdpConfigs();

                    if (empty($idps)) {
                        ?>
                        <!-- Welcome / Setup Message -->
                        <div class="alert alert-info">
                            <h4><?php echo _("Welcome to SAML Authentication"); ?></h4>
                            <p><?php echo _("Get started by adding your first Identity Provider."); ?></p>
                            <p>
                                <a href="?display=samlauth&view=add" class="btn btn-primary">
                                    <i class="fa fa-plus"></i> <?php echo _("Add Identity Provider"); ?>
                                </a>
                            </p>
                        </div>

                        <!-- Quick Start Guide -->
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h4><?php echo _("Quick Start Guide"); ?></h4>
                            </div>
                            <div class="panel-body">
                                <ol>
                                    <li><?php echo _("Add an Identity Provider using one of the templates (Azure AD, Okta, Google, etc.)"); ?></li>
                                    <li><?php echo _("Download SP Metadata from the Certificates page"); ?></li>
                                    <li><?php echo _("Configure your IdP with the SP Metadata"); ?></li>
                                    <li><?php echo _("Enter IdP details (Entity ID, SSO URL, Certificate)"); ?></li>
                                    <li><?php echo _("Test the connection"); ?></li>
                                    <li><?php echo _("Enable the IdP and configure user settings"); ?></li>
                                </ol>
                            </div>
                        </div>
                        <?php
                    } else {
                        ?>
                        <!-- IdP List -->
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-12">
                                <a href="?display=samlauth&view=add" class="btn btn-success">
                                    <i class="fa fa-plus"></i> <?php echo _("Add Identity Provider"); ?>
                                </a>
                            </div>
                        </div>

                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th><?php echo _("Name"); ?></th>
                                    <th><?php echo _("Provider Type"); ?></th>
                                    <th><?php echo _("Entity ID"); ?></th>
                                    <th><?php echo _("Status"); ?></th>
                                    <th><?php echo _("Actions"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($idps as $idp): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($idp['name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $providerLabels = array(
                                            'azure' => 'Microsoft Azure AD',
                                            'okta' => 'Okta',
                                            'google' => 'Google Workspace',
                                            'onelogin' => 'OneLogin',
                                            'auth0' => 'Auth0',
                                            'generic' => 'Generic SAML 2.0'
                                        );
                                        echo $providerLabels[$idp['provider_type']] ?? $idp['provider_type'];
                                        ?>
                                    </td>
                                    <td>
                                        <small><?php echo htmlspecialchars($idp['entity_id']); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($idp['enabled']): ?>
                                            <span class="label label-success"><?php echo _("Enabled"); ?></span>
                                        <?php else: ?>
                                            <span class="label label-default"><?php echo _("Disabled"); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="?display=samlauth&view=edit&id=<?php echo $idp['id']; ?>"
                                               class="btn btn-sm btn-default"
                                               title="<?php echo _("Edit"); ?>">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <button type="button"
                                                    class="btn btn-sm btn-info saml-test-connection"
                                                    data-idp-id="<?php echo $idp['id']; ?>"
                                                    title="<?php echo _("Test Connection"); ?>">
                                                <i class="fa fa-check-circle"></i>
                                            </button>
                                            <button type="button"
                                                    class="btn btn-sm btn-danger saml-delete-idp"
                                                    data-idp-id="<?php echo $idp['id']; ?>"
                                                    data-idp-name="<?php echo htmlspecialchars($idp['name']); ?>"
                                                    title="<?php echo _("Delete"); ?>">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if (empty($idps)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">
                                        <?php echo _("No Identity Providers configured."); ?>
                                        <a href="?display=samlauth&view=add"><?php echo _("Add one now"); ?></a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <?php
                    }
                    ?>

                    <!-- Information Panel -->
                    <div class="panel panel-info">
                        <div class="panel-heading">
                            <h4><?php echo _("Service Provider Information"); ?></h4>
                        </div>
                        <div class="panel-body">
                            <dl class="dl-horizontal">
                                <dt><?php echo _("SP Entity ID"); ?>:</dt>
                                <dd>
                                    <code><?php
                                        $protocol = (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') ? 'http' : 'https';
                                        $host = $_SERVER['HTTP_HOST'];
                                        echo "{$protocol}://{$host}/admin/modules/samlauth/endpoints/metadata.php";
                                    ?></code>
                                </dd>

                                <dt><?php echo _("ACS URL"); ?>:</dt>
                                <dd>
                                    <code><?php echo "{$protocol}://{$host}/admin/modules/samlauth/endpoints/acs.php"; ?></code>
                                </dd>

                                <dt><?php echo _("SLS URL"); ?>:</dt>
                                <dd>
                                    <code><?php echo "{$protocol}://{$host}/admin/modules/samlauth/endpoints/sls.php"; ?></code>
                                </dd>

                                <dt><?php echo _("SP Metadata"); ?>:</dt>
                                <dd>
                                    <a href="<?php echo "{$protocol}://{$host}/admin/modules/samlauth/endpoints/metadata.php"; ?>"
                                       target="_blank">
                                        <?php echo _("Download Metadata XML"); ?>
                                        <i class="fa fa-external-link"></i>
                                    </a>
                                </dd>
                            </dl>

                            <?php if ($protocol === 'http'): ?>
                            <div class="alert alert-warning">
                                <i class="fa fa-warning"></i>
                                <strong><?php echo _("Warning"); ?>:</strong>
                                <?php echo _("You are accessing FreePBX over HTTP. SAML requires HTTPS in production for security."); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteIdpModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><?php echo _("Delete Identity Provider"); ?></h4>
            </div>
            <div class="modal-body">
                <p><?php echo _("Are you sure you want to delete this Identity Provider?"); ?></p>
                <p><strong id="deleteIdpName"></strong></p>
                <p class="text-danger"><?php echo _("This action cannot be undone."); ?></p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="?display=samlauth">
                    <input type="hidden" name="action" value="delete_idp">
                    <input type="hidden" name="id" id="deleteIdpId">
                    <button type="button" class="btn btn-default" data-dismiss="modal">
                        <?php echo _("Cancel"); ?>
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fa fa-trash"></i> <?php echo _("Delete"); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
