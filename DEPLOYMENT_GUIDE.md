# Quick Deployment Guide

## Prerequisites Checklist

- [ ] FreePBX 17.0+ installed
- [ ] PHP 7.4+ available (FreePBX 17 uses PHP 8.2)
- [ ] Composer installed on server
- [ ] Valid SSL certificate (HTTPS required)
- [ ] Root or sudo access
- [ ] Identity Provider account (Azure AD, Okta, etc.)

---

## 5-Minute Deployment

### Step 1: Install Dependencies (2 minutes)

```bash
cd /path/to/freepbx17-samlauth/samlauth
composer install --no-dev --optimize-autoloader
```

### Step 2: Deploy to FreePBX (1 minute)

```bash
sudo cp -r samlauth /var/www/html/admin/modules/
sudo chown -R asterisk:asterisk /var/www/html/admin/modules/samlauth
sudo mkdir -p /etc/freepbx/saml/certs
sudo chown asterisk:asterisk /etc/freepbx/saml/certs
sudo chmod 700 /etc/freepbx/saml/certs
```

### Step 3: Install Module (1 minute)

1. Log in to FreePBX Admin: `https://your-freepbx.com/admin`
2. Navigate to: **Admin → Module Admin**
3. Find "SAML Authentication" in the list
4. Click **Install** button
5. Click **Process** to apply changes

### Step 4: Setup Cron Job (1 minute)

```bash
sudo crontab -u asterisk -e
```

Add this line:

```cron
*/5 * * * * /usr/bin/php /var/www/html/admin/modules/samlauth/scripts/cleanup.php >> /var/log/asterisk/saml-cleanup.log 2>&1
```

### Step 5: Configure SAML (already done if modules installed)

Module is now installed! Proceed to configuration.

---

## Configuration (10 minutes)

### Generate SP Certificates

1. Go to: **Admin → SAML Authentication → Certificates**
2. Click **"Generate Certificates"**
3. Download **SP Metadata XML**

### Configure Your IdP

Choose your provider and follow the guide:

#### Azure AD (5 minutes)
1. Azure Portal → Azure AD → Enterprise Applications
2. Create new application: "FreePBX"
3. Setup SAML:
   - Upload FreePBX SP Metadata OR manually enter:
   - Entity ID: `https://your-freepbx.com/admin/modules/samlauth/endpoints/metadata.php`
   - Reply URL: `https://your-freepbx.com/admin/modules/samlauth/endpoints/acs.php`
4. Download Certificate (Base64)
5. Copy Login URL and Azure AD Identifier

#### Okta (5 minutes)
1. Okta Admin → Applications → Create App Integration
2. Select SAML 2.0
3. App name: "FreePBX"
4. Configure SAML:
   - Single sign on URL: `https://your-freepbx.com/admin/modules/samlauth/endpoints/acs.php`
   - Audience URI: `https://your-freepbx.com/admin/modules/samlauth/endpoints/metadata.php`
5. Add attribute statements: email, firstName, lastName
6. Copy Issuer URL, SSO URL, and Certificate

#### Google Workspace (5 minutes)
1. Google Admin → Apps → Web and mobile apps
2. Add App → Add custom SAML app
3. Name: "FreePBX"
4. Download Certificate, copy SSO URL and Entity ID
5. Configure:
   - ACS URL: `https://your-freepbx.com/admin/modules/samlauth/endpoints/acs.php`
   - Entity ID: `https://your-freepbx.com/admin/modules/samlauth/endpoints/metadata.php`
6. Map attributes: email, first_name, last_name
7. Enable for users

### Add IdP in FreePBX

1. Go to: **Admin → SAML Authentication**
2. Click **"Add Identity Provider"**
3. Select your provider type (Azure AD, Okta, Google, etc.)
4. Fill in the form:
   - **Name**: e.g., "Company Azure AD"
   - **Entity ID**: Paste from IdP
   - **SSO URL**: Paste from IdP
   - **Certificate**: Paste IdP certificate (Base64, no headers)
5. Click **Save**

### Test Connection

1. Click the **Test** button (🔘) next to your IdP
2. Verify: "✓ Configuration valid"
3. Check certificate expiration

### Enable IdP

1. Edit the IdP
2. Check **"Enabled"**
3. Save

---

