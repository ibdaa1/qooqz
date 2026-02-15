# Tenant Users Management - Permission System Documentation

## Overview

The tenant_users management module has been updated with a comprehensive permission system that provides strict, granular control over who can view, create, edit, and delete tenant user assignments.

**Important**: The system includes a fallback mode that allows tenant-scoped access when permissions haven't been configured yet, ensuring the module works out-of-the-box while maintaining security when permissions are properly set up.

## Changes Made

### 1. Permission System Implementation ✅

#### PHP Fragment (`admin/fragments/tenant_users.php`)
- Added authentication checks using `is_admin_logged_in()`
- Implemented dual-layer permission control:
  - **Role-based permissions** via `can()` function
  - **Resource-based permissions** via `can_view_all()`, `can_view_tenant()`, etc.
- Added access denial for users without proper permissions (when configured)
- Super admin automatically gets full access to all features
- **Fallback mode**: When permissions aren't configured, grants basic tenant-scoped access

#### API Route (`api/routes/tenant_users.php`)
- Integrated with `admin_context.php` for permission checks
- Added automatic data filtering based on user permissions:
  - **Super admin**: Can view all data across all tenants
  - **Tenant users** (`can_view_tenant`): See only their tenant's data
  - **Entity users** (`can_view_own` + entity_id): See only their entity's data
  - **Regular users** (`can_view_own`): See only their own records
  - **Fallback**: If no permissions configured, defaults to tenant-scoped view

#### Repository (`api/v1/models/tenant_users/repositories/PdoTenant_usersRepository.php`)
- Modified `all()` and `count()` methods to support tenant filtering
- Special handling for super admin (tenant_id = 0 means "all tenants")

### 2. Excel Export Enhancement ✅

#### JavaScript (`admin/assets/js/pages/tenant_users.js`)
- Fixed export error: Changed `AF.loading()` to `AF.info()` for proper notification
- Export now requires filters to be applied (prevents full database dump)
- Exports respect current filters automatically
- Shows warning if no filters are applied

### 3. Entity Auto-filtering ✅
- Users with entity assignments automatically see only their entity's data
- Entity filtering is enforced at the API level
- Cannot be bypassed through frontend manipulation

## Fallback Mode (Permissions Not Configured)

**What is Fallback Mode?**
When the permission system detects that permissions haven't been configured in the database (via the SQL setup script), it automatically grants basic tenant-scoped access to logged-in users.

**Benefits:**
- Module works immediately after installation
- No 403 errors or blank screens
- Users can view data from their tenant by default
- Graceful degradation until permissions are properly set up

**Behavior in Fallback Mode:**
- All logged-in users get `can_view_tenant` access
- Data is scoped to user's current tenant
- Super admins still get full access
- No create/edit/delete permissions granted (UI hides these buttons)

**When Fallback Mode is Active:**
- No resource_permissions entries exist for tenant_users
- `admin_resource_permissions()` returns empty
- User is logged in but has no explicit permissions

**How to Exit Fallback Mode:**
Run the SQL setup script at `sql/tenant_users_permissions.sql` to configure proper permissions.

## Permission Levels

### Super Admin
- **View**: All tenant users across all tenants
- **Create**: Can create users for any tenant
- **Edit**: Can edit any tenant user
- **Delete**: Can delete any tenant user
- **Excel Export**: Can export with any filters

### Tenant Admin
- **View**: All tenant users within their tenant only
- **Create**: Can create users for their tenant
- **Edit**: Can edit users in their tenant
- **Delete**: Can delete users in their tenant
- **Excel Export**: Can export their tenant's data

### Entity Manager
- **View**: Only users assigned to their entity
- **Create**: Can add users to their entity
- **Edit**: Can edit users in their entity
- **Delete**: Can remove users from their entity
- **Excel Export**: Can export their entity's data

### Regular User
- **View**: Only their own user record
- **Create**: No
- **Edit**: Only their own record
- **Delete**: No
- **Excel Export**: No (or only their own record if filters match)

## Database Setup

### Required Tables
1. `permissions` - Role-based permissions
2. `role_permissions` - Links roles to permissions
3. `resource_permissions` - Granular resource-level permissions
4. `roles` - User roles
5. `tenant_users` - User-tenant-entity assignments

### Running the Setup Script

1. Open `sql/tenant_users_permissions.sql`
2. Update the tenant_id variable: `SET @tenant_id = 1;`
3. Execute the script in your database
4. Verify permissions were created using the verification queries at the end

### Permission Keys

#### Role-based Permissions
- `tenant_users.manage` - Full management access
- `tenant_users.view` - View access
- `tenant_users.create` - Create access
- `tenant_users.edit` - Edit access
- `tenant_users.delete` - Delete access

