# FreePBX 17 SAML Authentication Module

**Version:** 17.0.1
**License:** GPLv3
**Author:** FreePBX Community

## Overview

A comprehensive, enterprise-grade SAML 2.0 Single Sign-On (SSO) authentication module for FreePBX 17. This module provides secure authentication integration with major Identity Providers including Microsoft Azure AD/Entra ID, Okta, Google Workspace, OneLogin, Auth0, and any generic SAML 2.0 compatible IdP.

### Key Features

- ✅ **Full SAML 2.0 Protocol Support** - Complete SP implementation
- ✅ **Multiple IdP Support** - Pre-configured templates for major providers
- ✅ **Just-in-Time (JIT) User Provisioning** - Automatic user creation from IdP
- ✅ **Single Logout (SLO)** - Secure logout across all systems
- ✅ **Enterprise Security** - Comprehensive protection against SAML attacks
- ✅ **Auto-Generated Certificates** - Automated SP certificate management
- ✅ **Attribute Mapping** - Flexible user attribute configuration
- ✅ **Security Auditing** - Complete logging and monitoring

## Security Features

This module implements industry-standard security best practices:

### Core Security
- **Replay Attack Prevention** - Assertion ID tracking with expiration
- **XML Signature Validation** - Validates both messages and assertions
- **Session Fixation Prevention** - Regenerates session IDs after authentication
- **CSRF Protection** - Rejects unsolicited responses
- **SHA-256 Signatures** - Modern cryptographic algorithms (no SHA-1)
- **Strict Mode Validation** - All security checks enabled by default

### Additional Protections
- Certificate validation and expiration monitoring
- Open redirect prevention (RelayState validation)
- Attribute injection prevention (whitelist-based mapping)
- Privilege escalation prevention (role validation)
- Information disclosure prevention (generic error messages)
- Brute force detection and logging

## Requirements

- **FreePBX:** 17.0+
- **PHP:** 7.4+ (FreePBX 17 uses PHP 8.2)
- **Composer:** For installing dependencies
- **HTTPS/SSL:** **REQUIRED** for production use
- **Database:** MySQL/MariaDB (included with FreePBX)

## Installation

### 1. Copy Module to FreePBX

```bash
# Copy module to FreePBX modules directory
sudo cp -r samlauth /var/www/html/admin/modules/

# Set proper ownership
sudo chown -R asterisk:asterisk /var/www/html/admin/modules/samlauth
```

### 2. Install Composer Dependencies

```bash
cd /var/www/html/admin/modules/samlauth
sudo composer install --no-dev --optimize-autoloader
```

### 3. Create Certificate Directory

```bash
sudo mkdir -p /etc/freepbx/saml/certs
sudo chown asterisk:asterisk /etc/freepbx/saml/certs
sudo chmod 700 /etc/freepbx/saml/certs
```

### 4. Install Module via FreePBX Admin

1. Log in to FreePBX Admin Panel
2. Navigate to **Admin → Module Admin**
3. Find "SAML Authentication" in the list
4. Click **Install** and then **Process**

### 5. Configure Cron Job (Recommended)

Add the cleanup script to cron for maintenance:

```bash
# Edit crontab for asterisk user
sudo crontab -u asterisk -e

# Add this line to run cleanup every 5 minutes
*/5 * * * * /usr/bin/php /var/www/html/admin/modules/samlauth/scripts/cleanup.php >> /var/log/asterisk/saml-cleanup.log 2>&1
```

## Quick Start Guide

### Step 1: Access SAML Configuration

Navigate to **Admin → SAML Authentication** in FreePBX

### Step 2: Generate SP Certificates

1. Go to the **Certificates** tab
2. Click **Generate Certificates**
3. Download the SP Metadata XML

### Step 3: Configure Your Identity Provider

Choose your IdP and follow the setup guide:

