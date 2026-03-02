<?php
declare(strict_types=1);
/**
 * /admin/fragments/delivery.php
 * Complete Delivery Management Workspace
 */

 $isAjax     = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
 $isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
 $isFragment = $isAjax || $isEmbedded;

if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

if (!is_admin_logged_in()) {
    if ($isFragment) { http_response_code(401); echo json_encode(['error' => 'Not authenticated']); exit; }
    else { header('Location: /admin/login.php'); exit; }
}

 $user     = admin_user();
 $lang     = admin_lang();
 $dir      = in_array($lang, ['ar','he','fa','ur']) ? 'rtl' : 'ltr';
 $csrf     = admin_csrf();
 $tenantId = admin_tenant_id();
 $userId   = admin_user_id();

 $canCreate  = can_create('delivery')  || can('delivery.manage');
 $canEdit    = can_edit_all('delivery') || can('delivery.manage');
 $canDelete  = can_delete_all('delivery') || can('delivery.manage');
 $canView    = can_view_all('delivery') || can('delivery.manage');

if (!$canView && !is_super_admin()) {
    if ($isFragment) { http_response_code(403); echo json_encode(['error' => 'Access denied']); exit; }
    http_response_code(403); die('Access denied');
}

function __t(string $key, string $fallback = ''): string {
    if (function_exists('i18n_get')) { $v = i18n_get($key); return $v ?? ($fallback ?: $key); }
    return $fallback ?: $key;
}
?>
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/delivery.css?v=<?= time() ?>">
<?php endif; ?>
<meta data-page="delivery" data-i18n-files="/languages/Delivery/<?= rawurlencode($lang) ?>.json">

<div class="page-container" id="deliveryPageContainer" dir="<?= htmlspecialchars($dir) ?>">

<?php if (!$isFragment): ?>
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title" data-i18n="delivery.title"><?= __t('delivery.title','Delivery Management') ?></h1>
        <p class="page-subtitle" data-i18n="delivery.subtitle"><?= __t('delivery.subtitle','Manage zones, providers, orders and tracking') ?></p>
    </div>
</div>
<?php endif; ?>

<!-- ═══ WORKSPACE TABS ═══ -->
<div class="workspace-tabs" id="workspaceTabs">
    <button class="tab-btn active" data-tab="zones"><i class="fas fa-map-marked-alt"></i> <span data-i18n="workspace.tabs.zones">Zones</span></button>
    <button class="tab-btn" data-tab="providers"><i class="fas fa-motorcycle"></i> <span data-i18n="workspace.tabs.providers">Providers</span></button>
    <button class="tab-btn" data-tab="orders"><i class="fas fa-box"></i> <span data-i18n="workspace.tabs.orders">Orders</span></button>
    <button class="tab-btn" data-tab="locations"><i class="fas fa-location-arrow"></i> <span data-i18n="workspace.tabs.locations">Locations</span></button>
    <button class="tab-btn" data-tab="tracking"><i class="fas fa-route"></i> <span data-i18n="workspace.tabs.tracking">Tracking</span></button>
    <button class="tab-btn" data-tab="provider_zones"><i class="fas fa-link"></i> <span data-i18n="workspace.tabs.provider_zones">Provider Zones</span></button>
</div>

<!-- ══════════════════════════════════════════
     TAB: ZONES