#### Resource-based Permissions (resource_type = 'tenant_users')
- `can_view_all` - View all users across all tenants
- `can_view_tenant` - View all users within current tenant
- `can_view_own` - View only own user record
- `can_create` - Create new tenant user assignments
- `can_edit_all` - Edit any tenant user
- `can_edit_own` - Edit only own/tenant/entity users
- `can_delete_all` - Delete any tenant user
- `can_delete_own` - Delete only own/tenant/entity users

## Testing

### Test Scenarios

1. **Super Admin Access**
   - Login as super admin
   - Navigate to tenant users page
   - Should see all users from all tenants
   - Should be able to create/edit/delete any user
   - Excel export should work with any filters

2. **Tenant Admin Access**
   - Login as tenant admin
   - Navigate to tenant users page
   - Should only see users from your tenant
   - Should be able to create/edit/delete users in your tenant
   - Should NOT see users from other tenants

3. **Entity Manager Access**
   - Login as entity manager with entity_id assigned
   - Navigate to tenant users page
   - Should only see users assigned to your entity
   - Should be able to manage users in your entity only
   - Should NOT see users from other entities

4. **Regular User Access**
   - Login as regular user
   - Navigate to tenant users page
   - Should only see your own user record
   - Should not be able to create/edit/delete any users
   - Excel export should be disabled or show only own record

5. **Excel Export with Filters**
   - Apply tenant_id filter
   - Click Export Excel
   - Should export only filtered results
   - Try export without filters - should show warning

6. **Access Denial**
   - Remove all tenant_users permissions from a role
   - Login with that role
   - Try to access tenant users page
   - Should see "Access denied" error (403)

## Security Considerations

1. **Permission Enforcement**
   - Permissions are checked on both client and server side
   - Client-side checks hide UI elements
   - Server-side checks prevent API access
   - Cannot bypass by manipulating frontend

2. **Data Filtering**
   - Automatic filtering based on permissions
   - Applied at database query level
   - Cannot retrieve data outside scope via API manipulation

3. **Session Management**
   - Permissions loaded from database into session
   - Cached for performance
   - Cleared on logout
   - Should be refreshed when permissions change

## Troubleshooting

### "0 to 0 of 0 results" Issue
**Possible Causes:**
1. No data in database
2. User lacks view permissions
3. Tenant ID filter too restrictive
4. Database connection issue

**Solutions:**
1. Check if there's data: `SELECT * FROM tenant_users LIMIT 10;`
2. Verify user permissions: Check verification queries in SQL file
3. Ensure super admin has proper permissions
4. Check browser console for errors

### "Access Denied" Error
**Possible Causes:**
1. User not logged in
2. User lacks required permissions
3. Permissions not properly configured

**Solutions:**
1. Login again
2. Run permission setup SQL script
3. Assign proper role to user
4. Clear session and login again

### Excel Export Shows Warning
**Possible Causes:**
1. No filters applied
2. No data matches filters

**Solutions:**
1. Apply at least one filter before exporting
2. Check if filters match existing data
3. Verify data exists: Check with SQL query

### Export Fails with "AF.loading is not a function"
**Cause:** AdminFramework doesn't have a `loading()` method

**Solution:** ✅ FIXED
- Updated to use `AF.info()` instead
- Export now works correctly with proper notification

## Files Modified

1. `/admin/fragments/tenant_users.php` - Added permission checks with fallback mode
2. `/api/routes/tenant_users.php` - Added permission-based filtering with fallback
3. `/api/v1/models/tenant_users/repositories/PdoTenant_usersRepository.php` - Support for super admin viewing all
4. `/admin/assets/js/pages/tenant_users.js` - Fixed export error, added filter requirement
5. `/sql/tenant_users_permissions.sql` - New permission setup script
6. `/TENANT_USERS_PERMISSIONS.md` - Comprehensive documentation

## Maintenance

### Adding New Roles
1. Create role in `roles` table
2. Add entry in `resource_permissions` for tenant_users
3. Set appropriate permission flags
4. Link to role_based permissions via `role_permissions`
5. Test with user having that role

### Modifying Permissions
1. Update `resource_permissions` flags
2. Clear user sessions to reload permissions
3. Test access levels
4. Verify data filtering works correctly

### Debugging
1. Enable error logging in PHP
2. Check browser console for JavaScript errors
3. Monitor database queries
4. Use verification queries from SQL file
5. Check admin_context.php for permission loading

## Notes for Developers

- Always check permissions before showing UI elements
- Always enforce permissions at API level
- Use both role-based and resource-based permissions
- Super admin should always have full access
- Test with multiple user roles
- Document any custom permission logic
- Keep permissions in sync with actual capabilities

## Arabic Translation Notes

Permission system messages are available in both English and Arabic:
- Access denied messages
- Export warnings
- Filter requirements
- Permission-related UI labels

Translation files: `/languages/TenantUsers/ar.json` and `/languages/TenantUsers/en.json`
