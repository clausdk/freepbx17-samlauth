<?php
/**
 * SAML Authentication Admin Page
 *
 * Main admin interface for SAML configuration
 */

if (!defined('FREEPBX_IS_AUTH')) {
    die('No direct script access allowed');
}

// Get FreePBX object
$FreePBX = FreePBX::Create();

// Get module instance
$module = $FreePBX->Samlauth;

// Determine which view to show
$view = isset($_REQUEST['view']) ? $_REQUEST['view'] : 'main';
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Show any messages from form submissions
if (isset($_SESSION['saml_message'])) {
    $message = $_SESSION['saml_message'];
    unset($_SESSION['saml_message']);
    ?>
    <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible" role="alert">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
        <?php echo htmlspecialchars($message['message']); ?>
    </div>
    <?php
}

// Load view based on requested view
switch ($view) {
    case 'edit':
    case 'add':
        // Edit/Add IdP configuration
        $idp = null;
        if (isset($_REQUEST['id'])) {
            $idp = $module->getIdpConfig((int)$_REQUEST['id']);
        }
        echo load_view(__DIR__ . '/views/idp_form.php', array(
            'idp' => $idp,
            'module' => $module
        ));
        break;

    case 'settings':
        // Global SAML settings
        echo load_view(__DIR__ . '/views/settings.php', array(
            'module' => $module
        ));
        break;

    case 'logs':
        // Security logs
        echo load_view(__DIR__ . '/views/logs.php', array(
            'module' => $module
        ));
        break;

    case 'certificates':
        // Certificate management
        echo load_view(__DIR__ . '/views/certificates.php', array(
            'module' => $module
        ));
        break;

    case 'main':
    default:
        // Main dashboard
        echo load_view(__DIR__ . '/views/main.php', array(
            'module' => $module
        ));
        break;
}
?>

<!-- Include module CSS -->
<link rel="stylesheet" href="modules/samlauth/assets/css/samlauth.css">

<!-- Include module JavaScript -->
<script src="modules/samlauth/assets/js/samlauth.js"></script>