- [Microsoft Azure AD](#azure-ad-setup)
- [Okta](#okta-setup)
- [Google Workspace](#google-workspace-setup)
- [OneLogin](#onelogin-setup)
- [Auth0](#auth0-setup)
- [Generic SAML 2.0](#generic-saml-setup)

### Step 4: Add IdP in FreePBX

1. Click **Add Identity Provider**
2. Select your provider type from the dropdown
3. Fill in the required fields:
   - **Name**: Friendly name for this IdP
   - **Entity ID**: From your IdP configuration
   - **SSO URL**: Single Sign-On endpoint
   - **Certificate**: IdP signing certificate
4. Click **Save**

### Step 5: Test Connection

1. Click the **Test** button next to your IdP
2. Verify the configuration is valid
3. Check certificate expiration dates

### Step 6: Enable and Test

1. Enable the IdP
2. Test login: `https://your-freepbx.com/admin/modules/samlauth/endpoints/login.php`
3. You should be redirected to your IdP for authentication

## Identity Provider Setup Guides

### Azure AD Setup

**Prerequisites:** Azure AD tenant with admin access

1. **Create Enterprise Application**
   - Azure Portal → Azure Active Directory → Enterprise Applications
   - Click "+ New application" → "Create your own application"
   - Name: "FreePBX"
   - Select: "Integrate any other application you don't find in the gallery"

2. **Configure SAML**
   - Go to Single sign-on → SAML
   - Basic SAML Configuration:
     - Identifier (Entity ID): `https://your-freepbx.com/admin/modules/samlauth/endpoints/metadata.php`
     - Reply URL: `https://your-freepbx.com/admin/modules/samlauth/endpoints/acs.php`
     - Sign on URL: `https://your-freepbx.com/admin`

3. **Configure Claims**
   - Ensure these claims are mapped:
     - `email` → `user.mail`
     - `givenname` → `user.givenname`
     - `surname` → `user.surname`

4. **Download Certificate**
   - SAML Signing Certificate → Certificate (Base64) → Download

5. **Get Configuration Values**
   - Copy "Login URL" (SSO URL)
   - Copy "Azure AD Identifier" (Entity ID)

6. **Enter in FreePBX**
   - Provider Type: Microsoft Azure AD
   - Paste Entity ID, SSO URL, and Certificate

### Okta Setup

**Prerequisites:** Okta account with admin access

1. **Create SAML Application**
   - Okta Admin Console → Applications → Create App Integration
   - Sign-in method: SAML 2.0

2. **General Settings**
   - App name: "FreePBX"

3. **Configure SAML**
   - Single sign on URL: `https://your-freepbx.com/admin/modules/samlauth/endpoints/acs.php`
   - Audience URI: `https://your-freepbx.com/admin/modules/samlauth/endpoints/metadata.php`
   - Name ID format: EmailAddress
   - Application username: Email

4. **Attribute Statements**
   - `email` = `user.email`
   - `firstName` = `user.firstName`
   - `lastName` = `user.lastName`

5. **View Setup Instructions**
   - Sign On tab → View Setup Instructions
   - Copy Identity Provider Issuer, SSO URL, and Certificate

6. **Assign Users**
   - Assignments tab → Assign users/groups

### Google Workspace Setup

**Prerequisites:** Google Workspace admin access

1. **Create Custom SAML App**
   - Google Admin Console → Apps → Web and mobile apps
   - Add App → Add custom SAML app

2. **Google Identity Provider Details**
   - Download Certificate
   - Copy SSO URL and Entity ID

3. **Service Provider Details**
   - ACS URL: `https://your-freepbx.com/admin/modules/samlauth/endpoints/acs.php`
   - Entity ID: `https://your-freepbx.com/admin/modules/samlauth/endpoints/metadata.php`
   - Start URL: `https://your-freepbx.com/admin`
   - Name ID format: EMAIL
   - Name ID: Primary email

4. **Attribute Mapping**
   - `email` → Primary email
   - `first_name` → First name
   - `last_name` → Last name

5. **Turn ON Service**
   - Enable for your users/organizational units

### OneLogin Setup

1. **Add Application**
   - Applications → Add App
   - Search "SAML Test Connector (IdP)" or create custom

2. **Configuration**
   - Audience (EntityID): From FreePBX metadata
   - Recipient: `https://your-freepbx.com/admin/modules/samlauth/endpoints/acs.php`
   - ACS URL: `https://your-freepbx.com/admin/modules/samlauth/endpoints/acs.php`

3. **Parameters**
   - Add: `email`, `firstName`, `lastName`, `groups`

4. **SSO**
   - Copy Issuer URL and SAML 2.0 Endpoint
   - Download X.509 Certificate

### Auth0 Setup

1. **Create Application**
   - Applications → Create Application
   - Type: Regular Web Application

2. **Enable SAML2 Addon**
   - Addons → SAML2 Web App
   - Application Callback URL: `https://your-freepbx.com/admin/modules/samlauth/endpoints/acs.php`

3. **Settings JSON**
   ```json
   {
     "audience": "https://your-freepbx.com/admin/modules/samlauth/endpoints/metadata.php",
     "mappings": {
       "email": "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress",
       "given_name": "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname",
       "family_name": "http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname"
     },
     "nameIdentifierFormat": "urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress"
   }
   ```

4. **Get Configuration**
   - Usage tab: Copy Login URL and download Certificate
   - Issuer: `urn:YOUR_DOMAIN`

### Generic SAML Setup

For any SAML 2.0 compatible IdP:

1. Obtain IdP metadata XML or manual configuration values
2. Extract: Entity ID, SSO URL, Certificate
3. Configure IdP with FreePBX SP details (see SP Information below)
4. Enter IdP values in FreePBX SAML configuration

## Configuration

### SP Information

Provide these values to your Identity Provider:

- **SP Entity ID:** `https://your-freepbx.com/admin/modules/samlauth/endpoints/metadata.php`
- **ACS URL (POST):** `https://your-freepbx.com/admin/modules/samlauth/endpoints/acs.php`
- **SLS URL (Redirect):** `https://your-freepbx.com/admin/modules/samlauth/endpoints/sls.php`
- **NameID Format:** `urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress`
- **SP Metadata:** Download from Certificates tab

### Module Settings

Navigate to **Admin → SAML Authentication → Settings**

#### Just-in-Time Provisioning
- **Enable JIT Provisioning:** Auto-create users from SAML assertions
- **Default Sections:** Permissions for new users (default: `*` = all)
- **Session Timeout:** SAML session duration (default: 8 hours)

#### Security Settings
- **Assertion Cache TTL:** Replay protection cache time (default: 1 hour)
- **Log Retention Days:** Security log retention (default: 90 days)
- **Alert Email:** Email for security alerts

### Attribute Mapping

Edit `classes/SamlAttributeMapper.php` to customize attribute mappings:

```php
private $attributeMappings = array(
    'email' => array(
        'saml_attribute' => 'email',
        'required' => true,
        'validation' => 'email',
    ),
    // Add custom mappings...
);
```

### Role Mapping

Edit the `mapRolesToPermissions()` method to customize role assignments:

```php
$rolePermissions = array(
    'Administrator' => array('sections' => '*', 'permissions' => 'admin'),
    'Manager' => array('sections' => 'settings,reports', 'permissions' => 'edit'),
    // Add custom roles...
);
```

## Security Best Practices

### Production Deployment

1. **HTTPS Required**
   - Never use SAML over HTTP in production
   - Ensure valid SSL certificate

2. **Certificate Security**
   - Store certificates in `/etc/freepbx/saml/certs/`
   - Set permissions to 600 (owner read/write only)
   - Monitor expiration dates

3. **Firewall Configuration**
   - Allow HTTPS (443) from IdP IP ranges
   - Block direct access to endpoints if possible

4. **Regular Maintenance**
   - Review security logs regularly
   - Update certificates before expiration
   - Monitor for replay attack attempts

5. **Backup**
   - Include SAML configuration in FreePBX backups
   - Backup `/etc/freepbx/saml/certs/` separately

### Security Monitoring

Review security logs at **Admin → SAML Authentication → Security Logs**

Watch for:
- `replay_attempt` - Potential replay attacks
- `login_failure` - Failed authentication attempts
- `invalid_signature` - Certificate or signature issues
- `certificate_expiring` - Expiring certificates

## Troubleshooting

### Common Issues

#### "Authentication failed" Error

**Causes:**
- Invalid certificate
- Clock skew between SP and IdP
- Incorrect Entity ID or SSO URL

**Solutions:**
1. Test connection in FreePBX
2. Check certificate is valid and not expired
3. Verify Entity ID and SSO URL match IdP
4. Check server time: `date` (should match actual time)

#### "No SAML session found" Error

**Causes:**
- Cookies disabled
- Session timeout
- IdP-initiated login without session

**Solutions:**
1. Enable cookies in browser
2. Increase session timeout in Settings
3. Use SP-initiated login instead

#### Certificate Errors

**Error:** "Invalid certificate format"

**Solution:**
- Ensure certificate is Base64 encoded
- Remove `-----BEGIN CERTIFICATE-----` and `-----END CERTIFICATE-----` headers
- Remove all whitespace and newlines

#### Replay Attack Warnings

**Error:** "Invalid authentication attempt"

**Cause:** Assertion ID already processed (replay protection working correctly)

**Solution:**
- If legitimate: Clear browser cache and retry
- If persistent: Check for clock skew or IdP issues

### Enable Debug Mode

**Temporarily enable debug logging:**

Edit `classes/SamlConfigManager.php`:

```php
'debug' => true,  // Set to false in production
```

Check logs in `/var/log/asterisk/freepbx.log`

### Test SAML Flow Manually

```bash
# Test SP metadata generation
curl https://your-freepbx.com/admin/modules/samlauth/endpoints/metadata.php

# Initiate login
curl -L https://your-freepbx.com/admin/modules/samlauth/endpoints/login.php
```

## API / Integration

### Programmatic Login

```php
<?php
// Initiate SAML login with specific IdP
header('Location: /admin/modules/samlauth/endpoints/login.php?idp=1&returnTo=/admin/dashboard');
```

### Check SAML Authentication

```php
<?php
session_start();

if (isset($_SESSION['saml_authenticated']) && $_SESSION['saml_authenticated'] === true) {
    // User authenticated via SAML
    $userId = $_SESSION['saml_user_id'];
    $nameId = $_SESSION['saml_nameid'];
}
```

### Logout

```php
<?php
// Initiate SAML logout
header('Location: /admin/modules/samlauth/endpoints/logout.php');
```

## Development

### File Structure

```
samlauth/
├── module.xml                    # Module definition
├── Samlauth.class.php            # Main BMO class
├── composer.json                 # Dependencies
├── page.samlauth.php             # Admin UI page
├── classes/                      # Helper classes
│   ├── SamlConfigManager.php    # Configuration builder
│   ├── SamlAuthHandler.php      # Authentication flow
│   ├── SamlAttributeMapper.php  # Attribute mapping
│   └── CertificateManager.php   # Certificate management
├── endpoints/                    # SAML endpoints
│   ├── acs.php                  # Assertion Consumer Service
│   ├── sls.php                  # Single Logout Service
│   ├── metadata.php             # SP Metadata
│   ├── login.php                # Login initiator
│   └── logout.php               # Logout initiator
├── views/                        # UI templates
│   └── main.php                 # Main dashboard
├── templates/                    # IdP templates
│   ├── azure.json
│   ├── okta.json
│   ├── google.json
│   ├── onelogin.json
│   ├── auth0.json
│   └── generic.json
├── scripts/                      # Maintenance scripts
│   └── cleanup.php              # Cron cleanup
└── assets/                       # Frontend assets
    ├── js/samlauth.js
    └── css/samlauth.css
```

### Database Schema

The module uses 8 tables (created automatically):

- `saml_idp_config` - IdP configurations
- `saml_user_settings` - Per-user SAML settings
- `saml_group_settings` - Per-group SAML settings
- `saml_sessions` - Active SAML sessions
- `saml_processed_assertions` - Replay attack prevention
- `saml_user_mappings` - SAML to FreePBX user mapping
- `saml_security_log` - Security audit log
- `saml_settings` - Module settings

### Running Tests

```bash
# Test IdP connection
php -r "require 'Samlauth.class.php'; \$module->testIdpConnection(1);"

# Test certificate generation
php -r "require 'classes/CertificateManager.php'; \$cert = new CertificateManager(); \$cert->generateCertificates();"

# Run cleanup manually
php scripts/cleanup.php
```

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Follow FreePBX coding standards
4. Test thoroughly with multiple IdPs
5. Submit a pull request

## License

This module is licensed under **GPLv3**.

```
FreePBX SAML Authentication Module
Copyright (C) 2024 FreePBX Community

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Support

- **Issues:** Report bugs on GitHub Issues
- **Documentation:** https://wiki.freepbx.org
- **Community Forum:** https://community.freepbx.org
- **Commercial Support:** Available through Sangoma

## Credits

- **SAML Library:** [onelogin/php-saml](https://github.com/SAML-Toolkits/php-saml)
- **FreePBX:** [FreePBX Project](https://www.freepbx.org)
- **Contributors:** Community contributors

## Changelog

### Version 17.0.1 (2024-11-25)
- Initial release for FreePBX 17
- Full SAML 2.0 protocol support
- Multiple IdP templates (Azure, Okta, Google, OneLogin, Auth0)
- Enterprise security features
- Just-in-Time user provisioning
- Single Logout support
- Auto-generated SP certificates
- Comprehensive security logging
- Replay attack prevention
- Attribute mapping and validation

---

**Built with ❤️ for the FreePBX community**