══════════════════════════════════════════ -->
<div id="zonesTab" class="ws-panel active">
    <div id="zoneFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="zoneFormTitle" data-i18n="form.add_title">Add Zone</h3>
            <button class="btn btn-sm btn-outline" id="zoneCloseForm"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="zoneForm" novalidate>
                <input type="hidden" id="zoneId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="required">Zone Name</label>
                        <input type="text" id="zoneName" name="zone_name" class="form-control" required>
                    </div>
                    <div class="form-group col-3">
                        <label class="required">Type</label>
                        <select id="zoneType" name="zone_type" class="form-control" required>
                            <option value="city">City</option>
                            <option value="district">District</option>
                            <option value="radius">Radius</option>
                            <option value="polygon">Polygon</option>
                        </select>
                    </div>
                    <div class="form-group col-3">
                        <label>Provider</label>
                        <select id="zoneProviderId" name="provider_id" class="form-control">
                            <option value="">-- None --</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-4">
                        <label>City</label>
                        <select id="zoneCityId" name="city_id" class="form-control">
                            <option value="">-- Select City --</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label>Delivery Fee</label>
                        <input type="number" id="zoneFee" name="delivery_fee" class="form-control" step="0.01" value="0.00">
                    </div>
                    <div class="form-group col-4">
                        <label>Est. Time (min)</label>
                        <input type="number" id="zoneTime" name="estimated_minutes" class="form-control" value="45">
                    </div>
                </div>
                <!-- Radius specific fields -->
                <div id="radiusFields" class="form-row" style="display:none">
                    <div class="form-group col-4">
                        <label>Center Lat</label>
                        <input type="text" id="zoneLat" name="center_lat" class="form-control">
                    </div>
                    <div class="form-group col-4">
                        <label>Center Lng</label>
                        <input type="text" id="zoneLng" name="center_lng" class="form-control">
                    </div>
                    <div class="form-group col-4">
                        <label>Radius (km)</label>
                        <input type="number" id="zoneRadius" name="radius_km" class="form-control" step="0.01">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label>Min Order Value</label>
                        <input type="number" id="zoneMinOrder" name="min_order_value" class="form-control" step="0.01">
                    </div>
                    <div class="form-group col-6">
                        <label>Free Delivery Over</label>
                        <input type="number" id="zoneFreeDelivery" name="free_delivery_over" class="form-control" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="zoneActive" name="is_active" value="1" checked>
                        <span>Active</span>
                    </label>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="btn btn-outline" id="zoneCancelBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group"><label>Search</label><input type="text" id="zonesSearch" class="form-control"></div>
                <div class="filter-group"><label>Type</label>
                    <select id="zonesTypeFilter" class="form-control">
                        <option value="">All</option><option value="city">City</option><option value="district">District</option>
                        <option value="radius">Radius</option><option value="polygon">Polygon</option>
                    </select>
                </div>
                <div class="filter-group"><label>Active</label>
                    <select id="zonesActiveFilter" class="form-control">
                        <option value="">All</option><option value="1">Active</option><option value="0">Inactive</option>
                    </select>
                </div>
                <div class="filter-actions">
                    <button id="zonesApplyFilter" class="btn btn-secondary">Apply</button>
                    <button id="zonesResetFilter" class="btn btn-outline">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <h3 class="card-title">Delivery Zones</h3>
            <div class="card-actions">
                <?php if ($canCreate): ?><button id="zonesAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add</button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div id="zonesTableLoading" class="loading-state"><div class="spinner"></div><p>Loading...</p></div>
            <div id="zonesTableContainer" style="display:none">
                <div class="table-responsive"><table class="data-table"><thead><tr>
                    <th>ID</th><th>Name</th><th>Type</th><th>Fee</th><th>Est. Time</th><th>Status</th><th>Actions</th>
                </tr></thead><tbody id="zonesTableBody"></tbody></table></div>
                <div class="pagination-wrapper"><span id="zonesPaginationInfo"></span><div id="zonesPagination" class="pagination"></div></div>
            </div>
            <div id="zonesEmptyState" class="empty-state" style="display:none"><div class="empty-icon">🗺️</div><h3>No Zones</h3></div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════
     TAB: PROVIDERS
══════════════════════════════════════════ -->
<div id="providersTab" class="ws-panel" style="display:none">
    <div id="providerFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="providerFormTitle">Provider Details</h3>
            <button class="btn btn-sm btn-outline" id="providerCloseForm"><i class="fas fa-times"></i></button>
        </div>
        <div class="card-body">
            <form id="providerForm" novalidate>
                <input type="hidden" id="providerId" name="id">
                <div class="form-row">
                    <div class="form-group col-4">
                        <label class="required">Type</label>
                        <select id="providerType" name="provider_type" class="form-control" required>
                            <option value="company">Company</option>
                            <option value="entity_driver">Entity Driver</option>
                            <option value="independent_driver">Independent Driver</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label>Vehicle</label>
                        <select id="providerVehicle" name="vehicle_type" class="form-control">
                            <option value="bike">Bike</option>
                            <option value="car">Car</option>
                            <option value="van">Van</option>
                            <option value="truck">Truck</option>
                        </select>
                    </div>
                    <div class="form-group col-4">
                        <label>License</label>
                        <input type="text" id="providerLicense" name="license_number" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6">
                        <label class="checkbox-label"><input type="checkbox" id="providerOnline" name="is_online" value="1"> Online</label>
                    </div>
                    <div class="form-group col-6">
                        <label class="checkbox-label"><input type="checkbox" id="providerActive" name="is_active" value="1" checked> Active</label>
                    </div>
                </div>
                <div class="form-actions-footer">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    <button type="button" class="btn btn-outline" id="providerCancelBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Filters and Table similar to Zones, truncated for brevity but present in logic -->
    <div class="card filter-card"><div class="card-body"><div class="filters-grid">
        <div class="filter-group"><label>Search</label><input type="text" id="providersSearch" class="form-control"></div>
        <div class="filter-group"><label>Type</label><select id="providersTypeFilter" class="form-control"><option value="">All</option><option value="company">Company</option><option value="entity_driver">Driver</option></select></div>
        <div class="filter-actions"><button id="providersApplyFilter" class="btn btn-secondary">Apply</button></div>
    </div></div></div>
    <div class="card table-card"><div class="card-header"><h3 class="card-title">Providers</h3><div class="card-actions"><?php if ($canCreate): ?><button id="providersAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add</button><?php endif; ?></div></div>
    <div class="card-body"><div id="providersTableLoading" class="loading-state"><div class="spinner"></div></div><div id="providersTableContainer" style="display:none"><div class="table-responsive"><table class="data-table"><thead><tr><th>ID</th><th>Type</th><th>Vehicle</th><th>Online</th><th>Rating</th><th>Deliveries</th><th>Actions</th></tr></thead><tbody id="providersTableBody"></tbody></table></div><div class="pagination-wrapper"><span id="providersPaginationInfo"></span><div id="providersPagination" class="pagination"></div></div></div><div id="providersEmptyState" class="empty-state" style="display:none"><h3>No Providers</h3></div></div></div>
