<?php
namespace FreePBX\modules\Samlauth;

/**
 * SAML Attribute Mapper
 *
 * Securely maps and validates SAML attributes
 * Prevents privilege escalation and attribute injection attacks
 */
class SamlAttributeMapper {

    /**
     * Attribute mapping configuration
     * Defines how SAML attributes map to user fields with validation
     */
    private $attributeMappings = array(
        'email' => array(
            'saml_attribute' => 'email',
            'required' => true,
            'validation' => 'email',
            'default' => null,
        ),
        'firstName' => array(
            'saml_attribute' => 'firstName',
            'required' => false,
            'validation' => 'alpha',
            'default' => '',
        ),
        'lastName' => array(
            'saml_attribute' => 'lastName',
            'required' => false,
            'validation' => 'alpha',
            'default' => '',
        ),
        'displayName' => array(
            'saml_attribute' => 'displayName',
            'required' => false,
            'validation' => 'alphanumeric',
            'default' => '',
        ),
        'groups' => array(
            'saml_attribute' => 'groups',
            'required' => false,
            'validation' => 'array',
            'default' => array(),
        ),
        'roles' => array(
            'saml_attribute' => 'role',
            'required' => false,
            'validation' => 'array',
            'default' => array(),
        ),
    );

    /**
     * Map SAML attributes to user attributes
     * @param array $samlAttributes Raw SAML attributes from IdP
     * @return array Mapped and validated attributes
     */
    public function mapAttributes($samlAttributes) {
        $mapped = array();

        foreach ($this->attributeMappings as $key => $config) {
            $samlKey = $config['saml_attribute'];

            // Try multiple common attribute name variations
            $value = $this->findAttribute($samlAttributes, $samlKey);

            // SAML attributes are typically arrays
            if (is_array($value) && $config['validation'] !== 'array') {
                $value = !empty($value) ? $value[0] : $config['default'];
            }

            // Handle missing required attributes
            if ($config['required'] && empty($value)) {
                throw new \Exception("Required SAML attribute missing: {$key}");
            }

            // Use default if empty
            if (empty($value)) {
                $value = $config['default'];
            }

            // Validate and sanitize
            if (!empty($value)) {
                try {
                    $value = $this->validateAttribute($value, $config['validation']);
                } catch (\Exception $e) {
                    error_log("SAML: Attribute validation failed for {$key}: " . $e->getMessage());
                    // Use default for invalid attributes
                    $value = $config['default'];
                }
            }

            $mapped[$key] = $value;
        }

        return $mapped;
    }

    /**
     * Find attribute value from SAML attributes (case-insensitive)
     * @param array $samlAttributes SAML attributes
     * @param string $key Attribute key to find
     * @return mixed Attribute value or null
     */
    private function findAttribute($samlAttributes, $key) {
        // Try exact match first
        if (isset($samlAttributes[$key])) {
            return $samlAttributes[$key];
        }

        // Try common variations for email
        if ($key === 'email') {
            $emailKeys = array('mail', 'emailAddress', 'email', 'Email', 'MAIL');
            foreach ($emailKeys as $emailKey) {
                if (isset($samlAttributes[$emailKey])) {
                    return $samlAttributes[$emailKey];
                }
            }
        }

        // Try common variations for names
        if ($key === 'firstName') {
            $nameKeys = array('givenName', 'firstname', 'FirstName', 'given_name');
            foreach ($nameKeys as $nameKey) {
                if (isset($samlAttributes[$nameKey])) {
                    return $samlAttributes[$nameKey];
                }
            }
        }

        if ($key === 'lastName') {
            $nameKeys = array('surname', 'sn', 'lastname', 'LastName', 'family_name');
            foreach ($nameKeys as $nameKey) {
                if (isset($samlAttributes[$nameKey])) {
                    return $samlAttributes[$nameKey];
                }
            }
        }

        // Try common variations for groups
        if ($key === 'groups') {
            $groupKeys = array('groups', 'memberOf', 'group', 'Groups', 'member_of');
            foreach ($groupKeys as $groupKey) {
                if (isset($samlAttributes[$groupKey])) {
                    return $samlAttributes[$groupKey];
                }
            }
        }

        // Try common variations for roles
        if ($key === 'role') {
            $roleKeys = array('role', 'roles', 'Role', 'Roles');
            foreach ($roleKeys as $roleKey) {
                if (isset($samlAttributes[$roleKey])) {
                    return $samlAttributes[$roleKey];
                }
            }
        }

        return null;
    }

