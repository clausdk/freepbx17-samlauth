/**
 * SAML Authentication Module - JavaScript
 */

$(document).ready(function() {

    // Test IdP connection
    $('.saml-test-connection').on('click', function() {
        var button = $(this);
        var idpId = button.data('idp-id');

        button.prop('disabled', true);
        button.html('<i class="fa fa-spinner fa-spin"></i> Testing...');

        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            data: {
                module: 'samlauth',
                command: 'test_idp',
                idp_id: idpId
            },
            dataType: 'json',
            success: function(response) {
                if (response.status) {
                    var message = response.message;
                    if (response.warning) {
                        message += '\n\nWarning: ' + response.warning;
                        alert(message);
                    } else {
                        alert('✓ ' + message);
                    }
                } else {
                    alert('✗ Test failed: ' + (response.message || 'Unknown error'));
                }
            },
            error: function() {
                alert('✗ Test failed: Unable to connect to server');
            },
            complete: function() {
                button.prop('disabled', false);
                button.html('<i class="fa fa-check-circle"></i>');
            }
        });
    });

    // Delete IdP confirmation
    $('.saml-delete-idp').on('click', function() {
        var idpId = $(this).data('idp-id');
        var idpName = $(this).data('idp-name');

        $('#deleteIdpId').val(idpId);
        $('#deleteIdpName').text(idpName);
        $('#deleteIdpModal').modal('show');
    });

    // Load IdP template
    $('#provider_type').on('change', function() {
        var providerType = $(this).val();

        if (!providerType || providerType === 'generic') {
            return;
        }

        if (confirm('Load template for ' + providerType + '? This will reset the form.')) {
            $.ajax({
                url: 'ajax.php',
                type: 'POST',
                data: {
                    module: 'samlauth',
                    command: 'load_template',
                    provider: providerType
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status && response.data) {
                        var template = response.data;

                        // Populate form fields
                        if (template.name) {
                            $('#name').val(template.name);
                        }

                        // Show help text if available
                        if (template.setup_instructions) {
                            var instructions = template.setup_instructions.join('\n');
                            $('#setup-instructions').text(instructions).show();
                        }
                    }
                }
            });
        }
    });

    // Certificate format helper
    $('#certificate').on('blur', function() {
        var cert = $(this).val().trim();

        // Remove headers and whitespace
        cert = cert.replace(/-----BEGIN CERTIFICATE-----/g, '');
        cert = cert.replace(/-----END CERTIFICATE-----/g, '');
        cert = cert.replace(/\s/g, '');

        $(this).val(cert);
    });

    // Generate certificates
    $('#generate-certificates').on('click', function(e) {
        e.preventDefault();

        if (!confirm('Generate new SP certificates? This will replace existing certificates.')) {
            return;
        }

        var button = $(this);
        button.prop('disabled', true);
        button.html('<i class="fa fa-spinner fa-spin"></i> Generating...');

        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'generate_certificates'
            },
            success: function() {
                alert('Certificates generated successfully!');
                location.reload();
            },
            error: function() {
                alert('Failed to generate certificates');
                button.prop('disabled', false);
                button.html('<i class="fa fa-key"></i> Generate Certificates');
            }
        });
    });

    // Form validation
    $('#idp-form').on('submit', function() {
        var errors = [];

        if (!$('#name').val()) {
            errors.push('Name is required');
        }

        if (!$('#entity_id').val()) {
            errors.push('Entity ID is required');
        }

        if (!$('#sso_url').val()) {
            errors.push('SSO URL is required');
        }

        if (!$('#certificate').val()) {
            errors.push('Certificate is required');
        }

        if (errors.length > 0) {
            alert('Please fix the following errors:\n\n' + errors.join('\n'));
            return false;
        }

        return true;
    });

});
