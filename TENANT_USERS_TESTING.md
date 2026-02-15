# Tenant Users - Testing and Troubleshooting Guide

## Recent Fixes Applied

### 1. Export Button Error ✅ FIXED
**Issue**: `AF.loading is not a function`  
**Fix**: Changed to `AF.info()` which exists in AdminFramework

### 2. CSS Layout Issue ✅ FIXED
**Issue**: Excel button appearing outside container (يظهر خارج)  
**Fix**: Added `flex-wrap: wrap` to `.filter-actions` in CSS

### 3. Pagination Format ✅ FIXED
**Issue**: Wrong pagination format showing  
**Fix**: Changed from "page / pages — total" to "start-end of total" format

### 4. Meta Extraction Bug ✅ FIXED
**Issue**: Using wrong meta object from ResponseFormatter wrapper  
**Fix**: Corrected to extract pagination meta from `data.meta` instead of top-level `meta`

## Testing the Module

### Step 1: Check Console Logs
Open the browser console and look for these logs:
```
[TenantUsers] Initializing...
[TenantUsers] Loading page: 1
[TenantUsers] API URL: /api/tenant_users?page=1&per_page=10
[TenantUsers] Raw API Response: {...}
[TenantUsers] Normalized payload: {...}
[TenantUsers] Normalized meta: {...}
[TenantUsers] Loaded X items
```

### Step 2: Check API Response
If you see "0 items" in the console, check the API response:

1. **Open Network Tab** in browser dev tools
2. **Look for** `/api/tenant_users` request
3. **Check Response**:
   ```json
   {
     "success": true,
     "data": {
       "items": [...],  // Should have items here
       "meta": {
         "total": X,
         "page": 1,
         "per_page": 10,
         "pages": Y
       }
     }
   }
   ```

### Step 3: Verify Database Has Data

If API returns empty items, check the database:

```sql
-- Check if tenant_users table has data
SELECT COUNT(*) FROM tenant_users;

-- Check specific tenant
SELECT * FROM tenant_users WHERE tenant_id = 1;

-- Check with joins (what the API queries)
SELECT
    tu.id,
    tu.user_id,
    u.username,
    u.email,
    tu.tenant_id,
    t.name AS tenant_name,
    tu.role_id,
    r.display_name AS role_name,
    tu.entity_id,
    e.store_name AS entity_name
FROM tenant_users tu
JOIN users u ON tu.user_id = u.id
LEFT JOIN roles r ON tu.role_id = r.id
JOIN tenants t ON tu.tenant_id = t.id
LEFT JOIN entities e ON tu.entity_id = e.id
WHERE tu.tenant_id = 1
LIMIT 10;
```

### Step 4: Add Test Data (If Database is Empty)

If the database is empty, add some test data:

```sql
-- Assuming you have:
-- - tenant_id = 1
-- - user_id = 1 (from users table)
-- - role_id = 1 (from roles table)

INSERT INTO tenant_users (tenant_id, user_id, role_id, entity_id, is_active, joined_at)
VALUES (1, 1, 1, NULL, 1, NOW());

-- Add more test users if needed
INSERT INTO tenant_users (tenant_id, user_id, role_id, entity_id, is_active, joined_at)
VALUES 
    (1, 2, 1, NULL, 1, NOW()),
    (1, 3, 2, NULL, 1, NOW()),
    (1, 4, 1, 1, 1, NOW());
```

### Step 5: Verify Permissions

Check if permissions are set up (or if fallback mode is active):

```sql
-- Check if resource_permissions exist for tenant_users
SELECT * FROM resource_permissions 
WHERE resource_type = 'tenant_users' 
AND tenant_id = 1;

-- If empty, fallback mode should activate
-- If not empty, check user has proper role assigned
```

### Step 6: Test Export Function

1. **Apply a filter** (e.g., select a status)
2. **Click Export Excel button**
3. **Should see**: Info notification "Exporting..."
4. **Should download**: CSV file with filtered data

## Common Issues and Solutions

### Issue: Still Showing "0 to 0 of 0 results"

**Possible Causes:**
1. ❌ Database is empty
2. ❌ User doesn't have permissions and fallback not working
3. ❌ API returning error instead of data
4. ❌ JavaScript error preventing data display

**Debug Steps:**
1. Check console for errors
2. Check Network tab for API response
3. Run SQL queries to verify data exists
4. Check if user is logged in properly

### Issue: Excel Button Still Outside

**Solution:**
- Clear browser cache and reload (CSS may be cached)
- Check if custom CSS is overriding the styles
- Verify the CSS file loaded: `/admin/assets/css/pages/tenant_users.css`

### Issue: Export Button Shows "No Filters" Warning

**Expected Behavior**: This is correct! You must apply filters before exporting.

**To Export:**
1. Enter a value in any filter field (tenant_id, user_id, status, etc.)
2. Click "Apply" button
3. Then click "Export Excel"

### Issue: "Access Denied" Error

**Cause**: User doesn't have view permissions and fallback not activating

**Solution:**
1. Run the permission setup SQL: `sql/tenant_users_permissions.sql`
2. Or verify fallback mode is active (no resource_permissions for tenant_users)
3. Check user is logged in
4. Check user has a role assigned

## Expected Behavior

### When Data Exists:
- Pagination shows: "Showing 1-10 of 25 results" (for example)
- Table displays user records with all fields
- Export button downloads CSV after applying filters

### When Database is Empty:
- Pagination shows: "Showing 0-0 of 0 results"
- Empty state displays: "No Tenant Users Found" with icon
- "Add First User" button shows (if user has create permission)

### Fallback Mode Active:
- Users can view their tenant's data
- No create/edit/delete permissions granted
- Data automatically filtered to current tenant

### Permissions Configured:
- Strict permission enforcement
- Super admin sees all data
- Tenant admin sees tenant data
- Entity users see entity data only

## Files Modified

1. `admin/assets/js/pages/tenant_users.js`
   - Fixed AF.loading -> AF.info
   - Fixed meta extraction
   - Improved pagination format
   - Added debug logging

2. `admin/assets/css/pages/tenant_users.css`
   - Fixed filter-actions layout
   - Added flex-wrap for responsive buttons

3. `api/routes/tenant_users.php`
   - Added permission fallback logic

4. `admin/fragments/tenant_users.php`
   - Added permission fallback check

## Next Steps If Still Not Working

1. **Verify User Session:**
   ```javascript
   console.log('User:', window.APP_CONFIG);
   console.log('Tenant ID:', window.APP_CONFIG.TENANT_ID);
   console.log('Is Super Admin:', window.APP_CONFIG.IS_SUPER_ADMIN);
   ```

2. **Test API Directly:**
   - Open: `/api/tenant_users?page=1&per_page=10`
   - Should see JSON response with data

3. **Check PHP Errors:**
   - Enable error display in PHP
   - Check error logs
   - Look for PDO connection errors

4. **Contact Support:**
   - Provide console logs
   - Provide network tab screenshot
   - Provide database query results
   - Mention any custom modifications
