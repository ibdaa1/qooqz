/**
 * /admin/assets/js/pages/ads.js
 * Ads Management - Campaigns + Ad Units
 */
(function () {
    'use strict';

    /* ──────────────────────────────────────────────
     * Config & state
     * ──────────────────────────────────────────── */
    var CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;
    var PER_PAGE = 25;

    // Campaigns state
    var campaignsPage    = 1;
    var campaignsFilters = {};
    var campaignCache    = [];
    var currencyCache    = [];

    // Ads state
    var adsPage    = 1;
    var adsFilters = {};

    // Active tab
    var activeTab = 'campaigns';

    function reloadConfig() {
        CFG        = window.ADS_CONFIG || {};
        CSRF       = CFG.csrfToken || '';
        STRINGS    = CFG.strings   || {};
        CAN_CREATE = !!CFG.canCreate;
        CAN_EDIT   = !!CFG.canEdit;
        CAN_DELETE = !!CFG.canDelete;
    }
    reloadConfig();

    /* ──────────────────────────────────────────────
     * Translation helper
     * ──────────────────────────────────────────── */
    function t(key, fallback) {
        var keys = key.split('.');
        var val  = STRINGS;
        for (var i = 0; i < keys.length; i++) {
            if (val && typeof val === 'object' && keys[i] in val) {
                val = val[keys[i]];
            } else {
                return fallback || key;
            }
        }
        return (typeof val === 'string') ? val : (fallback || key);
    }

    /* ──────────────────────────────────────────────
     * XSS escape
     * ──────────────────────────────────────────── */
    function esc(str) {
        if (str == null) return '';
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    /* ──────────────────────────────────────────────
     * Modal helpers
     * ──────────────────────────────────────────── */
    function openModal(id)  { var el = document.getElementById(id); if (el) el.style.display = 'flex'; }
    function closeModal(id) { var el = document.getElementById(id); if (el) el.style.display = 'none'; }

    /* ──────────────────────────────────────────────
     * Toast notifications
     * ──────────────────────────────────────────── */
    function showNotification(message, type) {
        type = type || 'info';
        var container = document.getElementById('adsNotifications');
        if (!container) {
            container = document.createElement('div');
            container.id = 'adsNotifications';
            container.className = 'ads-notifications';
            document.body.appendChild(container);
        }
        var toast = document.createElement('div');
        toast.className = 'ads-toast ads-toast-' + type;
        toast.textContent = message;
        var close = document.createElement('span');
        close.className = 'ads-toast-close';
        close.textContent = '\u00d7';
        close.onclick = function () { toast.remove(); };
        toast.appendChild(close);
        container.appendChild(toast);
        setTimeout(function () { if (toast.parentNode) toast.remove(); }, 4000);
    }

    /* ──────────────────────────────────────────────
     * Status badge (shared)
     * ──────────────────────────────────────────── */
    function statusBadge(status) {
        var cls = {
            active:    'badge-active',
            paused:    'badge-paused',
            rejected:  'badge-rejected',
            draft:     'badge-draft',
            completed: 'badge-completed'
        }[status] || 'badge-default';
        var label = t('status.' + status, status);
        return '<span class="badge ' + cls + '">' + esc(label) + '</span>';
    }

    /* ──────────────────────────────────────────────
     * TAB SWITCHING
     * ──────────────────────────────────────────── */
    function switchTab(tabName) {
        activeTab = tabName;

        document.querySelectorAll('.ads-tab-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });
        document.querySelectorAll('.ads-tab-panel').forEach(function (panel) {
            panel.style.display = (panel.id === 'tab' + capitalise(tabName)) ? '' : 'none';
        });

        var btnAddCampaign = document.getElementById('btnAddCampaign');
        var btnAddAd       = document.getElementById('btnAddAd');
        if (btnAddCampaign) btnAddCampaign.style.display = (tabName === 'campaigns' && CAN_CREATE) ? '' : 'none';
        if (btnAddAd)       btnAddAd.style.display       = (tabName === 'ads'       && CAN_CREATE) ? '' : 'none';
    }

    function capitalise(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /* ══════════════════════════════════════════════
     * CAMPAIGNS
     * ══════════════════════════════════════════ */

    /* Load currencies for campaign modal */
    function loadCurrencies(callback) {
        if (currencyCache.length > 0) { callback(currencyCache); return; }
        var url = (CFG.apiBase || '/api') + '/currencies?limit=200&order_by=code&order_dir=ASC';
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                currencyCache = (json.data && json.data.items) ? json.data.items : (Array.isArray(json.data) ? json.data : []);
                callback(currencyCache);
            })
            .catch(function () {
                showNotification(t('error_currencies_load', 'Failed to load currencies'), 'error');
                callback([]);
            });
    }

    function populateCurrencySelect(selectEl, selectedId) {
        loadCurrencies(function (currencies) {
            var html = '<option value="">' + esc(t('form.select_currency', '-- Select Currency --')) + '</option>';
            currencies.forEach(function (c) {
                var label = (c.code || '') + (c.name ? ' - ' + c.name : '');
                var sel   = (selectedId && String(c.id) === String(selectedId)) ? ' selected' : '';
                html += '<option value="' + esc(c.id) + '"' + sel + '>' + esc(label) + '</option>';
            });
            selectEl.innerHTML = html;
        });
    }

    /* Load campaigns (used by campaign tab + ads filter + ad form) */
    function loadCampaignsData(callback, bustCache) {
        if (!bustCache && campaignCache.length > 0) { callback(campaignCache); return; }
        var url = (CFG.apiBase || '/api') + '/ad_campaigns?limit=500&order_by=id&order_dir=ASC';
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                campaignCache = (json.data && json.data.items) ? json.data.items : [];
                callback(campaignCache);
            })
            .catch(function () {
                showNotification(t('error_campaigns_load', 'Failed to load campaigns'), 'error');
                callback([]);
            });
    }

    /* Load campaigns table */
    function loadCampaigns(params) {
        params = params || {};
        var page    = params.page    || campaignsPage;
        var filters = params.filters || campaignsFilters;
        var offset  = (page - 1) * PER_PAGE;

        var url = (CFG.apiBase || '/api') + '/ad_campaigns?limit=' + PER_PAGE + '&offset=' + offset + '&order_by=id&order_dir=DESC';
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        if (filters.status)        url += '&status='        + encodeURIComponent(filters.status);
        if (filters.pricing_model) url += '&pricing_model=' + encodeURIComponent(filters.pricing_model);
        if (filters.search)        url += '&search='        + encodeURIComponent(filters.search);

        var tbody = document.getElementById('campaignsTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center">...</td></tr>';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var items = (json.data && json.data.items) ? json.data.items : [];
                var total = (json.data && json.data.meta) ? (json.data.meta.total || 0) : 0;
                renderCampaignsTable(items);
                renderCampaignsPagination(page, total);
                updateCampaignsPaginationInfo(page, items.length, total);
                // Bust cache so new data populates dropdowns
                campaignCache = items;
            })
            .catch(function () {
                showNotification(t('error_campaigns_load', 'Failed to load campaigns'), 'error');
                if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + esc(t('campaigns_table.no_records', 'No campaigns found')) + '</td></tr>';
            });
    }

    function renderCampaignsTable(items) {
        var tbody = document.getElementById('campaignsTableBody');
        if (!tbody) return;
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + esc(t('campaigns_table.no_records', 'No campaigns found')) + '</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function (c) {
            var pricingLabel = t('pricing_model.' + c.pricing_model, c.pricing_model || '-');
            var budget = (c.budget != null) ? (parseFloat(c.budget).toFixed(2) + (c.currency_code ? ' ' + c.currency_code : '')) : '-';
            html += '<tr>';
            html += '<td>' + esc(c.id) + '</td>';
            html += '<td><strong>' + esc(c.name) + '</strong></td>';
            html += '<td>' + esc(budget) + '</td>';
            html += '<td>' + esc(c.currency_code || '-') + '</td>';
            html += '<td>' + esc(pricingLabel) + '</td>';
            html += '<td>' + esc(c.start_date ? c.start_date.substring(0, 10) : '-') + '</td>';
            html += '<td>' + esc(c.end_date   ? c.end_date.substring(0, 10)   : '-') + '</td>';
            html += '<td>' + statusBadge(c.status) + '</td>';
            html += '<td><div class="row-actions">';
            if (CAN_EDIT) {
                html += '<button class="btn btn-secondary btn-sm btn-edit-campaign" data-id="' + esc(c.id) + '">' + esc(t('table.edit', 'Edit')) + '</button>';
            }
            if (CAN_DELETE) {
                html += '<button class="btn btn-danger btn-sm btn-delete-campaign" data-id="' + esc(c.id) + '">' + esc(t('table.delete', 'Delete')) + '</button>';
            }
            html += '</div></td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;

        tbody.querySelectorAll('.btn-edit-campaign').forEach(function (btn) {
            btn.addEventListener('click', function () { openEditCampaignModal(parseInt(btn.dataset.id, 10)); });
        });
        tbody.querySelectorAll('.btn-delete-campaign').forEach(function (btn) {
            btn.addEventListener('click', function () { confirmDeleteCampaign(parseInt(btn.dataset.id, 10)); });
        });
    }

    function renderCampaignsPagination(page, total) {
        var totalPages = Math.ceil(total / PER_PAGE) || 1;
        var pg = document.getElementById('campaignsPagination');
        if (!pg) return;
        var html = '';
        html += '<button class="page-btn" ' + (page <= 1 ? 'disabled' : '') + ' data-page="' + (page - 1) + '">' + esc(t('pagination.prev', 'Prev')) + '</button>';
        var start = Math.max(1, page - 2);
        var end   = Math.min(totalPages, start + 4);
        if (end - start < 4) start = Math.max(1, end - 4);
        for (var i = start; i <= end; i++) {
            html += '<button class="page-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }
        html += '<button class="page-btn" ' + (page >= totalPages ? 'disabled' : '') + ' data-page="' + (page + 1) + '">' + esc(t('pagination.next', 'Next')) + '</button>';
        pg.innerHTML = html;
        pg.querySelectorAll('.page-btn:not([disabled])').forEach(function (btn) {
            btn.addEventListener('click', function () {
                campaignsPage = parseInt(btn.dataset.page, 10);
                loadCampaigns({ page: campaignsPage, filters: campaignsFilters });
            });
        });
    }

    function updateCampaignsPaginationInfo(page, count, total) {
        var el = document.getElementById('campaignsPaginationInfo');
        if (!el) return;
        var from = total === 0 ? 0 : (page - 1) * PER_PAGE + 1;
        var to   = (page - 1) * PER_PAGE + count;
        el.textContent = from + '-' + to + ' ' + t('pagination.of', 'of') + ' ' + total;
    }

    /* Campaign Modal - Add */
    function openAddCampaignModal() {
        reloadConfig();
        var form = document.getElementById('campaignForm');
        if (form) form.reset();
        var idEl = document.getElementById('campaignId');
        if (idEl) idEl.value = '';
        var titleEl = document.getElementById('campaignModalTitle');
        if (titleEl) titleEl.textContent = t('modal.add_campaign_title', 'Add Campaign');

        var currSel = document.getElementById('campaignCurrencyId');
        if (currSel) populateCurrencySelect(currSel, null);

        openModal('campaignModal');
    }

    /* Campaign Modal - Edit */
    function openEditCampaignModal(id) {
        reloadConfig();
        var url = (CFG.apiBase || '/api') + '/ad_campaigns?id=' + id;
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var c = json.data || json;
                if (!c || !c.id) { showNotification(t('error_campaigns_load', 'Failed to load campaign'), 'error'); return; }

                var idEl = document.getElementById('campaignId');
                if (idEl) idEl.value = c.id;

                var titleEl = document.getElementById('campaignModalTitle');
                if (titleEl) titleEl.textContent = t('modal.edit_campaign_title', 'Edit Campaign');

                var setVal = function (elId, val) { var el = document.getElementById(elId); if (el) el.value = val || ''; };
                setVal('campaignName',         c.name);
                setVal('campaignBudget',       c.budget != null ? c.budget : 0);
                setVal('campaignPricingModel', c.pricing_model);
                setVal('campaignStartDate',    c.start_date ? c.start_date.substring(0, 10) : '');
                setVal('campaignEndDate',      c.end_date   ? c.end_date.substring(0, 10)   : '');
                setVal('campaignStatus',       c.status);

                var currSel = document.getElementById('campaignCurrencyId');
                if (currSel) populateCurrencySelect(currSel, c.currency_id);

                openModal('campaignModal');
            })
            .catch(function () { showNotification(t('error_campaigns_load', 'Failed to load campaign'), 'error'); });
    }

    /* Save Campaign */
    function saveCampaign() {
        var idEl = document.getElementById('campaignId');
        var id   = idEl ? parseInt(idEl.value, 10) : 0;

        var getVal = function (elId) { var el = document.getElementById(elId); return el ? el.value.trim() : ''; };

        var data = {
            name:          getVal('campaignName'),
            budget:        parseFloat(getVal('campaignBudget')) || 0,
            currency_id:   parseInt(getVal('campaignCurrencyId'), 10) || 0,
            pricing_model: getVal('campaignPricingModel'),
            start_date:    getVal('campaignStartDate') || null,
            end_date:      getVal('campaignEndDate')   || null,
            status:        getVal('campaignStatus'),
        };

        if (id > 0) data.id = id;

        var url    = (CFG.apiBase || '/api') + '/ad_campaigns' + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        var method = id > 0 ? 'PUT' : 'POST';

        var btn = document.getElementById('campaignSaveBtn');
        if (btn) btn.disabled = true;

        fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    closeModal('campaignModal');
                    showNotification(t('campaign_saved', 'Campaign saved successfully'), 'success');
                    campaignCache = []; // bust cache
                    loadCampaigns({ page: campaignsPage, filters: campaignsFilters });
                    // Refresh ads campaign filter
                    refreshCampaignFilter();
                } else {
                    var msg = json.message || json.error || t('error_campaign_save', 'Failed to save campaign');
                    showNotification(msg, 'error');
                }
            })
            .catch(function () { showNotification(t('error_campaign_save', 'Failed to save campaign'), 'error'); })
            .finally(function () { if (btn) btn.disabled = false; });
    }

    /* Delete Campaign */
    function confirmDeleteCampaign(id) {
        if (!confirm(t('confirm_campaign_delete', 'Are you sure you want to delete this campaign?'))) return;
        deleteCampaign(id);
    }

    function deleteCampaign(id) {
        var url = (CFG.apiBase || '/api') + '/ad_campaigns' + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        fetch(url, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    showNotification(t('campaign_deleted', 'Campaign deleted successfully'), 'success');
                    campaignCache = [];
                    loadCampaigns({ page: campaignsPage, filters: campaignsFilters });
                    refreshCampaignFilter();
                } else {
                    showNotification(json.message || t('error_campaign_delete', 'Failed to delete campaign'), 'error');
                }
            })
            .catch(function () { showNotification(t('error_campaign_delete', 'Failed to delete campaign'), 'error'); });
    }

    /* Campaign filter */
    function applyCampaignFilters() {
        campaignsPage = 1;
        var getEl = function (id) { return document.getElementById(id); };
        campaignsFilters = {
            search:        (getEl('filterCampaignSearch')       ? getEl('filterCampaignSearch').value.trim()       : ''),
            status:        (getEl('filterCampaignStatus')       ? getEl('filterCampaignStatus').value              : ''),
            pricing_model: (getEl('filterCampaignPricingModel') ? getEl('filterCampaignPricingModel').value        : ''),
        };
        loadCampaigns({ page: campaignsPage, filters: campaignsFilters });
    }

    function clearCampaignFilters() {
        ['filterCampaignSearch', 'filterCampaignStatus', 'filterCampaignPricingModel'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        campaignsFilters = {};
        campaignsPage    = 1;
        loadCampaigns({ page: 1, filters: {} });
    }

    /* ══════════════════════════════════════════════
     * ADS (AD UNITS)
     * ══════════════════════════════════════════ */

    function refreshCampaignFilter() {
        var campFilter = document.getElementById('filterCampaign');
        if (!campFilter) return;
        loadCampaignsData(function (campaigns) {
            var html = '<option value="">' + esc(t('filter.all_campaigns', 'All Campaigns')) + '</option>';
            campaigns.forEach(function (c) {
                html += '<option value="' + esc(c.id) + '">' + esc(c.name || ('#' + c.id)) + '</option>';
            });
            campFilter.innerHTML = html;
        }, true);
    }

    function populateCampaignSelect(selectEl, selectedId) {
        loadCampaignsData(function (campaigns) {
            var html = '<option value="">' + esc(t('form.select_campaign', '-- Select Campaign --')) + '</option>';
            campaigns.forEach(function (c) {
                var sel = (selectedId && String(c.id) === String(selectedId)) ? ' selected' : '';
                html += '<option value="' + esc(c.id) + '"' + sel + '>' + esc(c.name || ('#' + c.id)) + '</option>';
            });
            selectEl.innerHTML = html;
        });
    }

    function loadAds(params) {
        params = params || {};
        var page    = params.page    || adsPage;
        var filters = params.filters || adsFilters;
        var offset  = (page - 1) * PER_PAGE;

        var url = (CFG.apiBase || '/api') + '/ads?limit=' + PER_PAGE + '&offset=' + offset + '&order_by=id&order_dir=DESC';
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        if (filters.status)      url += '&status='      + encodeURIComponent(filters.status);
        if (filters.target_type) url += '&target_type=' + encodeURIComponent(filters.target_type);
        if (filters.campaign_id) url += '&campaign_id=' + encodeURIComponent(filters.campaign_id);
        if (filters.search)      url += '&search='      + encodeURIComponent(filters.search);

        var tbody = document.getElementById('adsTableBody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center">...</td></tr>';

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var items = (json.data && json.data.items) ? json.data.items : [];
                var total = (json.data && json.data.meta) ? (json.data.meta.total || 0) : 0;
                renderAdsTable(items);
                renderAdsPagination(page, total);
                updateAdsPaginationInfo(page, items.length, total);
            })
            .catch(function () {
                showNotification(t('error_load', 'Failed to load ads'), 'error');
                if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + esc(t('table.no_records', 'No ads found')) + '</td></tr>';
            });
    }

    function renderAdsTable(items) {
        var tbody = document.getElementById('adsTableBody');
        if (!tbody) return;
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + esc(t('table.no_records', 'No ads found')) + '</td></tr>';
            return;
        }
        var html = '';
        items.forEach(function (ad) {
            html += '<tr>';
            html += '<td>' + esc(ad.id) + '</td>';
            html += '<td>' + esc(ad.campaign_name || ad.campaign_id) + '</td>';
            html += '<td>' + esc(t('target_type.' + ad.target_type, ad.target_type)) + '</td>';
            html += '<td><span class="truncate" title="' + esc(ad.target_value) + '">' + esc(ad.target_value || '-') + '</span></td>';
            html += '<td>' + statusBadge(ad.status) + '</td>';
            html += '<td>' + esc(ad.views_count || 0) + '</td>';
            html += '<td>' + esc(ad.clicks_count || 0) + '</td>';
            html += '<td>' + esc((ad.created_at || '').replace('T', ' ').substring(0, 16)) + '</td>';
            html += '<td><div class="row-actions">';
            if (CAN_EDIT) {
                html += '<button class="btn btn-secondary btn-sm btn-edit-ad" data-id="' + esc(ad.id) + '">' + esc(t('table.edit', 'Edit')) + '</button>';
            }
            if (CAN_DELETE) {
                html += '<button class="btn btn-danger btn-sm btn-delete-ad" data-id="' + esc(ad.id) + '">' + esc(t('table.delete', 'Delete')) + '</button>';
            }
            html += '</div></td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;

        tbody.querySelectorAll('.btn-edit-ad').forEach(function (btn) {
            btn.addEventListener('click', function () { openEditAdModal(parseInt(btn.dataset.id, 10)); });
        });
        tbody.querySelectorAll('.btn-delete-ad').forEach(function (btn) {
            btn.addEventListener('click', function () { confirmDeleteAd(parseInt(btn.dataset.id, 10)); });
        });
    }

    function renderAdsPagination(page, total) {
        var totalPages = Math.ceil(total / PER_PAGE) || 1;
        var pg = document.getElementById('adsPagination');
        if (!pg) return;
        var html = '';
        html += '<button class="page-btn" ' + (page <= 1 ? 'disabled' : '') + ' data-page="' + (page - 1) + '">' + esc(t('pagination.prev', 'Prev')) + '</button>';
        var start = Math.max(1, page - 2);
        var end   = Math.min(totalPages, start + 4);
        if (end - start < 4) start = Math.max(1, end - 4);
        for (var i = start; i <= end; i++) {
            html += '<button class="page-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }
        html += '<button class="page-btn" ' + (page >= totalPages ? 'disabled' : '') + ' data-page="' + (page + 1) + '">' + esc(t('pagination.next', 'Next')) + '</button>';
        pg.innerHTML = html;
        pg.querySelectorAll('.page-btn:not([disabled])').forEach(function (btn) {
            btn.addEventListener('click', function () {
                adsPage = parseInt(btn.dataset.page, 10);
                loadAds({ page: adsPage, filters: adsFilters });
            });
        });
    }

    function updateAdsPaginationInfo(page, count, total) {
        var el = document.getElementById('adsPaginationInfo');
        if (!el) return;
        var from = total === 0 ? 0 : (page - 1) * PER_PAGE + 1;
        var to   = (page - 1) * PER_PAGE + count;
        el.textContent = from + '-' + to + ' ' + t('pagination.of', 'of') + ' ' + total;
    }

    function openAddAdModal() {
        reloadConfig();
        var form = document.getElementById('adForm');
        if (form) form.reset();
        var idEl = document.getElementById('adId');
        if (idEl) idEl.value = '';
        var titleEl = document.getElementById('adModalTitle');
        if (titleEl) titleEl.textContent = t('modal.add_title', 'Add Ad Unit');

        var campSel = document.getElementById('adCampaignId');
        if (campSel) populateCampaignSelect(campSel, null);

        openModal('adModal');
    }

    function openEditAdModal(id) {
        reloadConfig();
        var url = (CFG.apiBase || '/api') + '/ads?id=' + id;
        if (CFG.tenantId) url += '&tenant_id=' + CFG.tenantId;
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                var ad = json.data || json;
                if (!ad || !ad.id) { showNotification(t('error_load', 'Failed to load ad'), 'error'); return; }

                var idEl = document.getElementById('adId');
                if (idEl) idEl.value = ad.id;

                var titleEl = document.getElementById('adModalTitle');
                if (titleEl) titleEl.textContent = t('modal.edit_title', 'Edit Ad Unit');

                var campSel = document.getElementById('adCampaignId');
                if (campSel) populateCampaignSelect(campSel, ad.campaign_id);

                var setVal = function (elId, val) { var el = document.getElementById(elId); if (el) el.value = val || ''; };
                setVal('adTargetType',  ad.target_type);
                setVal('adTargetValue', ad.target_value);
                setVal('adStatus',      ad.status);
                setVal('adViewsCount',  ad.views_count);
                setVal('adClicksCount', ad.clicks_count);

                openModal('adModal');
            })
            .catch(function () { showNotification(t('error_load', 'Failed to load ad'), 'error'); });
    }

    function saveAd() {
        var idEl = document.getElementById('adId');
        var id   = idEl ? parseInt(idEl.value, 10) : 0;

        var getVal = function (elId) { var el = document.getElementById(elId); return el ? el.value.trim() : ''; };

        var data = {
            campaign_id:  parseInt(getVal('adCampaignId'), 10) || 0,
            target_type:  getVal('adTargetType'),
            target_value: getVal('adTargetValue'),
            status:       getVal('adStatus'),
            views_count:  parseInt(getVal('adViewsCount'), 10)  || 0,
            clicks_count: parseInt(getVal('adClicksCount'), 10) || 0,
        };

        if (id > 0) data.id = id;

        var url    = (CFG.apiBase || '/api') + '/ads' + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        var method = id > 0 ? 'PUT' : 'POST';

        var btn = document.getElementById('adSaveBtn');
        if (btn) btn.disabled = true;

        fetch(url, {
            method: method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    closeModal('adModal');
                    showNotification(t('saved', 'Ad saved successfully'), 'success');
                    loadAds({ page: adsPage, filters: adsFilters });
                } else {
                    var msg = json.message || json.error || t('error_save', 'Failed to save ad');
                    showNotification(msg, 'error');
                }
            })
            .catch(function () { showNotification(t('error_save', 'Failed to save ad'), 'error'); })
            .finally(function () { if (btn) btn.disabled = false; });
    }

    function confirmDeleteAd(id) {
        if (!confirm(t('confirm_delete', 'Are you sure you want to delete this ad?'))) return;
        deleteAd(id);
    }

    function deleteAd(id) {
        var url = (CFG.apiBase || '/api') + '/ads' + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        fetch(url, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (json.success || json.status === 'success') {
                    showNotification(t('deleted', 'Ad deleted successfully'), 'success');
                    loadAds({ page: adsPage, filters: adsFilters });
                } else {
                    showNotification(json.message || t('error_delete', 'Failed to delete ad'), 'error');
                }
            })
            .catch(function () { showNotification(t('error_delete', 'Failed to delete ad'), 'error'); });
    }

    function applyAdsFilters() {
        adsPage = 1;
        var getEl = function (id) { return document.getElementById(id); };
        adsFilters = {
            search:      (getEl('filterSearch')     ? getEl('filterSearch').value.trim()  : ''),
            status:      (getEl('filterStatus')     ? getEl('filterStatus').value         : ''),
            target_type: (getEl('filterTargetType') ? getEl('filterTargetType').value     : ''),
            campaign_id: (getEl('filterCampaign')   ? getEl('filterCampaign').value       : ''),
        };
        loadAds({ page: adsPage, filters: adsFilters });
    }

    function clearAdsFilters() {
        ['filterSearch', 'filterStatus', 'filterTargetType', 'filterCampaign'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        adsFilters = {};
        adsPage    = 1;
        loadAds({ page: 1, filters: {} });
    }

    /* ──────────────────────────────────────────────
     * Wire DOM events
     * ──────────────────────────────────────────── */
    function bindEvents() {
        var on = function (id, evt, fn) {
            var el = document.getElementById(id);
            if (el) el.addEventListener(evt, fn);
        };

        // Tab switching
        document.querySelectorAll('.ads-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () { switchTab(btn.dataset.tab); });
        });

        // Campaign events
        on('btnAddCampaign',          'click', openAddCampaignModal);
        on('btnCampaignFilter',       'click', applyCampaignFilters);
        on('btnClearCampaignFilters', 'click', clearCampaignFilters);
        on('campaignSaveBtn',         'click', saveCampaign);

        var campSearch = document.getElementById('filterCampaignSearch');
        if (campSearch) campSearch.addEventListener('keydown', function (e) { if (e.key === 'Enter') applyCampaignFilters(); });

        // Ad events
        on('btnAddAd',        'click', openAddAdModal);
        on('btnFilter',       'click', applyAdsFilters);
        on('btnClearFilters', 'click', clearAdsFilters);
        on('adSaveBtn',       'click', saveAd);

        var adSearch = document.getElementById('filterSearch');
        if (adSearch) adSearch.addEventListener('keydown', function (e) { if (e.key === 'Enter') applyAdsFilters(); });

        // Close modal buttons
        document.querySelectorAll('.btn-close-ads-modal').forEach(function (btn) {
            btn.addEventListener('click', function () { closeModal(btn.dataset.modal || 'adModal'); });
        });

        // Close modals on backdrop click
        ['campaignModal', 'adModal'].forEach(function (modalId) {
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal(modalId);
                });
            }
        });
    }

    /* ──────────────────────────────────────────────
     * Initialise
     * ──────────────────────────────────────────── */
    function init() {
        reloadConfig();
        bindEvents();

        // Show correct tab button
        switchTab('campaigns');

        // Populate campaign filter on ads tab
        refreshCampaignFilter();

        // Load data
        loadCampaigns({ page: 1, filters: {} });
        loadAds({ page: 1, filters: {} });
    }

    /* ──────────────────────────────────────────────
     * Expose & boot with i18n guard
     * ──────────────────────────────────────────── */
    window.Ads = { init: init };

    (function boot() {
        function tryInit() {
            if (!window.TRANSLATIONS) return;
            cleanup();
            if (window.ADS_CONFIG && window.ADS_CONFIG.strings) {
                STRINGS = window.ADS_CONFIG.strings;
            }
            init();
        }

        var INIT_TIMEOUT_MS = 6000;
        var poll;
        function cleanup() {
            clearInterval(poll);
            document.removeEventListener('admin:i18n:applied', tryInit);
        }

        document.addEventListener('admin:i18n:applied', tryInit);
        tryInit();
        poll = setInterval(function () {
            if (window.TRANSLATIONS) { tryInit(); }
        }, INIT_TIMEOUT_MS);
        setTimeout(function () { if (!window.TRANSLATIONS) { cleanup(); init(); } }, INIT_TIMEOUT_MS);
    }());

}());
