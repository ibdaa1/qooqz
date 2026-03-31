-- database/migrations/seed_default_resource_permissions.sql
-- Seeds default resource_permissions rows for the super_admin role (role_id=1).
-- Links permission_id to the manage_resource_permissions permission slug
-- and creates default full-access rows for all common resource types.
--
-- This uses INSERT ... SELECT ... WHERE NOT EXISTS for idempotent re-runs.
-- Requires: permissions table with slug='manage_resource_permissions',
--           roles table with name='super_admin' (id=1).

-- Super-admin gets full access to all resource types by default.
-- The permission_id references the 'manage_resource_permissions' permission entry.

INSERT INTO `resource_permissions`
  (`permission_id`, `role_id`, `tenant_id`, `resource_type`,
   `can_view_all`, `can_view_own`, `can_view_tenant`,
   `can_create`, `can_edit_all`, `can_edit_own`,
   `can_delete_all`, `can_delete_own`, `created_at`)
SELECT
  p.id, 1, 1, rt.resource_type,
  1, 1, 1, 1, 1, 1, 1, 1, NOW()
FROM `permissions` p
CROSS JOIN (
  SELECT 'products'          AS resource_type UNION ALL
  SELECT 'orders'            AS resource_type UNION ALL
  SELECT 'users'             AS resource_type UNION ALL
  SELECT 'entities'          AS resource_type UNION ALL
  SELECT 'categories'        AS resource_type UNION ALL
  SELECT 'banners'           AS resource_type UNION ALL
  SELECT 'discounts'         AS resource_type UNION ALL
  SELECT 'subscriptions'     AS resource_type UNION ALL
  SELECT 'notifications'     AS resource_type UNION ALL
  SELECT 'settings'          AS resource_type UNION ALL
  SELECT 'roles'             AS resource_type UNION ALL
  SELECT 'permissions'       AS resource_type UNION ALL
  SELECT 'themes'            AS resource_type UNION ALL
  SELECT 'media'             AS resource_type UNION ALL
  SELECT 'vendors'           AS resource_type UNION ALL
  SELECT 'tenants'           AS resource_type
) rt
WHERE p.slug = 'manage_resource_permissions'
  AND NOT EXISTS (
    SELECT 1 FROM `resource_permissions` rp
     WHERE rp.permission_id = p.id
       AND rp.role_id = 1
       AND rp.tenant_id = 1
       AND rp.resource_type = rt.resource_type
  );
