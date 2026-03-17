/**
 * /admin/assets/js/pages/ads.js
 * Ads Management - Production Ready
 */
(function () {
    'use strict';

    /* ──────────────────────────────────────────────
     * Config & state
     * ──────────────────────────────────────────── */
    var CFG, CSRF, STRINGS, CAN_CREATE, CAN_EDIT, CAN_DELETE;
    var PER_PAGE = 25;
    var currentPage = 1;
    var currentFilters = {};
    var campaignCache = [];

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
     * Status badge
     * ──────────────────────────────────────────── */
    function statusBadge(status) {
        var cls = {
            active:   'badge-active',
            paused:   'badge-paused',
            rejected: 'badge-rejected'
        }[status] || 'badge-default';
        var label = t('status.' + status, status);
        return '<span class="badge ' + cls + '">' + esc(label) + '</span>';
    }

    /* ──────────────────────────────────────────────
     * Load campaigns for dropdown
     * ──────────────────────────────────────────── */
    function loadCampaigns(callback) {
        if (campaignCache.length > 0) { callback(campaignCache); return; }
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

    function populateCampaignSelect(selectEl, selectedId) {
        loadCampaigns(function (campaigns) {
            var html = '<option value="">' + esc(t('form.select_campaign', '-- Select Campaign --')) + '</option>';
            campaigns.forEach(function (c) {
                var sel = (selectedId && String(c.id) === String(selectedId)) ? ' selected' : '';
                html += '<option value="' + esc(c.id) + '"' + sel + '>' + esc(c.name || ('#' + c.id)) + '</option>';
            });
            selectEl.innerHTML = html;
        });
    }

    /* ──────────────────────────────────────────────
     * Load ads list
     * ──────────────────────────────────────────── */
    function loadAds(params) {
        params = params || {};
        var page    = params.page    || currentPage;
        var filters = params.filters || currentFilters;
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
                renderTable(items);
                renderPagination(page, total);
                updatePaginationInfo(page, items.length, total);
            })
            .catch(function () {
                showNotification(t('error_load', 'Failed to load ads'), 'error');
                if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center">' + esc(t('table.no_records', 'No ads found')) + '</td></tr>';
            });
    }

    /* ──────────────────────────────────────────────
     * Render table
     * ──────────────────────────────────────────── */
    function renderTable(items) {
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
                html += '<button class="btn btn-secondary btn-sm btn-edit-ad" data-id="' + esc(ad.id) + '" title="' + esc(t('table.edit', 'Edit')) + '">' + esc(t('table.edit', 'Edit')) + '</button>';
            }
            if (CAN_DELETE) {
                html += '<button class="btn btn-danger btn-sm btn-delete-ad" data-id="' + esc(ad.id) + '" title="' + esc(t('table.delete', 'Delete')) + '">' + esc(t('table.delete', 'Delete')) + '</button>';
            }
            html += '</div></td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;

        // Bind edit & delete buttons
        tbody.querySelectorAll('.btn-edit-ad').forEach(function (btn) {
            btn.addEventListener('click', function () { openEditModal(parseInt(btn.dataset.id, 10)); });
        });
        tbody.querySelectorAll('.btn-delete-ad').forEach(function (btn) {
            btn.addEventListener('click', function () { confirmDelete(parseInt(btn.dataset.id, 10)); });
        });
    }

    /* ──────────────────────────────────────────────
     * Pagination
     * ──────────────────────────────────────────── */
    function renderPagination(page, total) {
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
                currentPage = parseInt(btn.dataset.page, 10);
                loadAds({ page: currentPage, filters: currentFilters });
            });
        });
    }

    function updatePaginationInfo(page, count, total) {
        var el = document.getElementById('adsPaginationInfo');
        if (!el) return;
        var from = total === 0 ? 0 : (page - 1) * PER_PAGE + 1;
        var to   = (page - 1) * PER_PAGE + count;
        el.textContent = from + '-' + to + ' ' + t('pagination.of', 'of') + ' ' + total;
    }

    /* ──────────────────────────────────────────────
     * Add Modal
     * ──────────────────────────────────────────── */
    function openAddModal() {
        reloadConfig();
        var form = document.getElementById('adForm');
        if (form) form.reset();
        var idEl = document.getElementById('adId');
        if (idEl) idEl.value = '';
        var titleEl = document.getElementById('adModalTitle');
        if (titleEl) titleEl.textContent = t('modal.add_title', 'Add Ad');

        var campSel = document.getElementById('adCampaignId');
        if (campSel) populateCampaignSelect(campSel, null);

        openModal('adModal');
    }

    /* ──────────────────────────────────────────────
     * Edit Modal
     * ──────────────────────────────────────────── */
    function openEditModal(id) {
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
                if (titleEl) titleEl.textContent = t('modal.edit_title', 'Edit Ad');

                var campSel = document.getElementById('adCampaignId');
                if (campSel) populateCampaignSelect(campSel, ad.campaign_id);

                var setVal = function (elId, val) {
                    var el = document.getElementById(elId);
                    if (el) el.value = val || '';
                };
                setVal('adTargetType',  ad.target_type);
                setVal('adTargetValue', ad.target_value);
                setVal('adStatus',      ad.status);
                setVal('adViewsCount',  ad.views_count);
                setVal('adClicksCount', ad.clicks_count);

                openModal('adModal');
            })
            .catch(function () { showNotification(t('error_load', 'Failed to load ad'), 'error'); });
    }

    /* ──────────────────────────────────────────────
     * Save Ad (create / update)
     * ──────────────────────────────────────────── */
    function saveAd() {
        var idEl = document.getElementById('adId');
        var id   = idEl ? parseInt(idEl.value, 10) : 0;

        var getVal = function (elId) {
            var el = document.getElementById(elId);
            return el ? el.value.trim() : '';
        };

        var data = {
            campaign_id:  parseInt(getVal('adCampaignId'), 10) || 0,
            target_type:  getVal('adTargetType'),
            target_value: getVal('adTargetValue'),
            status:       getVal('adStatus'),
            views_count:  parseInt(getVal('adViewsCount'), 10)  || 0,
            clicks_count: parseInt(getVal('adClicksCount'), 10) || 0,
        };

        if (id > 0) data.id = id;

        var isUpdate = id > 0;
        var url    = (CFG.apiBase || '/api') + '/ads' + (CFG.tenantId ? '?tenant_id=' + CFG.tenantId : '');
        var method = isUpdate ? 'PUT' : 'POST';

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
                    loadAds({ page: currentPage, filters: currentFilters });
                } else {
                    var msg = json.message || json.error || t('error_save', 'Failed to save ad');
                    showNotification(msg, 'error');
                }
            })
            .catch(function () { showNotification(t('error_save', 'Failed to save ad'), 'error'); })
            .finally(function () { if (btn) btn.disabled = false; });
    }

    /* ──────────────────────────────────────────────
     * Delete Ad
     * ──────────────────────────────────────────── */
    function confirmDelete(id) {
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
                    loadAds({ page: currentPage, filters: currentFilters });
                } else {
                    showNotification(json.message || t('error_delete', 'Failed to delete ad'), 'error');
                }
            })
            .catch(function () { showNotification(t('error_delete', 'Failed to delete ad'), 'error'); });
    }

    /* ──────────────────────────────────────────────
     * Apply filters
     * ──────────────────────────────────────────── */
    function applyFilters() {
        currentPage = 1;
        var getEl = function (id) { return document.getElementById(id); };
        currentFilters = {
            search:      (getEl('filterSearch')     ? getEl('filterSearch').value.trim()     : ''),
            status:      (getEl('filterStatus')     ? getEl('filterStatus').value            : ''),
            target_type: (getEl('filterTargetType') ? getEl('filterTargetType').value        : ''),
            campaign_id: (getEl('filterCampaign')   ? getEl('filterCampaign').value          : ''),
        };
        loadAds({ page: currentPage, filters: currentFilters });
    }

    function clearFilters() {
        ['filterSearch', 'filterStatus', 'filterTargetType', 'filterCampaign'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.value = '';
        });
        currentFilters = {};
        currentPage    = 1;
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

        on('btnAddAd',         'click', openAddModal);
        on('btnFilter',        'click', applyFilters);
        on('btnClearFilters',  'click', clearFilters);
        on('adSaveBtn',        'click', saveAd);

        // Close modal buttons
        document.querySelectorAll('.btn-close-ads-modal').forEach(function (btn) {
            btn.addEventListener('click', function () { closeModal(btn.dataset.modal || 'adModal'); });
        });

        // Close modal on backdrop click
        var modal = document.getElementById('adModal');
        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal('adModal');
            });
        }

        // Apply filter on Enter in search
        var searchInput = document.getElementById('filterSearch');
        if (searchInput) {
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') applyFilters();
            });
        }
    }

    /* ──────────────────────────────────────────────
     * Initialise
     * ──────────────────────────────────────────── */
    function init() {
        reloadConfig();
        bindEvents();
        // Populate campaign filter dropdown
        var campFilter = document.getElementById('filterCampaign');
        if (campFilter) {
            loadCampaigns(function (campaigns) {
                var html = '<option value="">' + esc(t('filter.all_campaigns', 'All Campaigns')) + '</option>';
                campaigns.forEach(function (c) {
                    html += '<option value="' + esc(c.id) + '">' + esc(c.name || ('#' + c.id)) + '</option>';
                });
                campFilter.innerHTML = html;
            });
        }
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
