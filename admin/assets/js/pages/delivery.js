/**
 * /admin/assets/js/pages/delivery.js
 * Delivery Management — Full Workspace Logic
 * Includes Leaflet map integration for Zone management
 */
(function () {
    'use strict';

    const AF  = window.AdminFramework;
    const CFG = window.DELIVERY_CONFIG || {};

    // ─── State ───────────────────────────────────────────────────────
    const state = {
        lang:  window.USER_LANGUAGE || CFG.lang || 'ar',
        tenant: window.APP_CONFIG?.TENANT_ID || CFG.tenantId || 1,
        csrf:  window.APP_CONFIG?.CSRF_TOKEN  || CFG.csrfToken || '',
        perms: window.PAGE_PERMISSIONS || {},
        zones:          { page: 1, items: [], filters: {}, loaded: false, total: 0 },
        providers:      { page: 1, items: [], filters: {}, loaded: false, total: 0 },
        orders:         { page: 1, items: [], filters: {}, loaded: false, total: 0 },
        locations:      { page: 1, items: [], filters: {}, loaded: false, total: 0 },
        tracking:       { page: 1, items: [], filters: {}, loaded: false, total: 0 },
        provider_zones: { page: 1, items: [], filters: {}, loaded: false, total: 0 }
    };
    const LIMIT = 20;

    // ─── Leaflet Map state ───────────────────────────────────────────
    let zonesMap      = null;
    let drawnItems    = null;
    let zoneLayerMap  = new Map(); // zoneId -> layer
    let drawControl   = null;

    // ─── Helpers ─────────────────────────────────────────────────────
    function $(id) { return document.getElementById(id); }
    function esc(v) {
        if (v == null) return '';
        return String(v).replace(/[&<>"']/g, m =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
    }
    function notify(msg, type) {
        if (AF) type === 'error' ? AF.error(msg) : AF.success(msg);
        else console.log('[Delivery]', type, msg);
    }

    async function api(url, opts = {}) {
        const headers = { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': state.csrf };
        if (opts.json) { headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(opts.json); delete opts.json; }
        const res = await fetch(url, { ...opts, headers, credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    function badge(text, map = {}) {
        const cls = map[text] || 'secondary';
        return `<span class="badge badge-${cls}">${esc(text)}</span>`;
    }

    function pagination(container, info, total, page, cb) {
        const pages = Math.ceil(total / LIMIT) || 1;
        if (info) info.textContent = total ? `${(page - 1) * LIMIT + 1}–${Math.min(page * LIMIT, total)} / ${total}` : '0';
        if (!container) return;
        let h = '<ul>';
        if (page > 1)  h += `<li><a href="#" data-p="${page - 1}">&laquo;</a></li>`;
        for (let i = Math.max(1, page - 2); i <= Math.min(pages, page + 2); i++) {
            h += i === page ? `<li class="active"><span>${i}</span></li>` : `<li><a href="#" data-p="${i}">${i}</a></li>`;
        }
        if (page < pages) h += `<li><a href="#" data-p="${page + 1}">&raquo;</a></li>`;
        container.innerHTML = h + '</ul>';
        container.querySelectorAll('a[data-p]').forEach(a => a.onclick = e => { e.preventDefault(); cb(parseInt(a.dataset.p)); });
    }

    // ─── Generic Module Factory ───────────────────────────────────────
    function createModule(name, url, cfg) {
        const s = state[name];
        const mod = {
            cfg,
            async load(page = 1) {
                s.page = page; s.loaded = true;
                if (cfg.loading)   { const el = $(cfg.loading);   if (el) el.style.display = ''; }
                if (cfg.container) { const el = $(cfg.container);  if (el) el.style.display = 'none'; }
                if (cfg.empty)     { const el = $(cfg.empty);      if (el) el.style.display = 'none'; }
                try {
                    const p = new URLSearchParams({ page, limit: LIMIT, tenant_id: state.tenant, lang: state.lang, ...s.filters });
                    const r = await api(`${url}?${p}`);
                    const items = r.data || r.items || [];
                    const total = r.meta?.total ?? r.total ?? items.length;
                    s.items = items; s.total = total;

                    if (cfg.loading)   { const el = $(cfg.loading);  if (el) el.style.display = 'none'; }
                    if (!items.length) {
                        if (cfg.empty) { const el = $(cfg.empty); if (el) el.style.display = ''; }
                        return;
                    }
                    if (cfg.container) { const el = $(cfg.container); if (el) el.style.display = ''; }
                    if (cfg.tbody)     { const el = $(cfg.tbody);     if (el) el.innerHTML = items.map(cfg.row).join(''); }
                    pagination($(cfg.pagination), $(cfg.info), total, page, n => this.load(n));

                    if (cfg.afterLoad) cfg.afterLoad(items);
                } catch (e) {
                    if (cfg.loading) { const el = $(cfg.loading); if (el) el.style.display = 'none'; }
                    notify(e.message, 'error');
                }
            },
            applyFilters() { s.filters = cfg.getFilters(); this.load(1); },
            resetFilters()  { if (cfg.reset) cfg.reset(); s.filters = {}; this.load(1); },
            showForm(item = {}) {
                const fc = $(cfg.formContainer);
                if (!fc) return;
                fc.style.display = '';
                if (cfg.setForm) cfg.setForm(item);
            },
            hideForm() { const fc = $(cfg.formContainer); if (fc) fc.style.display = 'none'; },
            async save(e) {
                e.preventDefault();
                const body = cfg.getFormData ? cfg.getFormData() : null;
                if (!body) return;
                const id = cfg.getId ? cfg.getId() : null;
                try {
                    const r = await api(id ? `${url}/${id}` : url, { method: id ? 'PUT' : 'POST', json: body });
                    if (r.success === false) throw new Error(r.error || r.message || 'Save failed');
                    notify('Saved successfully', 'success');
                    this.hideForm();
                    this.load(s.page);
                } catch (err) { notify(err.message, 'error'); }
            },
            async del(id) {
                if (!confirm('Delete this item?')) return;
                try {
                    const delUrl = cfg.delUrl ? cfg.delUrl(id) : `${url}/${id}`;
                    const r = await api(delUrl, { method: 'DELETE' });
                    if (r.success === false) throw new Error(r.error || 'Delete failed');
                    notify('Deleted successfully', 'success');
                    this.load(s.page);
                } catch (e) { notify(e.message, 'error'); }
            },
            bindEvents() {
                $(cfg.addBtn)?.addEventListener('click', () => this.showForm());
                $(cfg.closeBtn)?.addEventListener('click', () => this.hideForm());
                $(cfg.cancelBtn)?.addEventListener('click', () => this.hideForm());
                $(cfg.form)?.addEventListener('submit', e => this.save(e));
                $(cfg.applyBtn)?.addEventListener('click', () => this.applyFilters());
                $(cfg.resetBtn)?.addEventListener('click', () => this.resetFilters());
            }
        };
        return mod;
    }

    // ─── Leaflet Map for Zones ────────────────────────────────────────
    function initZonesMap() {
        const mapEl = $('zonesMap');
        if (!mapEl || typeof L === 'undefined') return;

        zonesMap = L.map('zonesMap').setView(CFG.mapCenter || [24.7136, 46.6753], CFG.mapZoom || 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(zonesMap);

        drawnItems = L.featureGroup().addTo(zonesMap);

        if (typeof L.Control !== 'undefined' && L.Control.Draw) {
            drawControl = new L.Control.Draw({
                position: 'topright',
                draw: {
                    polygon:  { allowIntersection: false, showArea: true, shapeOptions: { color: '#2563eb', fillOpacity: 0.15 } },
                    rectangle: { shapeOptions: { color: '#2563eb', fillOpacity: 0.15 } },
                    circle:   { shapeOptions: { color: '#2563eb', fillOpacity: 0.15 } },
                    marker:   false,
                    polyline: false,
                    circlemarker: false
                },
                edit: { featureGroup: drawnItems, remove: true }
            });
            drawControl.addTo(zonesMap);
        }

        // When shape drawn → populate zone_value textarea + auto-fill center/radius
        zonesMap.on(L.Draw.Event.CREATED, function (e) {
            drawnItems.clearLayers();
            drawnItems.addLayer(e.layer);
            populateZoneGeoFromLayer(e.layer);
        });

        zonesMap.on(L.Draw.Event.EDITED, function (e) {
            e.layers.eachLayer(layer => populateZoneGeoFromLayer(layer));
        });

        zonesMap.on(L.Draw.Event.DELETED, function () {
            if (drawnItems.getLayers().length === 0) {
                const gf = $('zoneGeoJson');
                if (gf) gf.value = '';
            }
        });

        // Manual edit of zone_value → re-draw
        $('zoneGeoJson')?.addEventListener('input', function () {
            const txt = this.value.trim();
            drawnItems.clearLayers();
            if (!txt) return;
            try { drawGeoOnMap(JSON.parse(txt)); } catch (e) { /* silent */ }
        });
    }

    function populateZoneGeoFromLayer(layer) {
        const gf    = $('zoneGeoJson');
        const latEl = $('zoneLat');
        const lngEl = $('zoneLng');
        const radEl = $('zoneRadius');
        let geo  = null;
        let type = null;

        if (layer instanceof L.Circle) {
            const c = layer.getLatLng();
            const r = layer.getRadius();
            geo  = { type: 'Circle', center: [c.lat, c.lng], radius: Math.round(r) };
            type = 'radius';
            if (latEl) latEl.value = c.lat.toFixed(7);
            if (lngEl) lngEl.value = c.lng.toFixed(7);
            if (radEl) radEl.value = (r / 1000).toFixed(2);
        } else if (layer instanceof L.Rectangle) {
            const b = layer.getBounds();
            geo  = { type: 'Rectangle', bounds: [[b.getSouth(), b.getWest()], [b.getNorth(), b.getEast()]] };
            type = 'polygon';
        } else if (layer instanceof L.Polygon) {
            const latlngs = layer.getLatLngs()[0].map(ll => [ll.lng, ll.lat]);
            latlngs.push(latlngs[0]); // close ring
            geo  = { type: 'Polygon', coordinates: [latlngs] };
            type = 'polygon';
        }

        if (gf && geo) gf.value = JSON.stringify(geo, null, 2);
        if (type) { const zt = $('zoneType'); if (zt) zt.value = type; }
        toggleRadiusFields();
    }

    function drawGeoOnMap(parsed) {
        if (!parsed || !parsed.type || !zonesMap) return;
        const t = (parsed.type || '').toLowerCase();
        let layer = null;

        if (t === 'polygon') {
            const coords = parsed.coordinates?.[0] || [];
            const latlngs = coords.map(pt => [pt[1], pt[0]]);
            layer = L.polygon(latlngs, { color: '#2563eb', fillOpacity: 0.15 });
        } else if (t === 'rectangle') {
            const b = parsed.bounds;
            if (b && b.length >= 2)
                layer = L.rectangle([[b[0][0], b[0][1]], [b[1][0], b[1][1]]], { color: '#2563eb', fillOpacity: 0.15 });
        } else if (t === 'circle' || t === 'radius') {
            if (parsed.center && parsed.radius)
                layer = L.circle([parsed.center[0], parsed.center[1]], { radius: parsed.radius, color: '#2563eb', fillOpacity: 0.15 });
        }

        if (layer) {
            drawnItems.addLayer(layer);
            try { zonesMap.fitBounds(layer.getBounds(), { padding: [20, 20] }); } catch (e) { /* silent */ }
        }
    }

    function renderZonesOnMap(zones) {
        if (!zonesMap || !drawnItems) return;
        // Keep drawn (new) layers but remove old zone markers
        zoneLayerMap.forEach(l => drawnItems.removeLayer(l));
        zoneLayerMap.clear();

        zones.forEach(z => {
            if (!z.zone_value) return;
            let parsed;
            try { parsed = JSON.parse(z.zone_value); } catch (e) { return; }

            let layer = null;
            const t = (parsed.type || '').toLowerCase();

            if (t === 'polygon') {
                const latlngs = (parsed.coordinates?.[0] || []).map(pt => [pt[1], pt[0]]);
                layer = L.polygon(latlngs, { color: '#10b981', fillOpacity: 0.1 });
            } else if (t === 'rectangle') {
                const b = parsed.bounds;
                if (b) layer = L.rectangle([[b[0][0], b[0][1]], [b[1][0], b[1][1]]], { color: '#10b981', fillOpacity: 0.1 });
            } else if (t === 'circle' || t === 'radius') {
                if (parsed.center && parsed.radius)
                    layer = L.circle([parsed.center[0], parsed.center[1]], { radius: parsed.radius, color: '#10b981', fillOpacity: 0.1 });
            }

            if (layer) {
                drawnItems.addLayer(layer);
                layer.bindPopup(`<strong>${esc(z.zone_name)}</strong><br>Type: ${esc(z.zone_type)}<br>Fee: ${esc(z.delivery_fee)}`);
                layer.on('click', () => zonesMod.edit(z.id));
                zoneLayerMap.set(String(z.id), layer);
            }
        });

        if (drawnItems.getLayers().length > 0) {
            try { zonesMap.fitBounds(drawnItems.getBounds(), { padding: [30, 30], maxZoom: 14 }); } catch (e) { /* silent */ }
        }
        setTimeout(() => { if (zonesMap) zonesMap.invalidateSize(); }, 200);
    }

    function toggleRadiusFields() {
        const zt  = $('zoneType');
        const rf  = $('radiusFields');
        if (!zt || !rf) return;
        rf.style.display = zt.value === 'radius' ? '' : 'none';
    }

    // ─── Zones Module ─────────────────────────────────────────────────
    const zonesMod = Object.assign(
        createModule('zones', CFG.urls.zones, {
            loading: null, container: null, empty: null, // handled by sidebar
            pagination: 'zonesPagination', info: 'zonesPaginationInfo',
            formContainer: 'zoneFormContainer',
            form:      'zoneForm',
            addBtn:    'zonesAddBtn',
            closeBtn:  'zoneCloseForm',
            cancelBtn: 'zoneCancelBtn',
            applyBtn:  'zonesApplyFilter',
            resetBtn:  'zonesResetFilter',
            getId:  () => $('zoneId')?.value || null,
            getFilters: () => ({
                search:    $('zonesSearch')?.value     || '',
                zone_type: $('zonesTypeFilter')?.value || '',
                is_active: $('zonesActiveFilter')?.value ?? ''
            }),
            reset: () => {
                if ($('zonesSearch'))      $('zonesSearch').value      = '';
                if ($('zonesTypeFilter'))  $('zonesTypeFilter').value  = '';
                if ($('zonesActiveFilter')) $('zonesActiveFilter').value = '';
            },
            setForm: z => {
                if ($('zoneId'))          $('zoneId').value          = z.id       || '';
                if ($('zoneName'))        $('zoneName').value        = z.zone_name || '';
                if ($('zoneType'))        $('zoneType').value        = z.zone_type || 'city';
                if ($('zoneFee'))         $('zoneFee').value         = z.delivery_fee || '0.00';
                if ($('zoneTime'))        $('zoneTime').value        = z.estimated_minutes ?? 45;
                if ($('zoneActive'))      $('zoneActive').checked    = !!+z.is_active;
                if ($('zoneLat'))         $('zoneLat').value         = z.center_lat || '';
                if ($('zoneLng'))         $('zoneLng').value         = z.center_lng || '';
                if ($('zoneRadius'))      $('zoneRadius').value      = z.radius_km  || '';
                if ($('zoneMinOrder'))    $('zoneMinOrder').value    = z.min_order_value || '';
                if ($('zoneFreeDelivery')) $('zoneFreeDelivery').value = z.free_delivery_over || '';
                if ($('zoneGeoJson'))     $('zoneGeoJson').value     = z.zone_value || '';
                toggleRadiusFields();

                // Redraw geometry on map
                if (drawnItems) { drawnItems.clearLayers(); }
                if (z.zone_value) { try { drawGeoOnMap(JSON.parse(z.zone_value)); } catch (e) { /* silent */ } }
            },
            getFormData: () => ({
                id:                $('zoneId')?.value        || null,
                zone_name:         $('zoneName')?.value      || '',
                zone_type:         $('zoneType')?.value      || 'city',
                city_id:           $('zoneCityId')?.value    || null,
                provider_id:       $('zoneProviderId')?.value || null,
                delivery_fee:      $('zoneFee')?.value       || '0.00',
                estimated_minutes: $('zoneTime')?.value      || 45,
                center_lat:        $('zoneLat')?.value       || null,
                center_lng:        $('zoneLng')?.value       || null,
                radius_km:         $('zoneRadius')?.value    || null,
                min_order_value:   $('zoneMinOrder')?.value  || null,
                free_delivery_over: $('zoneFreeDelivery')?.value || null,
                zone_value:        $('zoneGeoJson')?.value   || null,
                is_active:         $('zoneActive')?.checked  ? 1 : 0,
                tenant_id:         state.tenant
            }),
            afterLoad: items => renderZonesListSidebar(items),
            row: () => '' // we use sidebar, not table
        }),
        {
            async edit(id) {
                try {
                    const r = await api(`${CFG.urls.zones}/${id}?tenant_id=${state.tenant}`);
                    this.showForm(r.data || r);
                } catch (e) { notify(e.message, 'error'); }
            }
        }
    );

    // Override load to use sidebar elements
    const _zonesBaseLoad = zonesMod.load.bind(zonesMod);
    zonesMod.load = async function (page = 1) {
        const s = state.zones;
        s.page = page; s.loaded = true;
        const loadingEl = $('zonesListLoading');
        const itemsEl   = $('zonesListItems');
        const emptyEl   = $('zonesListEmpty');
        if (loadingEl) loadingEl.style.display = '';
        if (itemsEl)   itemsEl.innerHTML = '';
        if (emptyEl)   emptyEl.style.display = 'none';
        try {
            const p = new URLSearchParams({ page, limit: LIMIT, tenant_id: state.tenant, lang: state.lang, ...s.filters });
            const r = await api(`${CFG.urls.zones}?${p}`);
            const items = r.data || r.items || [];
            const total = r.meta?.total ?? r.total ?? items.length;
            s.items = items; s.total = total;

            if (loadingEl) loadingEl.style.display = 'none';
            if (!items.length) { if (emptyEl) emptyEl.style.display = ''; return; }

            renderZonesListSidebar(items);
            renderZonesOnMap(items);
            pagination($('zonesPagination'), $('zonesPaginationInfo'), total, page, n => this.load(n));
        } catch (e) {
            if (loadingEl) loadingEl.style.display = 'none';
            notify(e.message, 'error');
        }
    };

    function renderZonesListSidebar(items) {
        const el = $('zonesListItems');
        if (!el) return;
        el.innerHTML = items.map(z => `
            <div class="zone-list-item${+z.is_active ? '' : ' inactive'}" data-id="${esc(z.id)}">
                <div class="zone-item-info">
                    <strong>${esc(z.zone_name)}</strong>
                    <span class="zone-item-meta">${esc(z.zone_type)} · ${esc(z.delivery_fee)} · ${esc(z.estimated_minutes)} min</span>
                </div>
                <div class="zone-item-actions">
                    ${CFG.canEdit ? `<button class="btn btn-sm btn-icon" onclick="Delivery.editZone(${z.id})" title="Edit"><i class="fas fa-edit"></i></button>` : ''}
                    ${CFG.canDelete ? `<button class="btn btn-sm btn-icon btn-danger" onclick="Delivery.delZone(${z.id})" title="Delete"><i class="fas fa-trash"></i></button>` : ''}
                </div>
            </div>`).join('');

        // Highlight zone on map when clicking list item
        el.querySelectorAll('.zone-list-item').forEach(item => {
            item.addEventListener('click', e => {
                if (e.target.closest('button')) return;
                const id = item.dataset.id;
                const layer = zoneLayerMap.get(String(id));
                if (layer && zonesMap) {
                    try { zonesMap.fitBounds(layer.getBounds(), { padding: [30, 30], maxZoom: 14 }); } catch (e) { /* silent */ }
                    layer.openPopup();
                }
            });
        });
    }

    // ─── Providers Module ─────────────────────────────────────────────
    const providersMod = Object.assign(
        createModule('providers', CFG.urls.providers, {
            loading: 'providersTableLoading', container: 'providersTableContainer', empty: 'providersEmptyState',
            tbody: 'providersTableBody', pagination: 'providersPagination', info: 'providersPaginationInfo',
            formContainer: 'providerFormContainer', form: 'providerForm',
            addBtn: 'providersAddBtn', closeBtn: 'providerCloseForm', cancelBtn: 'providerCancelBtn',
            applyBtn: 'providersApplyFilter', resetBtn: 'providersResetFilter',
            getId: () => $('providerId')?.value || null,
            getFilters: () => ({
                search:        $('providersSearch')?.value        || '',
                provider_type: $('providersTypeFilter')?.value    || '',
                vehicle_type:  $('providersVehicleFilter')?.value || '',
                is_active:     $('providersActiveFilter')?.value  ?? ''
            }),
            reset: () => {
                ['providersSearch','providersTypeFilter','providersVehicleFilter','providersActiveFilter']
                    .forEach(id => { const el = $(id); if (el) el.value = ''; });
            },
            setForm: p => {
                if ($('providerId'))         $('providerId').value         = p.id            || '';
                if ($('providerType'))       $('providerType').value       = p.provider_type || 'company';
                if ($('providerVehicle'))    $('providerVehicle').value    = p.vehicle_type  || 'bike';
                if ($('providerLicense'))    $('providerLicense').value    = p.license_number || '';
                if ($('providerOnline'))     $('providerOnline').checked   = !!+p.is_online;
                if ($('providerActive'))     $('providerActive').checked   = !!+p.is_active;
                if ($('providerEntityId'))   $('providerEntityId').value   = p.entity_id     || '';
                if ($('providerTenantUserId')) $('providerTenantUserId').value = p.tenant_user_id || '';
            },
            getFormData: () => ({
                id:             $('providerId')?.value          || null,
                provider_type:  $('providerType')?.value        || 'company',
                vehicle_type:   $('providerVehicle')?.value     || 'bike',
                license_number: $('providerLicense')?.value     || null,
                is_online:      $('providerOnline')?.checked    ? 1 : 0,
                is_active:      $('providerActive')?.checked    ? 1 : 0,
                entity_id:      $('providerEntityId')?.value    || null,
                tenant_user_id: $('providerTenantUserId')?.value || null,
                tenant_id:      state.tenant
            }),
            row: p => `<tr>
                <td>${esc(p.id)}</td>
                <td>${esc(p.provider_type)}</td>
                <td>${esc(p.vehicle_type)}</td>
                <td>${badge(p.is_online ? 'Online' : 'Offline', { Online: 'success', Offline: 'secondary' })}</td>
                <td>${esc(p.rating ?? '0.00')}</td>
                <td>${esc(p.total_deliveries ?? 0)}</td>
                <td class="actions">
                    ${CFG.canEdit   ? `<button class="btn btn-sm btn-primary"  onclick="Delivery.editProvider(${p.id})"><i class="fas fa-edit"></i></button>` : ''}
                    ${CFG.canDelete ? `<button class="btn btn-sm btn-danger"   onclick="Delivery.delProvider(${p.id})"><i class="fas fa-trash"></i></button>` : ''}
                </td></tr>`
        }),
        { async edit(id) { const r = await api(`${CFG.urls.providers}/${id}?tenant_id=${state.tenant}`); this.showForm(r.data || r); } }
    );

    // ─── Orders Module ────────────────────────────────────────────────
    const ordersMod = Object.assign(
        createModule('orders', CFG.urls.orders, {
            loading: 'ordersTableLoading', container: 'ordersTableContainer', empty: 'ordersEmptyState',
            tbody: 'ordersTableBody', pagination: 'ordersPagination', info: 'ordersPaginationInfo',
            formContainer: 'orderFormContainer', form: 'orderForm',
            addBtn: 'ordersAddBtn', closeBtn: 'orderCloseForm', cancelBtn: 'orderCancelBtn',
            applyBtn: 'ordersApplyFilter', resetBtn: 'ordersResetFilter',
            getId: () => $('orderId')?.value || null,
            getFilters: () => ({
                delivery_status: $('ordersStatusFilter')?.value   || '',
                provider_id:     $('ordersProviderFilter')?.value || '',
                delivery_zone_id: $('ordersZoneFilter')?.value    || ''
            }),
            reset: () => {
                ['ordersStatusFilter','ordersProviderFilter','ordersZoneFilter']
                    .forEach(id => { const el = $(id); if (el) el.value = ''; });
            },
            setForm: o => {
                if ($('orderId'))          $('orderId').value          = o.id              || '';
                if ($('orderOrderId'))     $('orderOrderId').value     = o.order_id        || '';
                if ($('orderProviderId'))  $('orderProviderId').value  = o.provider_id     || '';
                if ($('orderStatus'))      $('orderStatus').value      = o.delivery_status || 'pending';
                if ($('orderPickup'))      $('orderPickup').value      = o.pickup_address_id  || '';
                if ($('orderDropoff'))     $('orderDropoff').value     = o.dropoff_address_id || '';
                if ($('orderFee'))         $('orderFee').value         = o.delivery_fee    || '0.00';
                if ($('orderCalcFee'))     $('orderCalcFee').value     = o.calculated_fee  || '0.00';
                if ($('orderPayout'))      $('orderPayout').value      = o.provider_payout || '0.00';
                if ($('orderZoneId'))      $('orderZoneId').value      = o.delivery_zone_id || '';
                if ($('orderCancelledBy')) $('orderCancelledBy').value = o.cancelled_by   || '';
                if ($('orderCancelReason')) $('orderCancelReason').value = o.cancellation_reason || '';
                // Show cancel fields if status is cancelled
                const cf = $('cancelFields');
                if (cf) cf.style.display = (o.delivery_status === 'cancelled') ? '' : 'none';
            },
            getFormData: () => ({
                id:                $('orderId')?.value          || null,
                order_id:          $('orderOrderId')?.value     || '',
                provider_id:       $('orderProviderId')?.value  || null,
                delivery_status:   $('orderStatus')?.value      || 'pending',
                pickup_address_id: $('orderPickup')?.value      || '',
                dropoff_address_id: $('orderDropoff')?.value    || '',
                delivery_fee:      $('orderFee')?.value         || '0.00',
                calculated_fee:    $('orderCalcFee')?.value     || '0.00',
                provider_payout:   $('orderPayout')?.value      || '0.00',
                delivery_zone_id:  $('orderZoneId')?.value      || null,
                cancelled_by:      $('orderCancelledBy')?.value  || null,
                cancellation_reason: $('orderCancelReason')?.value || null,
                tenant_id:         state.tenant
            }),
            row: o => `<tr>
                <td>${esc(o.id)}</td>
                <td>${esc(o.order_id)}</td>
                <td>${esc(o.provider_id || '–')}</td>
                <td>${badge(o.delivery_status, { pending: 'secondary', assigned: 'primary', accepted: 'primary', picked_up: 'warning', on_the_way: 'warning', delivered: 'success', cancelled: 'danger' })}</td>
                <td>${esc(o.delivery_fee)}</td>
                <td>${esc(o.delivery_zone_id || '–')}</td>
                <td>${esc(o.created_at || '')}</td>
                <td class="actions">
                    ${CFG.canEdit   ? `<button class="btn btn-sm btn-primary" onclick="Delivery.editOrder(${o.id})"><i class="fas fa-edit"></i></button>` : ''}
                </td></tr>`
        }),
        { async edit(id) { const r = await api(`${CFG.urls.orders}/${id}?tenant_id=${state.tenant}`); this.showForm(r.data || r); } }
    );

    // Toggle cancel fields on status change
    $('orderStatus')?.addEventListener('change', function () {
        const cf = $('cancelFields');
        if (cf) cf.style.display = this.value === 'cancelled' ? '' : 'none';
    });

    // ─── Locations Module ─────────────────────────────────────────────
    const locationsMod = Object.assign(
        createModule('locations', CFG.urls.locations, {
            loading: 'locationsTableLoading', container: 'locationsTableContainer', empty: 'locationsEmptyState',
            tbody: 'locationsTableBody', pagination: 'locationsPagination', info: 'locationsPaginationInfo',
            formContainer: 'locationFormContainer', form: 'locationForm',
            addBtn: 'locationsAddBtn', closeBtn: 'locationCloseForm', cancelBtn: 'locationCancelBtn',
            applyBtn: 'locationsApplyFilter', resetBtn: 'locationsResetFilter',
            getId: () => $('locationId')?.value || null,
            getFilters: () => ({ provider_id: $('locationsProviderFilter')?.value || '' }),
            reset: () => { const el = $('locationsProviderFilter'); if (el) el.value = ''; },
            setForm: l => {
                if ($('locationId'))         $('locationId').value         = l.id          || '';
                if ($('locationProviderId')) $('locationProviderId').value = l.provider_id || '';
                if ($('locationLat'))        $('locationLat').value        = l.latitude    || '';
                if ($('locationLng'))        $('locationLng').value        = l.longitude   || '';
            },
            getFormData: () => ({
                id:          $('locationId')?.value          || null,
                provider_id: $('locationProviderId')?.value  || '',
                latitude:    $('locationLat')?.value         || '',
                longitude:   $('locationLng')?.value         || ''
            }),
            row: l => `<tr>
                <td>${esc(l.id)}</td>
                <td>${esc(l.provider_id)}</td>
                <td>${esc(l.latitude)}</td>
                <td>${esc(l.longitude)}</td>
                <td>${esc(l.updated_at || '')}</td>
                <td class="actions">
                    ${CFG.canEdit   ? `<button class="btn btn-sm btn-primary" onclick="Delivery.editLocation(${l.id})"><i class="fas fa-edit"></i></button>` : ''}
                    ${CFG.canDelete ? `<button class="btn btn-sm btn-danger"  onclick="Delivery.delLocation(${l.id})"><i class="fas fa-trash"></i></button>` : ''}
                </td></tr>`
        }),
        { async edit(id) { const r = await api(`${CFG.urls.locations}/${id}?tenant_id=${state.tenant}`); this.showForm(r.data || r); } }
    );

    // ─── Tracking Module ──────────────────────────────────────────────
    const trackingMod = createModule('tracking', CFG.urls.tracking, {
        loading: 'trackingTableLoading', container: 'trackingTableContainer', empty: 'trackingEmptyState',
        tbody: 'trackingTableBody', pagination: 'trackingPagination', info: 'trackingPaginationInfo',
        formContainer: 'trackingFormContainer', form: 'trackingForm',
        addBtn: 'trackingAddBtn', closeBtn: 'trackingCloseForm', cancelBtn: 'trackingCancelBtn',
        applyBtn: 'trackingApplyFilter', resetBtn: 'trackingResetFilter',
        getId: () => $('trackingId')?.value || null,
        getFilters: () => ({
            delivery_order_id: $('trackingOrderFilter')?.value    || '',
            provider_id:       $('trackingProviderFilter')?.value || ''
        }),
        reset: () => {
            ['trackingOrderFilter','trackingProviderFilter'].forEach(id => { const el = $(id); if (el) el.value = ''; });
        },
        setForm: t => {
            if ($('trackingId'))         $('trackingId').value         = t.id                || '';
            if ($('trackingOrderId'))    $('trackingOrderId').value    = t.delivery_order_id || '';
            if ($('trackingProviderId')) $('trackingProviderId').value = t.provider_id       || '';
            if ($('trackingLat'))        $('trackingLat').value        = t.latitude          || '';
            if ($('trackingLng'))        $('trackingLng').value        = t.longitude         || '';
            if ($('trackingNote'))       $('trackingNote').value       = t.status_note       || '';
        },
        getFormData: () => ({
            id:                $('trackingId')?.value          || null,
            delivery_order_id: $('trackingOrderId')?.value     || '',
            provider_id:       $('trackingProviderId')?.value  || null,
            latitude:          $('trackingLat')?.value         || '',
            longitude:         $('trackingLng')?.value         || '',
            status_note:       $('trackingNote')?.value        || null,
            tenant_id:         state.tenant
        }),
        row: t => `<tr>
            <td>${esc(t.id)}</td>
            <td>${esc(t.delivery_order_id)}</td>
            <td>${esc(t.provider_id || '–')}</td>
            <td>${esc(t.latitude)}</td>
            <td>${esc(t.longitude)}</td>
            <td>${esc(t.status_note || '–')}</td>
            <td>${esc(t.created_at || '')}</td>
            <td class="actions">
                ${CFG.canDelete ? `<button class="btn btn-sm btn-danger" onclick="Delivery.delTracking(${t.id})"><i class="fas fa-trash"></i></button>` : ''}
            </td></tr>`
    });

    // ─── Provider Zones Module ────────────────────────────────────────
    const pzonesMod = Object.assign(
        createModule('provider_zones', CFG.urls.provider_zones, {
            loading: 'pzonesTableLoading', container: 'pzonesTableContainer', empty: 'pzonesEmptyState',
            tbody: 'pzonesTableBody', pagination: 'pzonesPagination', info: 'pzonesPaginationInfo',
            formContainer: 'pzoneFormContainer', form: 'pzoneForm',
            addBtn: 'pzonesAddBtn', closeBtn: 'pzoneCloseForm', cancelBtn: 'pzoneCancelBtn',
            applyBtn: 'pzonesApplyFilter', resetBtn: 'pzonesResetFilter',
            getId: () => null,
            getFilters: () => ({
                provider_id: $('pzonesProviderFilter')?.value || '',
                zone_id:     $('pzonesZoneFilter')?.value     || '',
                is_active:   $('pzonesActiveFilter')?.value   ?? ''
            }),
            reset: () => {
                ['pzonesProviderFilter','pzonesZoneFilter','pzonesActiveFilter']
                    .forEach(id => { const el = $(id); if (el) el.value = ''; });
            },
            setForm: pz => {
                if ($('pzoneProviderId')) $('pzoneProviderId').value = pz.provider_id || '';
                if ($('pzoneZoneId'))     $('pzoneZoneId').value     = pz.zone_id    || '';
                if ($('pzoneActive'))     $('pzoneActive').checked   = !!+pz.is_active;
            },
            getFormData: () => ({
                provider_id: $('pzoneProviderId')?.value || '',
                zone_id:     $('pzoneZoneId')?.value     || '',
                is_active:   $('pzoneActive')?.checked   ? 1 : 0
            }),
            delUrl: id => {
                // id format: "providerId_zoneId" (underscore delimiter, IDs are integers)
                const sep = id.indexOf('_');
                const pid = id.substring(0, sep);
                const zid = id.substring(sep + 1);
                return `${CFG.urls.provider_zones}?provider_id=${encodeURIComponent(pid)}&zone_id=${encodeURIComponent(zid)}`;
            },
            row: pz => `<tr data-pzid="${esc(pz.provider_id)}_${esc(pz.zone_id)}" data-pid="${esc(pz.provider_id)}" data-zid="${esc(pz.zone_id)}">
                <td>${esc(pz.provider_id)}</td>
                <td>${esc(pz.zone_id)}</td>
                <td>${badge(pz.is_active ? 'Active' : 'Inactive', { Active: 'success', Inactive: 'secondary' })}</td>
                <td>${esc(pz.assigned_at || '')}</td>
                <td class="actions">
                    ${CFG.canDelete ? `<button class="btn btn-sm btn-danger" onclick="Delivery.delPZone('${esc(pz.provider_id)}_${esc(pz.zone_id)}')"><i class="fas fa-trash"></i></button>` : ''}
                </td></tr>`
        }),
        {
            async save(e) {
                e.preventDefault();
                const body = this.cfg.getFormData();
                try {
                    const r = await api(CFG.urls.provider_zones, { method: 'POST', json: body });
                    if (r.success === false) throw new Error(r.error || r.message || 'Save failed');
                    notify('Saved successfully', 'success');
                    this.hideForm();
                    this.load(state.provider_zones.page);
                } catch (err) { notify(err.message, 'error'); }
            }
        }
    );

    // ─── Dropdown Loaders ─────────────────────────────────────────────
    async function loadDrops() {
        // Providers dropdown (used across multiple modules)
        let providerOpts = '<option value="">–</option>';
        try {
            const r = await api(`${CFG.urls.providers}?limit=500&tenant_id=${state.tenant}`);
            const items = r.data || r.items || [];
            providerOpts = '<option value="">–</option>' + items.map(p => `<option value="${esc(p.id)}">#${esc(p.id)} ${esc(p.provider_type)}</option>`).join('');
        } catch (e) { /* silent */ }

        ['zoneProviderId','orderProviderId','ordersProviderFilter',
         'locationProviderId','locationsProviderFilter',
         'pzoneProviderId','pzonesProviderFilter',
         'trackingProviderId','trackingProviderFilter']
            .forEach(id => { const el = $(id); if (el) el.innerHTML = providerOpts; });

        // Zones dropdown
        let zoneOpts = '<option value="">–</option>';
        try {
            const r = await api(`${CFG.urls.zones}?limit=500&tenant_id=${state.tenant}`);
            const items = r.data || r.items || [];
            zoneOpts = '<option value="">–</option>' + items.map(z => `<option value="${esc(z.id)}">${esc(z.zone_name)}</option>`).join('');
        } catch (e) { /* silent */ }

        ['pzoneZoneId','pzonesZoneFilter','orderZoneId','ordersZoneFilter']
            .forEach(id => { const el = $(id); if (el) el.innerHTML = zoneOpts; });

        // Cities
        try {
            const r = await api(`${CFG.urls.cities}?limit=500`);
            const items = r.data || r.items || [];
            const html = '<option value="">–</option>' + items.map(c => `<option value="${esc(c.id)}">${esc(c.name)}</option>`).join('');
            const el = $('zoneCityId');
            if (el) el.innerHTML = html;
        } catch (e) { /* silent */ }

        // Delivery orders for tracking dropdown
        let orderOpts = '<option value="">–</option>';
        try {
            const r = await api(`${CFG.urls.orders}?limit=500&tenant_id=${state.tenant}`);
            const items = r.data || r.items || [];
            orderOpts = '<option value="">–</option>' + items.map(o => `<option value="${esc(o.id)}">#${esc(o.id)} (order:${esc(o.order_id)})</option>`).join('');
        } catch (e) { /* silent */ }

        ['trackingOrderId','trackingOrderFilter']
            .forEach(id => { const el = $(id); if (el) el.innerHTML = orderOpts; });

        // Entities & Tenant Users for provider form
        try {
            const r = await api(`${CFG.urls.entities}?limit=500&tenant_id=${state.tenant}`);
            const items = r.data || r.items || [];
            const html = '<option value="">–</option>' + items.map(e => `<option value="${esc(e.id)}">${esc(e.store_name)}</option>`).join('');
            const el = $('providerEntityId');
            if (el) el.innerHTML = html;
        } catch (e) { /* silent */ }

        try {
            const r = await api(`${CFG.urls.tenant_users}?limit=500&tenant_id=${state.tenant}`);
            const items = r.data || r.items || [];
            const html = '<option value="">–</option>' + items.map(u => `<option value="${esc(u.id)}">#${esc(u.id)} (user:${esc(u.user_id)})</option>`).join('');
            const el = $('providerTenantUserId');
            if (el) el.innerHTML = html;
        } catch (e) { /* silent */ }
    }

    // ─── Tabs ─────────────────────────────────────────────────────────
    function initTabs() {
        document.querySelectorAll('#workspaceTabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#workspaceTabs .tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.querySelectorAll('.ws-panel').forEach(p => p.style.display = 'none');

                const tab   = btn.dataset.tab;
                const panel = document.getElementById(tab + 'Tab');
                if (panel) panel.style.display = '';

                // Force Leaflet to recalculate on show
                if (tab === 'zones' && zonesMap) {
                    setTimeout(() => zonesMap.invalidateSize(), 100);
                }

                const modMap = {
                    zones: zonesMod, providers: providersMod, orders: ordersMod,
                    locations: locationsMod, tracking: trackingMod, provider_zones: pzonesMod
                };
                const mod = modMap[tab];
                if (mod && !state[tab]?.loaded) mod.load(1);
            });
        });
    }

    // ─── Zone type change ─────────────────────────────────────────────
    function bindZoneTypeChange() {
        $('zoneType')?.addEventListener('change', toggleRadiusFields);
    }

    // ─── Init ─────────────────────────────────────────────────────────
    async function init() {
        initTabs();
        bindZoneTypeChange();

        // Bind all module events
        [zonesMod, providersMod, ordersMod, locationsMod, trackingMod, pzonesMod].forEach(m => m.bindEvents());

        // Init Leaflet map (may be deferred if leaflet loads async)
        function tryInitMap() {
            if (typeof L !== 'undefined') {
                initZonesMap();
            } else {
                setTimeout(tryInitMap, 200);
            }
        }
        tryInitMap();

        await loadDrops();
        await zonesMod.load(1); // Load first tab
    }

    // ─── Public API ───────────────────────────────────────────────────
    window.Delivery = {
        init,
        editZone:     id => zonesMod.edit(id),
        delZone:      id => zonesMod.del(id),
        editProvider: id => providersMod.edit(id),
        delProvider:  id => providersMod.del(id),
        editOrder:    id => ordersMod.edit(id),
        editLocation: id => locationsMod.edit(id),
        delLocation:  id => locationsMod.del(id),
        delTracking:  id => trackingMod.del(id),
        delPZone:     id => pzonesMod.del(id)
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

})();