</div>

<!-- ══════════════════════════════════════════
     TAB: ORDERS
══════════════════════════════════════════ -->
<div id="ordersTab" class="ws-panel" style="display:none">
    <div id="orderFormContainer" class="card form-card" style="display:none">
        <div class="card-header"><h3 class="card-title">Order Details</h3><button class="btn btn-sm btn-outline" id="orderCloseForm"><i class="fas fa-times"></i></button></div>
        <div class="card-body">
            <form id="orderForm" novalidate>
                <input type="hidden" id="orderId" name="id">
                <div class="form-row">
                    <div class="form-group col-4"><label>Order ID</label><input type="number" id="orderOrderId" name="order_id" class="form-control" required></div>
                    <div class="form-group col-4"><label>Provider</label><select id="orderProviderId" name="provider_id" class="form-control"><option value="">-- Select --</option></select></div>
                    <div class="form-group col-4"><label>Status</label>
                        <select id="orderStatus" name="delivery_status" class="form-control">
                            <option value="pending">Pending</option><option value="assigned">Assigned</option>
                            <option value="picked_up">Picked Up</option><option value="on_the_way">On The Way</option>
                            <option value="delivered">Delivered</option><option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6"><label>Pickup Address ID</label><input type="number" id="orderPickup" name="pickup_address_id" class="form-control" required></div>
                    <div class="form-group col-6"><label>Dropoff Address ID</label><input type="number" id="orderDropoff" name="dropoff_address_id" class="form-control" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6"><label>Fee</label><input type="number" id="orderFee" name="delivery_fee" class="form-control" step="0.01"></div>
                    <div class="form-group col-6"><label>Calculated Fee</label><input type="number" id="orderCalcFee" name="calculated_fee" class="form-control" step="0.01"></div>
                </div>
                <div class="form-actions-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button><button type="button" class="btn btn-outline" id="orderCancelBtn">Cancel</button></div>
            </form>
        </div>
    </div>
    <div class="card filter-card"><div class="card-body"><div class="filters-grid">
        <div class="filter-group"><label>Status</label><select id="ordersStatusFilter" class="form-control"><option value="">All</option><option value="pending">Pending</option><option value="delivered">Delivered</option></select></div>
        <div class="filter-group"><label>Provider</label><select id="ordersProviderFilter" class="form-control"><option value="">All</option></select></div>
        <div class="filter-actions"><button id="ordersApplyFilter" class="btn btn-secondary">Apply</button></div>
    </div></div></div>
    <div class="card table-card"><div class="card-header"><h3 class="card-title">Delivery Orders</h3><div class="card-actions"><?php if ($canCreate): ?><button id="ordersAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add</button><?php endif; ?></div></div>
    <div class="card-body"><div id="ordersTableLoading" class="loading-state"><div class="spinner"></div></div><div id="ordersTableContainer" style="display:none"><div class="table-responsive"><table class="data-table"><thead><tr><th>ID</th><th>Order</th><th>Provider</th><th>Status</th><th>Fee</th><th>Created</th><th>Actions</th></tr></thead><tbody id="ordersTableBody"></tbody></table></div><div class="pagination-wrapper"><span id="ordersPaginationInfo"></span><div id="ordersPagination" class="pagination"></div></div></div><div id="ordersEmptyState" class="empty-state" style="display:none"><h3>No Orders</h3></div></div></div>
