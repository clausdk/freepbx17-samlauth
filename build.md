# FreePBX 17 SAML Authentication Module - Open Source

Build a complete open-source SAML 2.0 Single Sign-On authentication module for FreePBX 17 with all the features from the commercial version.

## Project Requirements

Create a FreePBX module called "samlauth" with the following structure and features:

### Core Features Required

1. **SAML 2.0 SSO Authentication**
   - Full SAML 2.0 protocol support
   - Service Provider (SP) implementation
   - Support for Identity Provider (IdP) initiated and SP initiated flows
   - Signed assertions and responses
   - Single Logout (SLO) support

2. **Multiple IdP Support**
   - Pre-configured templates for:
     - Microsoft Azure AD / Entra ID
     - Okta
     - OneLogin
     - Google Workspace
     - Auth0
     - Generic SAML 2.0 IdP
   - Custom IdP configuration support

3. **User & Group Management**
   - Enable SAML per user
   - Enable SAML per group
   - Auto-provisioning of users from IdP
   - Attribute mapping (email, name, groups, etc.)
   - Just-in-time (JIT) user creation

4. **Integration Points**
   - Admin Control Panel (ACP) login
   - User Control Panel (UCP) login
   - Hook into FreePBX authentication system
   - Session management

5. **Configuration Wizard**
   - Step-by-step IdP setup
   - Metadata import (XML upload)
   - Certificate management
   - Test connection feature
   - Configuration summary and review

6. **Security Features**
   - Certificate validation
   - Signed assertion requirements
   - Encrypted assertions support
   - Session timeout management
   - CSRF protection

### Technical Stack

- **PHP Library**: Use `onelogin/php-saml` (composer package)
- **FreePBX Version**: 17.0+
- **PHP Version**: 7.4+
- **Database**: MySQL/MariaDB
- **Dependencies**: 
  - framework >= 17.0.0
  - userman >= 17.0.0

### File Structure

```
samlauth/
├── module.xml                 # Module definition
├── install.sql               # Database schema
├── uninstall.sql             # Cleanup
├── composer.json             # PHP dependencies
├── Samlauth.class.php        # Main module class (BMO)
├── functions.inc.php         # Helper functions
├── page.samlauth.php         # Admin UI page
├── assets/
│   ├── js/
│   │   └── samlauth.js       # Frontend JavaScript
│   └── css/
│       └── samlauth.css      # Styles
├── views/
│   ├── main.php              # Main configuration page
│   ├── wizard.php            # Setup wizard
│   ├── idp_form.php          # IdP configuration form
│   └── test.php              # Connection test page
├── templates/
│   ├── azure.json            # Azure AD preset
│   ├── okta.json             # Okta preset
│   ├── onelogin.json         # OneLogin preset
│   ├── google.json           # Google Workspace preset
│   └── auth0.json            # Auth0 preset
├── agi-bin/
│   └── saml_callback.php     # SAML callback handler
├── hooks/
│   ├── login.php             # Login hook
│   └── logout.php            # Logout hook
└── README.md                 # Documentation
```

### Database Schema

Create tables for:
- `saml_idp_config` - IdP configurations
- `saml_user_settings` - Per-user SAML settings
- `saml_group_settings` - Per-group SAML settings
- `saml_sessions` - Active SAML sessions
- `saml_settings` - Module settings

### Key Functions to Implement

1. **IdP Management**
   - `getIdpConfigs()` - List all IdPs
   - `getIdpConfig($id)` - Get specific IdP
   - `saveIdpConfig($data)` - Save/update IdP
   - `deleteIdpConfig($id)` - Remove IdP
   - `testIdpConnection($id)` - Test IdP connectivity

2. **User/Group Settings**
   - `getUserSamlSettings($userId)` - Get user settings
   - `setUserSamlEnabled($userId, $enabled, $idpId)` - Enable/disable per user
   - `getGroupSamlSettings($groupId)` - Get group settings
   - `setGroupSamlEnabled($groupId, $enabled, $idpId)` - Enable/disable per group

3. **SAML Authentication Flow**
   - `initiateSamlLogin($idpId)` - Start SSO flow
   - `processSamlResponse()` - Handle IdP callback
   - `validateSamlAssertion($response)` - Validate SAML response
   - `createOrUpdateUser($samlData)` - Auto-provision users
   - `initiateSamlLogout()` - Start SLO flow
   - `processSamlLogout()` - Handle SLO callback

4. **Configuration Helpers**
   - `getSpMetadata()` - Generate SP metadata XML
   - `getSpEntityId()` - Get SP entity ID
   - `getSpAcsUrl()` - Get Assertion Consumer Service URL
   - `getSpSlsUrl()` - Get Single Logout Service URL
   - `loadIdpTemplate($provider)` - Load preset configuration

5. **Admin UI Functions**
   - Configuration wizard (multi-step form)
   - IdP list/add/edit/delete
   - User SAML settings management
   - Group SAML settings management
   - Test connection interface
   - Metadata download/upload

### Admin Interface Requirements

Create a clean, intuitive UI with:
- Dashboard showing configured IdPs
- "Add New IdP" wizard button
- Table of IdPs with enable/disable toggles
- Edit/Delete/Test actions per IdP
- User settings tab
- Group settings tab
- Global settings tab
- Help/documentation links

### Integration with FreePBX Auth

Hook into FreePBX authentication:
- Modify login page to show "Sign in with [IdP]" buttons
- Detect if user has SAML enabled
- Redirect to appropriate IdP
- Handle callback and create session
- Integrate with existing FreePBX session management

### Configuration Wizard Steps

1. **Choose Provider**: Select from presets or custom
2. **IdP Details**: Enter entity ID, SSO URL, certificate
3. **Attribute Mapping**: Map SAML attributes to FreePBX fields
4. **User/Group Settings**: Configure access control
5. **Review & Test**: Summary and connection test
6. **Complete**: Save and enable

### Security Considerations

- Validate all SAML responses
- Check signatures on assertions and responses
- Implement replay attack prevention
- Use secure session handling
- Sanitize all inputs
- CSRF token protection
- Proper error handling without information disclosure

### Documentation to Include

- README.md with:
  - Installation instructions
  - Configuration guide
  - Supported IdPs and setup steps
  - Troubleshooting
  - API documentation
  - Contributing guidelines

### Testing Requirements

- Test with multiple IdP providers
- Verify SP-initiated flow
- Verify IdP-initiated flow
- Test SLO functionality
- Test user auto-provisioning
- Test attribute mapping
- Test error handling

### Additional Features (Nice to Have)

- SAML metadata auto-refresh
- Multiple certificate support (rollover)
- Audit logging of SAML events
- User self-service SAML linking
- Admin can impersonate users
- SAML debugging mode with detailed logs
- Export/import IdP configurations
- Backup/restore support

## Implementation Notes

- Follow FreePBX coding standards
- Use FreePBX BMO (Base Module Object) pattern
- Implement all required BMO methods (install, uninstall, backup, restore)
- Use FreePBX database abstraction
- Follow PSR-4 autoloading
- Add proper PHPDoc comments
- Make it fully translatable (i18n ready)
- Ensure compatibility with FreePBX 17 GUI framework

## License

GPLv3 - Keep it fully open source!

## Build Instructions

1. Create all files in proper FreePBX module structure
2. Use composer to include `onelogin/php-saml`
3. Write clean, well-documented PHP code
4. Create intuitive admin interface
5. Test with at least Azure AD and Okta
6. Package as installable FreePBX module

Build this as a production-ready, enterprise-quality module that matches or exceeds the commercial version's functionality!
