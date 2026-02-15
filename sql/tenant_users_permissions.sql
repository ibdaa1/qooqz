-- ============================================================================
-- Tenant Users Permissions Setup
-- ============================================================================
-- This file creates the necessary permissions for the tenant_users module
-- Run this script to set up permissions for tenant users management
--
-- Prerequisites:
-- - permissions table exists
-- - resource_permissions table exists
-- - roles table has at least 'super_admin' role
-- - tenant_id should be set to your actual tenant ID (default: 1)
-- ============================================================================

SET @tenant_id = 1; -- Change this to your tenant ID

-- ============================================================================
-- 1. CREATE ROLE-BASED PERMISSIONS
-- ============================================================================

-- Insert tenant_users permissions (if they don't exist)
INSERT IGNORE INTO permissions (tenant_id, key_name, display_name, description, module, is_active)
VALUES
    (@tenant_id, 'tenant_users.manage', 'Manage Tenant Users', 'Full management of tenant user assignments', 'tenant_users', 1),
    (@tenant_id, 'tenant_users.view', 'View Tenant Users', 'View tenant user assignments', 'tenant_users', 1),
    (@tenant_id, 'tenant_users.create', 'Create Tenant Users', 'Create new tenant user assignments', 'tenant_users', 1),
    (@tenant_id, 'tenant_users.edit', 'Edit Tenant Users', 'Edit existing tenant user assignments', 'tenant_users', 1),
    (@tenant_id, 'tenant_users.delete', 'Delete Tenant Users', 'Delete tenant user assignments', 'tenant_users', 1);

-- ============================================================================
-- 2. CREATE RESOURCE-BASED PERMISSIONS
-- ============================================================================

-- Get permission IDs
SET @perm_manage_id = (SELECT id FROM permissions WHERE key_name = 'tenant_users.manage' AND tenant_id = @tenant_id LIMIT 1);
SET @perm_view_id = (SELECT id FROM permissions WHERE key_name = 'tenant_users.view' AND tenant_id = @tenant_id LIMIT 1);
SET @perm_create_id = (SELECT id FROM permissions WHERE key_name = 'tenant_users.create' AND tenant_id = @tenant_id LIMIT 1);

-- Get super_admin role ID
SET @super_admin_role_id = (SELECT id FROM roles WHERE key_name = 'super_admin' AND tenant_id = @tenant_id LIMIT 1);

-- Create resource permissions for super_admin role
-- Super admin has full access to all tenant users across all tenants
INSERT IGNORE INTO resource_permissions (
    permission_id,
    role_id,
    tenant_id,
    resource_type,
    can_view_all,
    can_view_own,
    can_view_tenant,
    can_create,
    can_edit_all,
    can_edit_own,
    can_delete_all,
    can_delete_own
)
VALUES (
    @perm_manage_id,
    @super_admin_role_id,
    @tenant_id,
    'tenant_users',
    1, -- can_view_all
    1, -- can_view_own
    1, -- can_view_tenant
    1, -- can_create
    1, -- can_edit_all
    1, -- can_edit_own
    1, -- can_delete_all
    1  -- can_delete_own
);

-- ============================================================================
-- 3. EXAMPLE: CREATE PERMISSIONS FOR TENANT ADMIN ROLE
-- ============================================================================
-- Uncomment and customize for your tenant_admin role

/*
SET @tenant_admin_role_id = (SELECT id FROM roles WHERE key_name = 'tenant_admin' AND tenant_id = @tenant_id LIMIT 1);

-- Tenant admin can view and manage all users within their tenant
INSERT IGNORE INTO resource_permissions (
    permission_id,
    role_id,
    tenant_id,
    resource_type,
    can_view_all,
    can_view_own,
    can_view_tenant,
    can_create,
    can_edit_all,
    can_edit_own,
    can_delete_all,
    can_delete_own
)
VALUES (
    @perm_manage_id,
    @tenant_admin_role_id,
    @tenant_id,
    'tenant_users',
    0, -- can_view_all: No (only within tenant)
    1, -- can_view_own: Yes
    1, -- can_view_tenant: Yes (can see all users in their tenant)
    1, -- can_create: Yes
    0, -- can_edit_all: No
    1, -- can_edit_own: Yes (can edit users in their tenant)
    0, -- can_delete_all: No
    1  -- can_delete_own: Yes (can delete users in their tenant)
);
*/

-- ============================================================================
-- 4. EXAMPLE: CREATE PERMISSIONS FOR ENTITY MANAGER ROLE
-- ============================================================================
-- Uncomment and customize for your entity_manager role

/*
SET @entity_manager_role_id = (SELECT id FROM roles WHERE key_name = 'entity_manager' AND tenant_id = @tenant_id LIMIT 1);

-- Entity manager can only view and manage users within their entity
INSERT IGNORE INTO resource_permissions (
    permission_id,
    role_id,
    tenant_id,
    resource_type,
    can_view_all,
    can_view_own,
    can_view_tenant,
    can_create,
    can_edit_all,
    can_edit_own,
    can_delete_all,
    can_delete_own
)
VALUES (
    @perm_view_id,
    @entity_manager_role_id,
    @tenant_id,
    'tenant_users',
    0, -- can_view_all: No
    1, -- can_view_own: Yes
    0, -- can_view_tenant: No (limited to entity)
    1, -- can_create: Yes (can add users to their entity)
    0, -- can_edit_all: No
    1, -- can_edit_own: Yes (can edit users in their entity)
    0, -- can_delete_all: No
    1  -- can_delete_own: Yes (can remove users from their entity)
);
*/

-- ============================================================================
-- 5. EXAMPLE: CREATE PERMISSIONS FOR REGULAR USER ROLE
-- ============================================================================
-- Uncomment and customize for your user role

/*
SET @user_role_id = (SELECT id FROM roles WHERE key_name = 'user' AND tenant_id = @tenant_id LIMIT 1);

-- Regular user can only view their own record
INSERT IGNORE INTO resource_permissions (
    permission_id,
    role_id,
    tenant_id,
    resource_type,
    can_view_all,
    can_view_own,
    can_view_tenant,
    can_create,
    can_edit_all,
    can_edit_own,
    can_delete_all,
    can_delete_own
)
VALUES (
    @perm_view_id,
    @user_role_id,
    @tenant_id,
    'tenant_users',
    0, -- can_view_all: No
    1, -- can_view_own: Yes (can see only their own record)
    0, -- can_view_tenant: No
    0, -- can_create: No
    0, -- can_edit_all: No
    1, -- can_edit_own: Yes (can edit only their own record)
    0, -- can_delete_all: No
    0  -- can_delete_own: No
);
*/

-- ============================================================================
-- 6. ASSIGN PERMISSIONS TO SUPER ADMIN ROLE
-- ============================================================================

-- Link all tenant_users permissions to super_admin role via role_permissions
INSERT IGNORE INTO role_permissions (tenant_id, role_id, permission_id)
SELECT @tenant_id, @super_admin_role_id, id
FROM permissions
WHERE key_name LIKE 'tenant_users.%'
  AND tenant_id = @tenant_id;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- View all tenant_users permissions
SELECT 
    p.id,
    p.key_name,
    p.display_name,
    p.description,
    p.is_active
FROM permissions p
WHERE p.key_name LIKE 'tenant_users.%'
  AND p.tenant_id = @tenant_id;

-- View all resource permissions for tenant_users
SELECT 
    rp.id,
    r.key_name AS role_key,
    r.display_name AS role_name,
    rp.resource_type,
    rp.can_view_all,
    rp.can_view_tenant,
    rp.can_view_own,
    rp.can_create,
    rp.can_edit_all,
    rp.can_edit_own,
    rp.can_delete_all,
    rp.can_delete_own
FROM resource_permissions rp
JOIN roles r ON r.id = rp.role_id
WHERE rp.resource_type = 'tenant_users'
  AND rp.tenant_id = @tenant_id;

-- View role_permissions assignments
SELECT 
    rp.id,
    r.key_name AS role_key,
    r.display_name AS role_name,
    p.key_name AS permission_key,
    p.display_name AS permission_name
FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id
JOIN permissions p ON p.id = rp.permission_id
WHERE p.key_name LIKE 'tenant_users.%'
  AND rp.tenant_id = @tenant_id;

-- ============================================================================
-- NOTES
-- ============================================================================
-- After running this script:
-- 1. Clear any cached sessions to reload permissions
-- 2. Test access with different user roles
-- 3. Verify that super admin can see all data
-- 4. Verify that tenant admins can only see their tenant's data
-- 5. Verify that entity managers can only see their entity's data
-- 6. Verify that regular users can only see their own data
-- ============================================================================