</div>

<!-- ══════════════════════════════════════════
     TAB: LOCATIONS (Driver Locations)
══════════════════════════════════════════ -->
<div id="locationsTab" class="ws-panel" style="display:none">
    <div id="locationFormContainer" class="card form-card" style="display:none">
         <div class="card-header"><h3 class="card-title">Update Location</h3><button class="btn btn-sm btn-outline" id="locationCloseForm"><i class="fas fa-times"></i></button></div>
         <div class="card-body">
             <form id="locationForm" novalidate>
                <input type="hidden" id="locationId" name="id">
                <div class="form-row">
                    <div class="form-group col-6"><label>Provider</label><select id="locationProviderId" name="provider_id" class="form-control" required></select></div>
                    <div class="form-group col-3"><label>Lat</label><input type="text" id="locationLat" name="latitude" class="form-control" required></div>
                    <div class="form-group col-3"><label>Lng</label><input type="text" id="locationLng" name="longitude" class="form-control" required></div>
                </div>
                <div class="form-actions-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
             </form>
         </div>
    </div>
    <div class="card filter-card"><div class="card-body"><div class="filters-grid">
        <div class="filter-group"><label>Provider</label><select id="locationsProviderFilter" class="form-control"><option value="">All</option></select></div>
        <div class="filter-actions"><button id="locationsApplyFilter" class="btn btn-secondary">Apply</button></div>
    </div></div></div>
    <div class="card table-card"><div class="card-header"><h3 class="card-title">Driver Locations</h3><div class="card-actions"><?php if ($canCreate): ?><button id="locationsAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add</button><?php endif; ?></div></div>
    <div class="card-body"><div id="locationsTableLoading" class="loading-state"><div class="spinner"></div></div><div id="locationsTableContainer" style="display:none"><div class="table-responsive"><table class="data-table"><thead><tr><th>ID</th><th>Provider</th><th>Lat</th><th>Lng</th><th>Updated</th><th>Actions</th></tr></thead><tbody id="locationsTableBody"></tbody></table></div><div class="pagination-wrapper"><span id="locationsPaginationInfo"></span><div id="locationsPagination" class="pagination"></div></div></div><div id="locationsEmptyState" class="empty-state" style="display:none"><h3>No Locations</h3></div></div></div>
</div>

<!-- ══════════════════════════════════════════
     TAB: TRACKING
══════════════════════════════════════════ -->
<div id="trackingTab" class="ws-panel" style="display:none">
    <div id="trackingFormContainer" class="card form-card" style="display:none">
         <div class="card-header"><h3 class="card-title">Tracking Log</h3><button class="btn btn-sm btn-outline" id="trackingCloseForm"><i class="fas fa-times"></i></button></div>
         <div class="card-body">
             <form id="trackingForm" novalidate>
                <input type="hidden" id="trackingId" name="id">
                <div class="form-row">
                    <div class="form-group col-6"><label>Order ID</label><select id="trackingOrderId" name="delivery_order_id" class="form-control" required></select></div>
                    <div class="form-group col-6"><label>Provider</label><select id="trackingProviderId" name="provider_id" class="form-control"></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group col-6"><label>Lat</label><input type="text" id="trackingLat" name="latitude" class="form-control" required></div>
                    <div class="form-group col-6"><label>Lng</label><input type="text" id="trackingLng" name="longitude" class="form-control" required></div>
                </div>
                <div class="form-group"><label>Note</label><input type="text" id="trackingNote" name="status_note" class="form-control"></div>
                <div class="form-actions-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
             </form>
         </div>
    </div>
    <div class="card filter-card"><div class="card-body"><div class="filters-grid">
        <div class="filter-group"><label>Order</label><select id="trackingOrderFilter" class="form-control"><option value="">All</option></select></div>
        <div class="filter-actions"><button id="trackingApplyFilter" class="btn btn-secondary">Apply</button></div>
    </div></div></div>
    <div class="card table-card"><div class="card-header"><h3 class="card-title">Order Tracking</h3><div class="card-actions"><?php if ($canCreate): ?><button id="trackingAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add</button><?php endif; ?></div></div>
    <div class="card-body"><div id="trackingTableLoading" class="loading-state"><div class="spinner"></div></div><div id="trackingTableContainer" style="display:none"><div class="table-responsive"><table class="data-table"><thead><tr><th>ID</th><th>Order</th><th>Lat</th><th>Lng</th><th>Note</th><th>Time</th><th>Actions</th></tr></thead><tbody id="trackingTableBody"></tbody></table></div><div class="pagination-wrapper"><span id="trackingPaginationInfo"></span><div id="trackingPagination" class="pagination"></div></div></div><div id="trackingEmptyState" class="empty-state" style="display:none"><h3>No Logs</h3></div></div></div>
