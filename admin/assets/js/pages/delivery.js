/**
 * /admin/assets/js/pages/delivery.js
 * Delivery Management — Full Workspace Logic
 */
(function () {
    'use strict';

    const AF = window.AdminFramework;
    const CFG = window.DELIVERY_CONFIG || {};

    // ─── State & Config ─────────────────────────────────────────────
    const state = {
        lang: window.USER_LANGUAGE || CFG.lang || 'ar',
        tenant: window.APP_CONFIG?.TENANT_ID || CFG.tenantId || 1,
        csrf: window.APP_CONFIG?.CSRF_TOKEN || CFG.csrfToken || '',
        perms: window.PAGE_PERMISSIONS || {},
        zones: { page: 1, items: [], filters: {}, loaded: false },
        providers: { page: 1, items: [], filters: {}, loaded: false },
        orders: { page: 1, items: [], filters: {}, loaded: false },
        locations: { page: 1, items: [], filters: {}, loaded: false },
        tracking: { page: 1, items: [], filters: {}, loaded: false },
        provider_zones: { page: 1, items: [], filters: {}, loaded: false }
    };
    const LIMIT = 20;

    // ─── Helpers ────────────────────────────────────────────────────
    function $(id) { return document.getElementById(id); }
    function esc(v) { if(v==null) return ''; return String(v).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m]); }
    function notify(msg, type) { if (AF) type === 'error' ? AF.error(msg) : AF.success(msg); else console.log(msg); }
    
    async function api(url, opts = {}) {
        const headers = { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': state.csrf };
        if (opts.json) { headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(opts.json); }
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
        if (info) info.textContent = total ? `${(page-1)*LIMIT+1}-${Math.min(page*LIMIT, total)} of ${total}` : '0';
        if (!container) return;
        let h = '<ul>';
        for(let i=Math.max(1,page-2); i<=Math.min(pages,page+2); i++) h += i===page ? `<li class="active"><span>${i}</span></li>` : `<li><a href="#" data-p="${i}">${i}</a></li>`;
        container.innerHTML = h + '</ul>';
        container.querySelectorAll('a[data-p]').forEach(a => a.onclick = e => { e.preventDefault(); cb(parseInt(a.dataset.p)); });
    }

    // ─── Generic Module Factory ───────────────────────────────────────
    function createModule(name, url, cfg) {
        const s = state[name];
        return {
            async load(page = 1) {
                s.page = page; s.loaded = true;
                $(cfg.loading).style.display = ''; $(cfg.container).style.display = 'none'; $(cfg.empty).style.display = 'none';
                try {
                    const p = new URLSearchParams({ page, limit: LIMIT, tenant_id: state.tenant, lang: state.lang, ...s.filters });
                    const r = await api(`${url}?${p}`);
                    const items = r.data || r.items || []; // Normalize
                    const total = r.meta?.total || r.total || items.length;
                    s.items = items; s.total = total;
                    
                    $(cfg.loading).style.display = 'none';
                    if (!items.length) { $(cfg.empty).style.display = ''; return; }
                    
                    $(cfg.container).style.display = '';
                    $(cfg.tbody).innerHTML = items.map(cfg.row).join('');
                    pagination($(cfg.pagination), $(cfg.info), total, page, n => this.load(n));
                } catch (e) { $(cfg.loading).style.display = 'none'; notify(e.message, 'error'); }
            },
            applyFilters() { s.filters = cfg.getFilters(); this.load(1); },
            resetFilters() { cfg.reset(); s.filters = {}; this.load(1); },
            showForm(item = {}) {
                const fc = $(cfg.formContainer); if(!fc) return;
                fc.style.display = '';
                cfg.setForm(item);
            },
            hideForm() { if($(cfg.formContainer)) $(cfg.formContainer).style.display = 'none'; },
            async save(e) {
                e.preventDefault();
                const body = cfg.getFormData();
                if (!body) return;
                const id = cfg.getId();
                try {
                    const r = await api(id ? `${url}/${id}` : url, { method: id ? 'PUT' : 'POST', json: body });
                    if (r.success === false) throw new Error(r.error || 'Save failed');
                    notify('Saved', 'success');
                    this.hideForm();
                    this.load(s.page);
                } catch (err) { notify(err.message, 'error'); }
            },
            async del(id) {
                if (!confirm('Delete?')) return;
                try {
                    // Special handling for composite keys if needed (ProviderZone)
                    const delUrl = cfg.delUrl ? cfg.delUrl(id) : `${url}/${id}`;
                    await api(delUrl, { method: 'DELETE' });
                    notify('Deleted', 'success');
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
    }

    // ─── Modules Definition ──────────────────────────────────────────

    // 1. Zones
    const zonesMod = Object.assign(createModule('zones', CFG.urls.zones, {
        loading: 'zonesTableLoading', container: 'zonesTableContainer', empty: 'zonesEmptyState', tbody: 'zonesTableBody', pagination: 'zonesPagination', info: 'zonesPaginationInfo',
        formContainer: 'zoneFormContainer', form: 'zoneForm', addBtn: 'zonesAddBtn', closeBtn: 'zoneCloseForm', cancelBtn: 'zoneCancelBtn', applyBtn: 'zonesApplyFilter', resetBtn: 'zonesResetFilter',
        getId: () => $('zoneId')?.value,
        getFilters: () => ({ search: $('zonesSearch')?.value, zone_type: $('zonesTypeFilter')?.value, is_active: $('zonesActiveFilter')?.value }),
        reset: () => { $('zonesSearch').value=''; $('zonesTypeFilter').value=''; $('zonesActiveFilter').value=''; },
        setForm: i => { $('zoneId').value=i.id||''; $('zoneName').value=i.zone_name||''; $('zoneType').value=i.zone_type||'city'; $('zoneFee').value=i.delivery_fee||0; $('zoneActive').checked=!!+i.is_active; },
        getFormData: () => ({ id: $('zoneId').value||null, zone_name: $('zoneName').value, zone_type: $('zoneType').value, city_id: $('zoneCityId').value||null, delivery_fee: $('zoneFee').value, estimated_minutes: $('zoneTime').value, is_active: $('zoneActive').checked?1:0, tenant_id: state.tenant }),
        row: i => `<tr><td>${esc(i.id)}</td><td>${esc(i.zone_name)}</td><td>${esc(i.zone_type)}</td><td>${esc(i.delivery_fee)}</td><td>${esc(i.estimated_minutes)}</td><td>${badge(i.is_active? 'Active':'Inactive', {Active:'success',Inactive:'secondary'})}</td><td class="actions"><button class="btn btn-sm btn-primary" onclick="Delivery.editZone(${i.id})"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger" onclick="Delivery.delZone(${i.id})"><i class="fas fa-trash"></i></button></td></tr>`
    }), {
        async edit(id) { const r = await api(`${CFG.urls.zones}/${id}?tenant_id=${state.tenant}`); this.showForm(r.data || r); }
    });

    // 2. Providers
    const providersMod = Object.assign(createModule('providers', CFG.urls.providers, {
        loading: 'providersTableLoading', container: 'providersTableContainer', empty: 'providersEmptyState', tbody: 'providersTableBody', pagination: 'providersPagination', info: 'providersPaginationInfo',
        formContainer: 'providerFormContainer', form: 'providerForm', addBtn: 'providersAddBtn', closeBtn: 'providerCloseForm', cancelBtn: 'providerCancelBtn', applyBtn: 'providersApplyFilter', resetBtn: null,
        getId: () => $('providerId')?.value,
        getFilters: () => ({ search: $('providersSearch')?.value, provider_type: $('providersTypeFilter')?.value }),
        reset: () => $('providersSearch').value='',
        setForm: i => { $('providerId').value=i.id||''; $('providerType').value=i.provider_type||'company'; $('providerVehicle').value=i.vehicle_type||'bike'; $('providerActive').checked=!!+i.is_active; },
        getFormData: () => ({ id: $('providerId').value||null, provider_type: $('providerType').value, vehicle_type: $('providerVehicle').value, is_active: $('providerActive').checked?1:0, tenant_id: state.tenant }),
        row: i => `<tr><td>${esc(i.id)}</td><td>${esc(i.provider_type)}</td><td>${esc(i.vehicle_type)}</td><td>${badge(i.is_online?'Online':'Offline',{Online:'success',Offline:'secondary'})}</td><td>${esc(i.rating)}</td><td>${esc(i.total_deliveries)}</td><td class="actions"><button class="btn btn-sm btn-primary" onclick="Delivery.editProvider(${i.id})"><i class="fas fa-edit"></i></button> <button class="btn btn-sm btn-danger" onclick="Delivery.delProvider(${i.id})"><i class="fas fa-trash"></i></button></td></tr>`
    }), {
        async edit(id) { const r = await api(`${CFG.urls.providers}/${id}?tenant_id=${state.tenant}`); this.showForm(r.data || r); }
    });

    // 3. Orders
    const ordersMod = Object.assign(createModule('orders', CFG.urls.orders, {
        loading: 'ordersTableLoading', container: 'ordersTableContainer', empty: 'ordersEmptyState', tbody: 'ordersTableBody', pagination: 'ordersPagination', info: 'ordersPaginationInfo',
        formContainer: 'orderFormContainer', form: 'orderForm', addBtn: 'ordersAddBtn', closeBtn: 'orderCloseForm', cancelBtn: 'orderCancelBtn', applyBtn: 'ordersApplyFilter', resetBtn: null,
        getId: () => $('orderId')?.value,
        getFilters: () => ({ delivery_status: $('ordersStatusFilter')?.value, provider_id: $('ordersProviderFilter')?.value }),
        reset: () => { $('ordersStatusFilter').value=''; $('ordersProviderFilter').value=''; },
        setForm: i => { $('orderId').value=i.id||''; $('orderOrderId').value=i.order_id||''; $('orderStatus').value=i.delivery_status||'pending'; $('orderFee').value=i.delivery_fee||0; },
        getFormData: () => ({ id: $('orderId').value||null, order_id: $('orderOrderId').value, delivery_status: $('orderStatus').value, delivery_fee: $('orderFee').value, pickup_address_id:$('orderPickup').value, dropoff_address_id:$('orderDropoff').value, tenant_id: state.tenant }),
        row: i => `<tr><td>${esc(i.id)}</td><td>${esc(i.order_id)}</td><td>${esc(i.provider_id||'-')}</td><td>${badge(i.delivery_status, {pending:'secondary', assigned:'primary', delivered:'success', cancelled:'danger'})}</td><td>${esc(i.delivery_fee)}</td><td>${esc(i.created_at||'')}</td><td class="actions"><button class="btn btn-sm btn-primary" onclick="Delivery.editOrder(${i.id})"><i class="fas fa-edit"></i></button></td></tr>`
    }), {
        async edit(id) { const r = await api(`${CFG.urls.orders}/${id}?tenant_id=${state.tenant}`); this.showForm(r.data || r); }
    });
    
    // 4. Locations (Simple List)
    const locationsMod = Object.assign(createModule('locations', CFG.urls.locations, {
        loading: 'locationsTableLoading', container: 'locationsTableContainer', empty: 'locationsEmptyState', tbody: 'locationsTableBody', pagination: 'locationsPagination', info: 'locationsPaginationInfo',
        formContainer: 'locationFormContainer', form: 'locationForm', addBtn: 'locationsAddBtn', closeBtn: 'locationCloseForm', cancelBtn: null, applyBtn: 'locationsApplyFilter', resetBtn: null,
        getId: () => $('locationId')?.value,
        getFilters: () => ({ provider_id: $('locationsProviderFilter')?.value }),
        reset: () => $('locationsProviderFilter').value='',
        setForm: i => { $('locationId').value=i.id||''; $('locationProviderId').value=i.provider_id||''; $('locationLat').value=i.latitude||''; $('locationLng').value=i.longitude||''; },
        getFormData: () => ({ id: $('locationId').value||null, provider_id: $('locationProviderId').value, latitude: $('locationLat').value, longitude: $('locationLng').value }),
        row: i => `<tr><td>${esc(i.id)}</td><td>${esc(i.provider_id)}</td><td>${esc(i.latitude)}</td><td>${esc(i.longitude)}</td><td>${esc(i.updated_at||'')}</td><td class="actions"><button class="btn btn-sm btn-primary" onclick="Delivery.editLocation(${i.id})"><i class="fas fa-edit"></i></button></td></tr>`
    }), {
        async edit(id) { const r = await api(`${CFG.urls.locations}/${id}?tenant_id=${state.tenant}`); this.showForm(r.data || r); }
    });

    // 5. Tracking
    const trackingMod = Object.assign(createModule('tracking', CFG.urls.tracking, {
        loading: 'trackingTableLoading', container: 'trackingTableContainer', empty: 'trackingEmptyState', tbody: 'trackingTableBody', pagination: 'trackingPagination', info: 'trackingPaginationInfo',
        formContainer: 'trackingFormContainer', form: 'trackingForm', addBtn: 'trackingAddBtn', closeBtn: 'trackingCloseForm', cancelBtn: null, applyBtn: 'trackingApplyFilter', resetBtn: null,
        getId: () => $('trackingId')?.value,
        getFilters: () => ({ delivery_order_id: $('trackingOrderFilter')?.value }),
        reset: () => $('trackingOrderFilter').value='',
        setForm: i => { $('trackingId').value=i.id||''; $('trackingLat').value=i.latitude||''; $('trackingNote').value=i.status_note||''; },
        getFormData: () => ({ id: $('trackingId').value||null, delivery_order_id: $('trackingOrderId').value, latitude: $('trackingLat').value, longitude: $('trackingLng').value, status_note: $('trackingNote').value }),
        row: i => `<tr><td>${esc(i.id)}</td><td>${esc(i.delivery_order_id)}</td><td>${esc(i.latitude)}</td><td>${esc(i.longitude)}</td><td>${esc(i.status_note||'-')}</td><td>${esc(i.created_at||'')}</td><td class="actions"><button class="btn btn-sm btn-danger" onclick="Delivery.delTracking(${i.id})"><i class="fas fa-trash"></i></button></td></tr>`
    }), { });

    // 6. Provider Zones (Composite Key Logic)
    const pzonesMod = Object.assign(createModule('provider_zones', CFG.urls.provider_zones, {
        loading: 'pzonesTableLoading', container: 'pzonesTableContainer', empty: 'pzonesEmptyState', tbody: 'pzonesTableBody', pagination: 'pzonesPagination', info: 'pzonesPaginationInfo',
        formContainer: 'pzoneFormContainer', form: 'pzoneForm', addBtn: 'pzonesAddBtn', closeBtn: 'pzoneCloseForm', cancelBtn: null, applyBtn: 'pzonesApplyFilter', resetBtn: null,
        getId: () => null, // Not used for save
        getFilters: () => ({ provider_id: $('pzonesProviderFilter')?.value }),
        reset: () => $('pzonesProviderFilter').value='',
        setForm: i => { $('pzoneProviderId').value=i.provider_id||''; $('pzoneZoneId').value=i.zone_id||''; $('pzoneActive').checked=!!+i.is_active; },
        getFormData: () => ({ provider_id: $('pzoneProviderId').value, zone_id: $('pzoneZoneId').value, is_active: $('pzoneActive').checked?1:0 }),
        // Custom Save for Composite Key (No ID, just logic)
        async save(e) {
            e.preventDefault();
            const body = this.cfg.getFormData();
            try {
                // In this API, POST creates/replaces
                await api(CFG.urls.provider_zones, { method: 'POST', json: body });
                notify('Saved', 'success');
                this.hideForm();
                this.load(state.provider_zones.page);
            } catch(err) { notify(err.message, 'error'); }
        },
        // Custom Delete for Composite Key
        delUrl: (id) => { 
            // We need provider_id and zone_id to delete. 
            // We stored them in data-pid and data-zid in the row
            const row = document.querySelector(`tr[data-id="${id}"]`);
            const pid = row?.dataset.pid;
            const zid = row?.dataset.zid;
            return `${CFG.urls.provider_zones}?provider_id=${pid}&zone_id=${zid}`;
        },
        row: i => `<tr data-id="${esc(i.provider_id)}-${esc(i.zone_id)}" data-pid="${esc(i.provider_id)}" data-zid="${esc(i.zone_id)}"><td>${esc(i.provider_id)}</td><td>${esc(i.zone_id)}</td><td>${badge(i.is_active?'Active':'Inactive',{Active:'success'})}</td><td>${esc(i.assigned_at||'')}</td><td class="actions"><button class="btn btn-sm btn-danger" onclick="Delivery.delPZone('${i.provider_id}-${i.zone_id}')"><i class="fas fa-trash"></i></button></td></tr>`
    }), { });

    // ─── Dropdown Loader ─────────────────────────────────────────────
    async function loadDrops() {
        // Load Providers
        try {
            const r = await api(`${CFG.urls.providers}?limit=500&tenant_id=${state.tenant}`);
            const items = r.data || r.items || [];
            const html = items.map(p => `<option value="${p.id}">#${p.id} ${esc(p.provider_type)}</option>`).join('');
            ['zoneProviderId', 'orderProviderId', 'ordersProviderFilter', 'locationProviderId', 'locationsProviderFilter', 'pzoneProviderId', 'pzonesProviderFilter'].forEach(id => {
                if($(id)) $(id).innerHTML = '<option value="">-- Select --</option>' + html;
            });
        } catch(e) {}

        // Load Zones
        try {
            const r = await api(`${CFG.urls.zones}?limit=500&tenant_id=${state.tenant}`);
            const items = r.data || r.items || [];
            const html = items.map(z => `<option value="${z.id}">${esc(z.zone_name)}</option>`).join('');
            if($('pzoneZoneId')) $('pzoneZoneId').innerHTML = '<option value="">-- Select --</option>' + html;
        } catch(e) {}
    }

    // ─── Init ───────────────────────────────────────────────────────
    function initTabs() {
        document.querySelectorAll('#workspaceTabs .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#workspaceTabs .tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.querySelectorAll('.ws-panel').forEach(p => p.style.display = 'none');
                
                const tab = btn.dataset.tab;
                const panel = document.getElementById(tab + 'Tab');
                if(panel) panel.style.display = '';

                // Lazy load
                const mod = { zones: zonesMod, providers: providersMod, orders: ordersMod, locations: locationsMod, tracking: trackingMod, provider_zones: pzonesMod }[tab];
                if (mod && !state[tab].loaded) mod.load(1);
            });
        });
    }

    async function init() {
        initTabs();
        zonesMod.bindEvents();
        providersMod.bindEvents();
        ordersMod.bindEvents();
        locationsMod.bindEvents();
        trackingMod.bindEvents();
        pzonesMod.bindEvents();
        
        await loadDrops();
        zonesMod.load(1); // Load first tab
    }

    // Expose Global API
    window.Delivery = {
        init,
        editZone: id => zonesMod.edit(id),
        delZone: id => zonesMod.del(id),
        editProvider: id => providersMod.edit(id),
        delProvider: id => providersMod.del(id),
        editOrder: id => ordersMod.edit(id),
        editLocation: id => locationsMod.edit(id),
        delTracking: id => trackingMod.del(id),
        delPZone: id => pzonesMod.del(id)
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

})();