## First Login Test

### Test SAML Login

1. Open new incognito/private browser window
2. Navigate to: `https://your-freepbx.com/admin/modules/samlauth/endpoints/login.php`
3. You should be redirected to your IdP
4. Log in with your IdP credentials
5. You should be redirected back to FreePBX and logged in

### If It Doesn't Work

**Check these:**

1. **HTTPS**: Are you using HTTPS? SAML requires it.
2. **Certificate**: Did you paste the certificate correctly? (No headers, no whitespace)
3. **URLs**: Do Entity ID and SSO URL match exactly?
4. **User Assignment**: Did you assign users to the app in your IdP?
5. **Logs**: Check `/var/log/asterisk/freepbx.log` for errors

---

## Post-Deployment

### Security Checklist

- [ ] Verify HTTPS is working (valid SSL certificate)
- [ ] Test SAML login with real user
- [ ] Test SAML logout
- [ ] Review security logs: **Admin → SAML Authentication → Security Logs**
- [ ] Verify cron job is running: `sudo crontab -u asterisk -l`
- [ ] Check certificate permissions: `ls -l /etc/freepbx/saml/certs/`
- [ ] Set up email alerts (optional): **Settings → Alert Email**
- [ ] Backup `/etc/freepbx/saml/certs/` directory

### Recommended Settings

Go to **Admin → SAML Authentication → Settings**

- **JIT Provisioning**: Enabled (auto-create users)
- **Default Sections**: `*` (all sections) or customize
- **Session Timeout**: 28800 (8 hours)
- **Log Retention**: 90 days

### Monitoring

Set up these alerts:

1. **Certificate Expiration** - Module sends alerts 30 days before expiry
2. **Failed Logins** - Review security logs weekly
3. **Replay Attempts** - Investigate immediately if seen

---

## Troubleshooting Quick Fixes

### "Authentication failed"

```bash
# Check FreePBX logs
sudo tail -f /var/log/asterisk/freepbx.log | grep SAML

# Verify certificate is valid
openssl x509 -in /etc/freepbx/saml/certs/sp.crt -text -noout
```

### "No SAML session found"

- Clear browser cookies
- Try in incognito/private window
- Check session timeout setting

### "Certificate errors"

Certificate format should be Base64 without headers:
```
MIIDXTCCAkWgAwIBAgIJALmVVuDWu4NYMA0GCSqGSIb3DQEBCwUA...
```

NOT:
```
-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJALmVVuDWu4NYMA0GCSqGSIb3DQEBCwUA...
-----END CERTIFICATE-----
```

### Re-generate Certificates

```bash
cd /var/www/html/admin/modules/samlauth
sudo -u asterisk php -r "require 'classes/CertificateManager.php'; \$c = new FreePBX\modules\Samlauth\CertificateManager(); \$c->generateCertificates();"
```

### View Cleanup Logs

```bash
sudo tail -f /var/log/asterisk/saml-cleanup.log
```

### Manual Cleanup

```bash
cd /var/www/html/admin/modules/samlauth
sudo -u asterisk php scripts/cleanup.php
```

---

## Common URLs Reference

Replace `your-freepbx.com` with your actual domain:

| Purpose | URL |
|---------|-----|
| Admin Login | `https://your-freepbx.com/admin` |
| SAML Config | `https://your-freepbx.com/admin/config.php?display=samlauth` |
| SAML Login | `https://your-freepbx.com/admin/modules/samlauth/endpoints/login.php` |
| SAML Logout | `https://your-freepbx.com/admin/modules/samlauth/endpoints/logout.php` |
| SP Metadata | `https://your-freepbx.com/admin/modules/samlauth/endpoints/metadata.php` |

---

## Support Resources

- **Full Documentation**: See `README.md`
- **Installation Details**: See `INSTALL.txt`
- **Project Summary**: See `PROJECT_SUMMARY.md`
- **FreePBX Forums**: https://community.freepbx.org
- **SAML Library Docs**: https://github.com/SAML-Toolkits/php-saml

---

## Success!

If you can:
- ✅ Log in via SAML
- ✅ See your user info in FreePBX
- ✅ Log out successfully
- ✅ See security logs

**You're all set! 🎉**

---

*Deployment typically takes 15-20 minutes including IdP configuration*