</div>

<!-- ══════════════════════════════════════════
     TAB: PROVIDER ZONES
══════════════════════════════════════════ -->
<div id="provider_zonesTab" class="ws-panel" style="display:none">
    <div id="pzoneFormContainer" class="card form-card" style="display:none">
         <div class="card-header"><h3 class="card-title">Assign Zone</h3><button class="btn btn-sm btn-outline" id="pzoneCloseForm"><i class="fas fa-times"></i></button></div>
         <div class="card-body">
             <form id="pzoneForm" novalidate>
                <div class="form-row">
                    <div class="form-group col-6"><label>Provider</label><select id="pzoneProviderId" name="provider_id" class="form-control" required></select></div>
                    <div class="form-group col-6"><label>Zone</label><select id="pzoneZoneId" name="zone_id" class="form-control" required></select></div>
                </div>
                <div class="form-group"><label class="checkbox-label"><input type="checkbox" id="pzoneActive" name="is_active" value="1" checked> Active</label></div>
                <div class="form-actions-footer"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div>
             </form>
         </div>
    </div>
    <div class="card filter-card"><div class="card-body"><div class="filters-grid">
        <div class="filter-group"><label>Provider</label><select id="pzonesProviderFilter" class="form-control"><option value="">All</option></select></div>
        <div class="filter-actions"><button id="pzonesApplyFilter" class="btn btn-secondary">Apply</button></div>
    </div></div></div>
    <div class="card table-card"><div class="card-header"><h3 class="card-title">Provider Zones</h3><div class="card-actions"><?php if ($canCreate): ?><button id="pzonesAddBtn" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Assign</button><?php endif; ?></div></div>
    <div class="card-body"><div id="pzonesTableLoading" class="loading-state"><div class="spinner"></div></div><div id="pzonesTableContainer" style="display:none"><div class="table-responsive"><table class="data-table"><thead><tr><th>Provider</th><th>Zone</th><th>Active</th><th>Assigned</th><th>Actions</th></tr></thead><tbody id="pzonesTableBody"></tbody></table></div><div class="pagination-wrapper"><span id="pzonesPaginationInfo"></span><div id="pzonesPagination" class="pagination"></div></div></div><div id="pzonesEmptyState" class="empty-state" style="display:none"><h3>No Assignments</h3></div></div></div>
</div>

</div><!-- /page-container -->

<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.TENANT_ID = <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = '<?= addslashes($csrf) ?>';
window.USER_LANGUAGE = '<?= addslashes($lang) ?>';

window.DELIVERY_CONFIG = {
    lang: '<?= addslashes($lang) ?>',
    tenantId: <?= $tenantId ?>,
    csrfToken: '<?= addslashes($csrf) ?>',
    userId: <?= $userId ?>,
    urls: {
        zones: '/api/delivery_zones',
        providers: '/api/delivery_providers',
        orders: '/api/delivery_orders',
        locations: '/api/driver_locations',
        tracking: '/api/delivery_tracking',
        provider_zones: '/api/provider_zones',
        cities: '/api/cities'
    }
};

window.PAGE_PERMISSIONS = <?= json_encode(['canCreate'=>$canCreate, 'canEdit'=>$canEdit, 'canDelete'=>$canDelete], JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php if ($isFragment): ?>
<script src="/admin/assets/js/admin_framework.js?v=<?= time() ?>"></script>
<script src="/admin/assets/js/pages/delivery.js?v=<?= time() ?>"></script>
<script>(function(){ let i=0; const iv=setInterval(()=>{ if(window.Delivery && typeof window.Delivery.init==='function') { clearInterval(iv); window.Delivery.init(); } else if(++i > 80) { clearInterval(iv); console.error('Timeout'); } }, 100); })();</script>
<?php else: ?>
<script src="/admin/assets/js/pages/delivery.js?v=<?= time() ?>"></script>
<?php endif; ?>

<?php if (!$isFragment): require_once __DIR__ . '/../includes/footer.php'; endif; ?>