    /**
     * Validate and sanitize attribute value
     * @param mixed $value Attribute value
     * @param string $type Validation type
     * @return mixed Validated value
     * @throws \Exception on validation failure
     */
    private function validateAttribute($value, $type) {
        switch ($type) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \Exception("Invalid email format: {$value}");
                }
                return strtolower(trim($value));

            case 'alpha':
                // Only letters, spaces, hyphens, and apostrophes
                return preg_replace("/[^a-zA-Z\s\-']/", '', $value);

            case 'alphanumeric':
                // Letters, numbers, spaces, and common punctuation
                return preg_replace('/[^a-zA-Z0-9\s\-_.]/', '', $value);

            case 'array':
                if (!is_array($value)) {
                    $value = array($value);
                }
                // Validate each element
                return array_map(function($item) {
                    return preg_replace('/[^a-zA-Z0-9\s\-_.]/', '', $item);
                }, $value);

            default:
                return $value;
        }
    }

    /**
     * Map SAML roles to FreePBX permissions
     * CRITICAL: Never trust role/privilege attributes directly from IdP
     * @param array $samlRoles Roles from SAML assertion
     * @return array FreePBX permissions
     */
    public function mapRolesToPermissions($samlRoles) {
        // Whitelist of allowed role mappings
        $rolePermissions = array(
            'Administrator' => array('sections' => '*', 'permissions' => 'admin'),
            'Manager' => array('sections' => 'settings,reports', 'permissions' => 'edit'),
            'User' => array('sections' => 'basic', 'permissions' => 'view'),
            'Technician' => array('sections' => 'extensions,queues', 'permissions' => 'edit'),
        );

        // Default to least privilege
        $permissions = array('sections' => 'basic', 'permissions' => 'view');

        // Only accept whitelisted roles
        foreach ($samlRoles as $role) {
            $role = trim($role);

            if (isset($rolePermissions[$role])) {
                // Use highest privilege found
                if ($this->comparePrivilegeLevel($rolePermissions[$role], $permissions) > 0) {
                    $permissions = $rolePermissions[$role];
                }
            } else {
                // Log unknown roles for security monitoring
                error_log("SAML: Unknown role ignored: {$role}");
            }
        }

        return $permissions;
    }

    /**
     * Compare privilege levels
     * @param array $role1 First role
     * @param array $role2 Second role
     * @return int -1 if role1 < role2, 0 if equal, 1 if role1 > role2
     */
    private function comparePrivilegeLevel($role1, $role2) {
        $levels = array('view' => 1, 'edit' => 2, 'admin' => 3);

        $level1 = $levels[$role1['permissions']] ?? 0;
        $level2 = $levels[$role2['permissions']] ?? 0;

        if ($level1 < $level2) {
            return -1;
        } elseif ($level1 > $level2) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Map SAML groups to FreePBX groups
     * @param array $samlGroups Groups from SAML assertion
     * @return array Mapped FreePBX groups
     */
    public function mapGroups($samlGroups) {
        // Whitelist of allowed group mappings
        $groupMapping = array(
            'IT Department' => 'tech',
            'Sales' => 'sales',
            'Support' => 'support',
            'Management' => 'managers',
        );

        $mappedGroups = array();

        foreach ($samlGroups as $samlGroup) {
            $samlGroup = trim($samlGroup);

            if (isset($groupMapping[$samlGroup])) {
                $mappedGroups[] = $groupMapping[$samlGroup];
            } else {
                error_log("SAML: Unknown group ignored: {$samlGroup}");
            }
        }

        // If no valid groups, assign default
        if (empty($mappedGroups)) {
            $mappedGroups = array('default');
        }

        return $mappedGroups;
    }

    /**
     * Validate email domain against whitelist
     * @param string $email Email address
     * @param array $allowedDomains Array of allowed domains
     * @return bool True if valid
     */
    public function validateEmailDomain($email, $allowedDomains = array()) {
        if (empty($allowedDomains)) {
            return true; // No restriction
        }

        $domain = substr(strrchr($email, "@"), 1);

        return in_array($domain, $allowedDomains);
    }

    /**
     * Get attribute mapping configuration
     * Allows customization of attribute mappings
     * @return array Attribute mappings
     */
    public function getAttributeMappings() {
        return $this->attributeMappings;
    }

    /**
     * Set custom attribute mapping
     * @param string $key Internal attribute key
     * @param array $config Configuration array
     */
    public function setAttributeMapping($key, $config) {
        $this->attributeMappings[$key] = $config;
    }
}
