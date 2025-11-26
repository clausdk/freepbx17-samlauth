/**
 * SAML Login Button Injector
 *
 * This script is automatically loaded on the login page via doConfigPageInit hook
 * and injects SAML login buttons dynamically without modifying core files
 */

(function() {
    'use strict';

    // Check if we're on the login page
    if (!document.getElementById('loginform')) {
        return;
    }

    // Fetch SAML configuration via AJAX
    fetch('/admin/ajax.php?module=samlauth&command=get_login_config')
        .then(response => response.json())
        .then(data => {
            if (!data.status || !data.show_button || !data.idps || data.idps.length === 0) {
                return;
            }

            // Check if redirect is enabled
            if (data.redirect_all && !window.location.search.includes('local=1')) {
                // Redirect to SAML login
                window.location.href = data.idps[0].login_url;
                return;
            }

            // Inject SAML login buttons
            injectSamlButtons(data.idps, data.redirect_all);
        })
        .catch(error => {
            console.error('SAML login injection error:', error);
        });

    function injectSamlButtons(idps, redirectAll) {
        var loginForm = document.getElementById('loginform');
        if (!loginForm) return;

        // Create separator
        var separator = document.createElement('div');
        separator.className = 'saml-login-separator';
        separator.innerHTML = '<span>Or</span>';
        separator.style.cssText = 'margin: 20px 0; text-align: center; position: relative;';

        var separatorBefore = document.createElement('style');
        separatorBefore.textContent = '.saml-login-separator:before { content: ""; position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: #ddd; }';
        document.head.appendChild(separatorBefore);

        separator.querySelector('span').style.cssText = 'background: #f5f5f5; padding: 0 10px; position: relative; color: #666; font-size: 12px; text-transform: uppercase;';

        loginForm.appendChild(separator);

        // Create button container
        var buttonContainer = document.createElement('div');
        buttonContainer.className = 'saml-button-container';

        // Add each IdP button
        idps.forEach(function(idp) {
            var button = document.createElement('a');
            button.href = idp.login_url;
            button.className = 'saml-login-button saml-provider-' + idp.provider_type;
            button.style.cssText = 'display: block; width: 100%; padding: 12px; margin: 10px 0; border: 1px solid; background: #fff; border-radius: 4px; text-decoration: none; text-align: center; font-weight: 500; transition: all 0.3s ease; cursor: pointer;';

            // Provider-specific styling
            var providerStyles = {
                'google': { border: '#4285f4', color: '#4285f4', hover: '#4285f4' },
                'azure': { border: '#00a4ef', color: '#00a4ef', hover: '#00a4ef' },
                'okta': { border: '#007dc1', color: '#007dc1', hover: '#007dc1' },
                'generic': { border: '#666', color: '#666', hover: '#666' }
            };

            var style = providerStyles[idp.provider_type] || providerStyles.generic;
            button.style.borderColor = style.border;
            button.style.color = style.color;

            // Add hover effect
            button.onmouseover = function() {
                this.style.background = style.hover;
                this.style.color = '#fff';
            };
            button.onmouseout = function() {
                this.style.background = '#fff';
                this.style.color = style.color;
            };

            // Icon and text
            var icon = document.createElement('i');
            icon.className = 'fa ' + idp.icon;
            icon.style.marginRight = '8px';

            button.appendChild(icon);
            button.appendChild(document.createTextNode(idp.button_text));

            buttonContainer.appendChild(button);
        });

        loginForm.appendChild(buttonContainer);

        // Add local login link if redirect is enabled
        if (redirectAll) {
            var localLink = document.createElement('div');
            localLink.style.cssText = 'text-align: center; margin-top: 15px;';
            localLink.innerHTML = '<a href="?local=1" style="font-size: 12px; color: #666;">Use local login instead</a>';
            loginForm.appendChild(localLink);
        }
    }
})();
