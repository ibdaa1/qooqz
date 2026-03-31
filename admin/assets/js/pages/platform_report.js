/**
 * Platform Report & Analytics Module
 * admin/assets/js/pages/platform_report.js
 *
 * Handles:
 *  - Dashboard summary cards
 *  - Report generation with filters
 *  - Metric cards rendering
 *  - Chart.js time-series charts
 *  - Data tables for detailed metrics
 *  - Export functionality
 */
(function () {
    'use strict';

    // ═══════════════════════════════════════════
    // CONFIG & STATE
    // ═══════════════════════════════════════════
    const CFG = window.__PR_CONFIG || {};
    const API = (CFG.apiBase || '/api') + '/platform_report';
    const T   = CFG.strings || {};
    let mainChart = null;
    let currentReportData = null;

    function t(key, fallback) {
        return T[key] || fallback || key;
    }

    function fmt(num) {
        if (num === null || num === undefined || num === '') return '-';
        const n = parseFloat(num);
        if (isNaN(n)) return num;
        return n.toLocaleString(CFG.lang === 'ar' ? 'ar-SA' : 'en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        });
    }

    function fmtCurrency(num) {
        if (num === null || num === undefined || num === '') return '-';
        const n = parseFloat(num);
        if (isNaN(n)) return num;
        return n.toLocaleString(CFG.lang === 'ar' ? 'ar-SA' : 'en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function h(str) {
        const el = document.createElement('span');
        el.textContent = str;
        return el.innerHTML;
    }

    // ═══════════════════════════════════════════
    // DOM REFERENCES
    // ═══════════════════════════════════════════
    function $(sel) { return document.querySelector(sel); }
    function $$(sel) { return document.querySelectorAll(sel); }

    // ═══════════════════════════════════════════
    // API HELPER
    // ═══════════════════════════════════════════
    async function apiGet(action, params) {
        const url = new URL(API, window.location.origin);
        url.searchParams.set('action', action);
        if (params) {
            Object.entries(params).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined) {
                    url.searchParams.set(k, v);
                }
            });
        }
        const resp = await fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        return resp.json();
    }

    async function apiPost(action, body) {
        const url = new URL(API, window.location.origin);
        url.searchParams.set('action', action);
        const resp = await fetch(url.toString(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        });
        return resp.json();
    }

    // ═══════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════
    function init() {
        // Set default dates (last 30 days)
        const endDateEl = $('#prEndDate');
        const startDateEl = $('#prStartDate');
        if (endDateEl && startDateEl) {
            const now = new Date();
            endDateEl.value = now.toISOString().split('T')[0];
            const thirtyDaysAgo = new Date(now);
            thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
            startDateEl.value = thirtyDaysAgo.toISOString().split('T')[0];
        }

        // Default report type
        const reportTypeEl = $('#prReportType');
        if (reportTypeEl) {
            reportTypeEl.value = 'sales_overview';
        }

        // Bind generate button
        const genBtn = $('#prGenerateBtn');
        if (genBtn) {
            genBtn.addEventListener('click', generateReport);
        }

        // Bind export buttons
        $$('.pr-btn-export').forEach(btn => {
            btn.addEventListener('click', function () {
                requestExport(this.dataset.format);
            });
        });

        // Load tenants if super admin
        if (CFG.isSuperAdmin) {
            loadTenants();
        }

        // Load entities for filter
        loadEntities();

        // Auto-generate default report after a short delay for initial render
        loadDashboardAndAutoReport();
    }

    /**
     * Load dashboard summary first, then auto-generate report.
     */
    async function loadDashboardAndAutoReport() {
        await loadDashboardSummary();
        generateReport();
    }

    // ═══════════════════════════════════════════
    // DASHBOARD SUMMARY
    // ═══════════════════════════════════════════
    async function loadDashboardSummary() {
        try {
            const params = {};
            if (CFG.tenantId) params.tenant_id = CFG.tenantId;
            const resp = await apiGet('dashboard', params);
            if (resp.success && resp.data) {
                const d = resp.data;
                const todayOrders = $('#todayOrders');
                const todayRevenue = $('#todayRevenue');
                const todayCustomers = $('#todayCustomers');
                const monthOrders = $('#monthOrders');
                const monthRevenue = $('#monthRevenue');
                const monthCustomers = $('#monthCustomers');
                const monthAvgOrder = $('#monthAvgOrder');

                if (todayOrders) todayOrders.textContent = fmt(d.today?.orders);
                if (todayRevenue) todayRevenue.textContent = fmtCurrency(d.today?.revenue);
                if (todayCustomers) todayCustomers.textContent = fmt(d.today?.customers);
                if (monthOrders) monthOrders.textContent = fmt(d.month?.orders);
                if (monthRevenue) monthRevenue.textContent = fmtCurrency(d.month?.revenue);
                if (monthCustomers) monthCustomers.textContent = fmt(d.month?.customers);
                if (monthAvgOrder) monthAvgOrder.textContent = fmtCurrency(d.month?.avg_order);
            }
        } catch (e) {
            console.error('Failed to load dashboard summary:', e);
        }
    }

    // ═══════════════════════════════════════════
    // LOAD TENANTS (super admin) – searchable autocomplete
    // ═══════════════════════════════════════════
    let tenantSearchTimer = null;

    async function loadTenants() {
        const searchInput = $('#prTenantSearch');
        const hiddenInput = $('#prTenantId');
        const dropdown = $('#prTenantDropdown');
        if (!searchInput || !hiddenInput || !dropdown) return;

        // Debounced search on input
        searchInput.addEventListener('input', function () {
            clearTimeout(tenantSearchTimer);
            const query = this.value.trim();
            if (query.length < 1) {
                dropdown.style.display = 'none';
                hiddenInput.value = '';
                loadEntities();
                return;
            }
            tenantSearchTimer = setTimeout(function () {
                searchTenants(query);
            }, 300);
        });

        // Hide dropdown on click outside
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Show dropdown on focus if has value
        searchInput.addEventListener('focus', function () {
            if (this.value.trim().length >= 1 && dropdown.children.length > 0) {
                dropdown.style.display = 'block';
            }
        });
    }

    async function searchTenants(query) {
        const dropdown = $('#prTenantDropdown');
        const hiddenInput = $('#prTenantId');
        const searchInput = $('#prTenantSearch');
        if (!dropdown) return;

        try {
            const url = new URL((CFG.apiBase || '/api') + '/tenants', window.location.origin);
            url.searchParams.set('limit', '20');
            url.searchParams.set('search', query);
            const resp = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            const items = data?.data?.items || data?.data || [];

            dropdown.innerHTML = '';

            // "All Tenants" clear option
            const clearDiv = document.createElement('div');
            clearDiv.className = 'pr-autocomplete-clear';
            clearDiv.textContent = t('all_tenants', '✕ All Tenants (clear)');
            clearDiv.addEventListener('click', function () {
                hiddenInput.value = '';
                searchInput.value = '';
                dropdown.style.display = 'none';
                loadEntities();
            });
            dropdown.appendChild(clearDiv);

            if (items.length === 0) {
                const noResult = document.createElement('div');
                noResult.className = 'pr-autocomplete-item';
                noResult.textContent = t('no_results', 'No tenants found');
                noResult.style.opacity = '0.6';
                noResult.style.cursor = 'default';
                dropdown.appendChild(noResult);
            } else {
                items.forEach(function (tenant) {
                    const item = document.createElement('div');
                    item.className = 'pr-autocomplete-item';
                    const nameText = document.createTextNode(tenant.name || ('Tenant #' + tenant.id));
                    item.appendChild(nameText);
                    const idSpan = document.createElement('span');
                    idSpan.className = 'pr-ac-id';
                    idSpan.textContent = '#' + tenant.id;
                    item.appendChild(idSpan);
                    item.addEventListener('click', function () {
                        hiddenInput.value = tenant.id;
                        searchInput.value = tenant.name || ('Tenant #' + tenant.id);
                        dropdown.style.display = 'none';
                        loadEntities(tenant.id);
                    });
                    dropdown.appendChild(item);
                });
            }

            dropdown.style.display = 'block';
        } catch (e) {
            console.error('Failed to search tenants:', e);
        }
    }

    // ═══════════════════════════════════════════
    // LOAD ENTITIES (for entity filter)
    // ═══════════════════════════════════════════
    async function loadEntities(tenantIdOverride) {
        try {
            const sel = $('#prEntityId');
            if (!sel) return;

            // Determine tenant ID: override > hidden input > config
            let tid = tenantIdOverride;
            if (tid === undefined) {
                const hiddenTenant = $('#prTenantId');
                tid = hiddenTenant ? hiddenTenant.value : '';
            }
            if (!tid && CFG.tenantId) tid = CFG.tenantId;

            // Clear existing options
            sel.innerHTML = '<option value="">' + t('all_entities', 'All Entities') + '</option>';

            const url = new URL((CFG.apiBase || '/api') + '/entities', window.location.origin);
            url.searchParams.set('limit', '200');
            if (tid) url.searchParams.set('tenant_id', tid);

            const resp = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            const items = data?.data?.items || data?.data || [];
            items.forEach(function (e) {
                const opt = document.createElement('option');
                opt.value = e.id;
                opt.textContent = e.store_name || e.name || ('Entity #' + e.id);
                sel.appendChild(opt);
            });
        } catch (e) {
            console.error('Failed to load entities:', e);
        }
    }

    // ═══════════════════════════════════════════
    // GENERATE REPORT
    // ═══════════════════════════════════════════
    async function generateReport() {
        const reportType = $('#prReportType')?.value;
        const startDate = $('#prStartDate')?.value;
        const endDate = $('#prEndDate')?.value;
        const groupBy = $('#prGroupBy')?.value || 'day';
        const tenantIdEl = $('#prTenantId');
        const tenantId = tenantIdEl ? tenantIdEl.value : (CFG.tenantId || '');
        const entityIdEl = $('#prEntityId');
        const entityId = entityIdEl ? entityIdEl.value : '';

        if (!reportType) {
            alert(t('select_report', 'Please select a report type'));
            return;
        }
        if (!startDate || !endDate) {
            alert(t('start_date', 'Please select start and end dates'));
            return;
        }

        showLoading(true);
        hideResults();

        try {
            const resp = await apiGet('report', {
                report_type: reportType,
                start_date: startDate,
                end_date: endDate,
                group_by: groupBy,
                tenant_id: tenantId,
                entity_id: entityId
            });

            if (resp.success && resp.data?.success) {
                currentReportData = resp.data;
                renderReport(resp.data);
                showResults(true);
            } else {
                const msg = resp.message || resp.data?.errors?.join('; ') || t('error_loading');
                showNoData(msg);
            }
        } catch (e) {
            console.error('Failed to generate report:', e);
            showNoData(t('error_loading'));
        } finally {
            showLoading(false);
        }
    }

    // ═══════════════════════════════════════════
    // RENDER REPORT
    // ═══════════════════════════════════════════
    function renderReport(data) {
        renderMetrics(data.report_type, data.metrics || {});
        renderChart(data.report_type, data.time_series || []);
        renderTable(data.report_type, data.metrics || {});
    }

    // ═══════════════════════════════════════════
    // RENDER METRICS CARDS
    // ═══════════════════════════════════════════
    function renderMetrics(type, metrics) {
        const grid = $('#prMetricsGrid');
        if (!grid) return;

        const cards = getMetricCards(type, metrics);
        grid.innerHTML = cards.map(c =>
            `<div class="pr-metric-card ${c.color || ''}">
                <div class="pr-metric-icon">${c.icon || '📊'}</div>
                <div class="pr-metric-info">
                    <div class="pr-metric-value">${h(c.value)}</div>
                    <div class="pr-metric-label">${h(c.label)}</div>
                </div>
            </div>`
        ).join('');
    }

    function getMetricCards(type, m) {
        switch (type) {
            case 'sales_overview':
                return [
                    { icon: '🛒', label: t('total_orders'), value: fmt(m.total_orders), color: 'pr-blue' },
                    { icon: '💰', label: t('total_revenue'), value: fmtCurrency(m.total_revenue), color: 'pr-green' },
                    { icon: '👥', label: t('unique_customers'), value: fmt(m.unique_customers), color: 'pr-purple' },
                    { icon: '📦', label: t('avg_order_value'), value: fmtCurrency(m.avg_order_value), color: 'pr-orange' },
                    { icon: '✅', label: t('completed_orders'), value: fmt(m.completed_orders), color: 'pr-green' },
                    { icon: '❌', label: t('cancelled_orders'), value: fmt(m.cancelled_orders), color: 'pr-red' },
                    { icon: '💸', label: t('total_discounts'), value: fmtCurrency(m.total_discounts), color: 'pr-yellow' },
                    { icon: '💳', label: t('paid_orders'), value: fmt(m.paid_orders), color: 'pr-blue' },
                ];

            case 'revenue_profit':
                return [
                    { icon: '💰', label: t('gross_revenue'), value: fmtCurrency(m.gross_revenue), color: 'pr-green' },
                    { icon: '💵', label: t('net_revenue'), value: fmtCurrency(m.net_revenue), color: 'pr-blue' },
                    { icon: '🏷️', label: t('total_discounts'), value: fmtCurrency(m.total_discounts), color: 'pr-yellow' },
                    { icon: '🏦', label: t('total_tax'), value: fmtCurrency(m.total_tax), color: 'pr-orange' },
                    { icon: '🚚', label: t('total_shipping'), value: fmtCurrency(m.total_shipping), color: 'pr-purple' },
                    { icon: '💹', label: t('total_commissions'), value: fmtCurrency(m.total_commissions), color: 'pr-red' },
                ];

            case 'orders_performance':
                return [
                    { icon: '📦', label: t('total_orders'), value: fmt(m.total_orders), color: 'pr-blue' },
                    { icon: '⏳', label: t('pending_orders'), value: fmt(m.pending_orders), color: 'pr-yellow' },
                    { icon: '✅', label: t('delivered_orders'), value: fmt(m.delivered_orders), color: 'pr-green' },
                    { icon: '❌', label: t('cancelled_orders'), value: fmt(m.cancelled_orders), color: 'pr-red' },
                    { icon: '🌐', label: t('online_orders'), value: fmt(m.online_orders), color: 'pr-blue' },
                    { icon: '🧾', label: t('pos_orders'), value: fmt(m.pos_orders), color: 'pr-purple' },
                    { icon: '⏱️', label: t('avg_delivery_hours'), value: fmt(m.avg_delivery_hours), color: 'pr-orange' },
                    { icon: '🔄', label: t('refunded_orders'), value: fmt(m.refunded_orders), color: 'pr-red' },
                    { icon: '🚚', label: t('total_deliveries', 'Total Deliveries'), value: fmt(m.total_deliveries), color: 'pr-blue' },
                    { icon: '📍', label: t('pending_deliveries', 'Pending Deliveries'), value: fmt(m.pending_deliveries), color: 'pr-yellow' },
                    { icon: '🚛', label: t('in_transit_deliveries', 'In Transit'), value: fmt(m.in_transit_deliveries), color: 'pr-purple' },
                    { icon: '✅', label: t('completed_deliveries', 'Completed Deliveries'), value: fmt(m.completed_deliveries), color: 'pr-green' },
                    { icon: '💰', label: t('total_delivery_fees', 'Delivery Fees'), value: fmtCurrency(m.total_delivery_fees), color: 'pr-orange' },
                    { icon: '⏱️', label: t('avg_delivery_minutes', 'Avg Delivery (min)'), value: fmt(m.avg_delivery_minutes), color: 'pr-purple' },
                ];

            case 'products_performance':
                return [
                    { icon: '📦', label: t('total_products'), value: fmt(m.total_products), color: 'pr-blue' },
                    { icon: '✅', label: t('active_products'), value: fmt(m.active_products), color: 'pr-green' },
                    { icon: '⚠️', label: t('out_of_stock'), value: fmt(m.out_of_stock), color: 'pr-red' },
                    { icon: '📉', label: t('low_stock'), value: fmt(m.low_stock), color: 'pr-yellow' },
                    { icon: '🛍️', label: t('products_sold'), value: fmt(m.products_sold_count), color: 'pr-purple' },
                    { icon: '📊', label: t('units_sold'), value: fmt(m.total_units_sold), color: 'pr-orange' },
                    { icon: '👁️', label: t('product_views'), value: fmt(m.product_views), color: 'pr-blue' },
                    { icon: '👆', label: t('product_clicks'), value: fmt(m.product_clicks), color: 'pr-green' },
                    { icon: '🛒', label: t('add_to_cart_events'), value: fmt(m.add_to_cart_events), color: 'pr-purple' },
                    { icon: '❤️', label: t('product_favorites'), value: fmt(m.product_favorites), color: 'pr-red' },
                ];

            case 'ads_performance':
                return [
                    { icon: '📺', label: t('active_campaigns'), value: fmt(m.active_campaigns), color: 'pr-blue' },
                    { icon: '👁️', label: t('total_impressions'), value: fmt(m.total_impressions), color: 'pr-purple' },
                    { icon: '👆', label: t('total_clicks'), value: fmt(m.total_clicks), color: 'pr-green' },
                    { icon: '📈', label: t('ctr'), value: fmt(m.ctr) + '%', color: 'pr-orange' },
                    { icon: '🔗', label: t('total_interactions'), value: fmt(m.total_interactions), color: 'pr-red' },
                ];

            case 'returns_complaints':
                return [
                    { icon: '↩️', label: t('total_returns'), value: fmt(m.total_returns), color: 'pr-blue' },
                    { icon: '⏳', label: t('pending_returns'), value: fmt(m.pending_returns), color: 'pr-yellow' },
                    { icon: '✅', label: t('approved_returns'), value: fmt(m.approved_returns), color: 'pr-green' },
                    { icon: '❌', label: t('rejected_returns'), value: fmt(m.rejected_returns), color: 'pr-red' },
                    { icon: '🎫', label: t('total_tickets'), value: fmt(m.total_tickets), color: 'pr-purple' },
                    { icon: '📂', label: t('open_tickets'), value: fmt(m.open_tickets), color: 'pr-orange' },
                    { icon: '✔️', label: t('resolved_tickets'), value: fmt(m.resolved_tickets), color: 'pr-green' },
                ];

            case 'entities_performance':
                return [
                    { icon: '🏪', label: t('total_entities'), value: fmt(m.total_entities), color: 'pr-blue' },
                    { icon: '✅', label: t('active_entities'), value: fmt(m.active_entities), color: 'pr-green' },
                    { icon: '⏳', label: t('pending_entities'), value: fmt(m.pending_entities), color: 'pr-yellow' },
                    { icon: '🚫', label: t('suspended_entities'), value: fmt(m.suspended_entities), color: 'pr-red' },
                ];

            case 'customer_behavior':
                return [
                    { icon: '👤', label: t('new_users'), value: fmt(m.new_users), color: 'pr-blue' },
                    { icon: '🛒', label: t('total_carts'), value: fmt(m.total_carts), color: 'pr-purple' },
                    { icon: '🚫', label: t('abandoned_carts'), value: fmt(m.abandoned_carts), color: 'pr-red' },
                    { icon: '✅', label: t('converted_carts'), value: fmt(m.converted_carts), color: 'pr-green' },
                    { icon: '📈', label: t('cart_conversion_rate'), value: fmt(m.cart_conversion_rate) + '%', color: 'pr-orange' },
                    { icon: '🔄', label: t('repeat_customers'), value: fmt(m.repeat_customers), color: 'pr-blue' },
                    { icon: '❤️', label: t('wishlist_items'), value: fmt(m.wishlist_items), color: 'pr-yellow' },
                ];

            case 'delivery_performance':
                return [
                    { icon: '🚚', label: t('total_deliveries', 'Total Deliveries'), value: fmt(m.total_deliveries), color: 'pr-blue' },
                    { icon: '📍', label: t('pending_deliveries', 'Pending'), value: fmt(m.pending_deliveries), color: 'pr-yellow' },
                    { icon: '🚛', label: t('in_transit_deliveries', 'In Transit'), value: fmt(m.in_transit_deliveries), color: 'pr-purple' },
                    { icon: '✅', label: t('completed_deliveries', 'Completed'), value: fmt(m.completed_deliveries), color: 'pr-green' },
                    { icon: '❌', label: t('failed_deliveries', 'Failed'), value: fmt(m.failed_deliveries), color: 'pr-red' },
                    { icon: '💰', label: t('total_delivery_fees', 'Delivery Fees'), value: fmtCurrency(m.total_delivery_fees), color: 'pr-orange' },
                    { icon: '⏱️', label: t('avg_delivery_minutes', 'Avg Time (min)'), value: fmt(m.avg_delivery_minutes), color: 'pr-purple' },
                    { icon: '💵', label: t('total_delivery_revenue', 'Delivery Revenue'), value: fmtCurrency(m.total_delivery_revenue), color: 'pr-green' },
                ];

            case 'platform_health':
                return [
                    { icon: '👥', label: t('total_users'), value: fmt(m.total_users), color: 'pr-blue' },
                    { icon: '✅', label: t('active_users'), value: fmt(m.active_users), color: 'pr-green' },
                    { icon: '🏢', label: t('total_tenants'), value: fmt(m.total_tenants), color: 'pr-purple' },
                    { icon: '🏪', label: t('total_entities'), value: fmt(m.total_entities), color: 'pr-orange' },
                    { icon: '📦', label: t('total_products'), value: fmt(m.total_products), color: 'pr-blue' },
                    { icon: '🛒', label: t('period_orders'), value: fmt(m.period_orders), color: 'pr-green' },
                    { icon: '💰', label: t('period_revenue'), value: fmtCurrency(m.period_revenue), color: 'pr-green' },
                    { icon: '🔄', label: t('active_subscriptions'), value: fmt(m.active_subscriptions), color: 'pr-purple' },
                ];

            default:
                return Object.entries(m)
                    .filter(([k, v]) => typeof v !== 'object')
                    .map(([k, v]) => ({
                        icon: '📊',
                        label: t(k, k.replace(/_/g, ' ')),
                        value: typeof v === 'number' ? fmt(v) : String(v),
                        color: 'pr-blue'
                    }));
        }
    }

    // ═══════════════════════════════════════════
    // RENDER CHART
    // ═══════════════════════════════════════════
    function renderChart(type, timeSeries) {
        const canvas = $('#prMainChart');
        if (!canvas) return;

        // Destroy previous chart
        if (mainChart) {
            mainChart.destroy();
            mainChart = null;
        }

        if (!timeSeries || timeSeries.length === 0) {
            canvas.parentElement.style.display = 'none';
            return;
        }
        canvas.parentElement.style.display = 'block';

        // Ensure Chart.js is loaded
        if (typeof Chart === 'undefined') {
            console.warn('Chart.js not loaded yet, retrying...');
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';
            script.onload = function () { renderChart(type, timeSeries); };
            document.head.appendChild(script);
            return;
        }

        const labels = timeSeries.map(d => d.period);

        const primaryColor = getComputedStyle(document.documentElement)
            .getPropertyValue('--primary-color').trim() || '#4F46E5';
        const successColor = getComputedStyle(document.documentElement)
            .getPropertyValue('--success-color').trim() || '#10B981';

        // Build datasets based on report type
        const chartConfig = getChartConfig(type, timeSeries, labels, primaryColor, successColor);

        mainChart = new Chart(canvas.getContext('2d'), chartConfig);
    }

    function getChartConfig(type, timeSeries, labels, primaryColor, successColor) {
        switch (type) {
            case 'ads_performance':
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('total_impressions', 'Views'),
                                data: timeSeries.map(d => parseFloat(d.views) || 0),
                                backgroundColor: primaryColor + '80',
                                borderColor: primaryColor,
                                borderWidth: 1,
                                yAxisID: 'y',
                                order: 2,
                            },
                            {
                                label: t('total_clicks', 'Clicks'),
                                data: timeSeries.map(d => parseFloat(d.clicks) || 0),
                                type: 'line',
                                borderColor: successColor,
                                backgroundColor: successColor + '20',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                yAxisID: 'y1',
                                order: 1,
                            },
                        ]
                    },
                    options: buildChartOptions(t('total_impressions', 'Views'), t('total_clicks', 'Clicks'), false)
                };

            case 'products_performance':
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('units_sold', 'Units Sold'),
                                data: timeSeries.map(d => parseFloat(d.units_sold) || 0),
                                backgroundColor: primaryColor + '80',
                                borderColor: primaryColor,
                                borderWidth: 1,
                                yAxisID: 'y',
                                order: 2,
                            },
                            {
                                label: t('revenue', 'Revenue'),
                                data: timeSeries.map(d => parseFloat(d.revenue) || 0),
                                type: 'line',
                                borderColor: successColor,
                                backgroundColor: successColor + '20',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                yAxisID: 'y1',
                                order: 1,
                            },
                        ]
                    },
                    options: buildChartOptions(t('units_sold', 'Units Sold'), t('revenue', 'Revenue'), true)
                };

            case 'returns_complaints':
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('total_returns', 'Returns'),
                                data: timeSeries.map(d => parseFloat(d.return_count) || 0),
                                backgroundColor: '#EF4444' + '80',
                                borderColor: '#EF4444',
                                borderWidth: 1,
                            },
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: {
                            y: { beginAtZero: true, position: CFG.dir === 'rtl' ? 'right' : 'left' }
                        }
                    }
                };

            case 'customer_behavior':
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('new_users', 'New Users'),
                                data: timeSeries.map(d => parseFloat(d.new_users) || 0),
                                backgroundColor: primaryColor + '80',
                                borderColor: primaryColor,
                                borderWidth: 1,
                            },
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top' } },
                        scales: {
                            y: { beginAtZero: true, position: CFG.dir === 'rtl' ? 'right' : 'left' }
                        }
                    }
                };

            case 'delivery_performance':
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('total_deliveries', 'Deliveries'),
                                data: timeSeries.map(d => parseFloat(d.delivery_count) || 0),
                                backgroundColor: primaryColor + '80',
                                borderColor: primaryColor,
                                borderWidth: 1,
                                yAxisID: 'y',
                                order: 2,
                            },
                            {
                                label: t('delivery_fees', 'Delivery Fees'),
                                data: timeSeries.map(d => parseFloat(d.delivery_fees) || 0),
                                type: 'line',
                                borderColor: successColor,
                                backgroundColor: successColor + '20',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                yAxisID: 'y1',
                                order: 1,
                            },
                        ]
                    },
                    options: buildChartOptions(t('total_deliveries', 'Deliveries'), t('delivery_fees', 'Delivery Fees'), true)
                };

            default:
                // Default: orders + revenue (for sales, orders, entities, platform)
                return {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [
                            {
                                label: t('orders', 'Orders'),
                                data: timeSeries.map(d => parseFloat(d.order_count) || 0),
                                backgroundColor: primaryColor + '80',
                                borderColor: primaryColor,
                                borderWidth: 1,
                                yAxisID: 'y',
                                order: 2,
                            },
                            {
                                label: t('revenue', 'Revenue'),
                                data: timeSeries.map(d => parseFloat(d.revenue) || 0),
                                type: 'line',
                                borderColor: successColor,
                                backgroundColor: successColor + '20',
                                borderWidth: 2,
                                fill: true,
                                tension: 0.3,
                                yAxisID: 'y1',
                                order: 1,
                            },
                        ]
                    },
                    options: buildChartOptions(t('orders', 'Orders'), t('revenue', 'Revenue'), true)
                };
        }
    }

    function buildChartOptions(leftLabel, rightLabel, rightIsCurrency) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            if (ctx.datasetIndex === 1 && rightIsCurrency) {
                                return ctx.dataset.label + ': ' + fmtCurrency(ctx.raw);
                            }
                            return ctx.dataset.label + ': ' + fmt(ctx.raw);
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    position: CFG.dir === 'rtl' ? 'right' : 'left',
                    title: { display: true, text: leftLabel },
                    beginAtZero: true,
                },
                y1: {
                    type: 'linear',
                    position: CFG.dir === 'rtl' ? 'left' : 'right',
                    title: { display: true, text: rightLabel },
                    beginAtZero: true,
                    grid: { drawOnChartArea: false },
                },
            }
        };
    }

    // ═══════════════════════════════════════════
    // RENDER TABLE
    // ═══════════════════════════════════════════
    function renderTable(type, metrics) {
        const thead = $('#prDataTableHead');
        const tbody = $('#prDataTableBody');
        if (!thead || !tbody) return;

        // Build table from metrics
        if (type === 'products_performance' && metrics.top_products) {
            thead.innerHTML = `<tr>
                <th>#</th>
                <th>${h(t('product_name'))}</th>
                <th>${h(t('quantity'))}</th>
                <th>${h(t('revenue'))}</th>
            </tr>`;
            tbody.innerHTML = metrics.top_products.map((p, i) =>
                `<tr>
                    <td>${i + 1}</td>
                    <td>${h(p.product_name || '-')}</td>
                    <td>${fmt(p.total_quantity)}</td>
                    <td>${fmtCurrency(p.total_revenue)}</td>
                </tr>`
            ).join('') || `<tr><td colspan="4">${h(t('no_data'))}</td></tr>`;
            return;
        }

        if (type === 'entities_performance' && metrics.top_entities) {
            thead.innerHTML = `<tr>
                <th>#</th>
                <th>${h(t('entity_name'))}</th>
                <th>${h(t('order_count'))}</th>
                <th>${h(t('revenue'))}</th>
            </tr>`;
            tbody.innerHTML = metrics.top_entities.map((e, i) =>
                `<tr>
                    <td>${i + 1}</td>
                    <td>${h(e.store_name || '-')}</td>
                    <td>${fmt(e.order_count)}</td>
                    <td>${fmtCurrency(e.total_revenue)}</td>
                </tr>`
            ).join('') || `<tr><td colspan="4">${h(t('no_data'))}</td></tr>`;
            return;
        }

        if (type === 'ads_performance' && metrics.top_ads) {
            thead.innerHTML = `<tr>
                <th>#</th>
                <th>${h(t('ad_type', 'Ad Type'))}</th>
                <th>${h(t('ad_target', 'Target'))}</th>
                <th>${h(t('total_impressions', 'Views'))}</th>
                <th>${h(t('total_clicks', 'Clicks'))}</th>
            </tr>`;
            tbody.innerHTML = metrics.top_ads.map((a, i) =>
                `<tr>
                    <td>${i + 1}</td>
                    <td>${h(a.ad_type || '-')}</td>
                    <td>${h(a.ad_target || '-')}</td>
                    <td>${fmt(a.total_views)}</td>
                    <td>${fmt(a.total_clicks)}</td>
                </tr>`
            ).join('') || `<tr><td colspan="5">${h(t('no_data'))}</td></tr>`;
            return;
        }

        // Default: show all metrics as key-value table
        const entries = Object.entries(metrics).filter(([k, v]) => typeof v !== 'object');
        thead.innerHTML = `<tr>
            <th>${h(t('metrics'))}</th>
            <th>${h(t('value'))}</th>
        </tr>`;
        tbody.innerHTML = entries.map(([k, v]) =>
            `<tr>
                <td>${h(t(k, k.replace(/_/g, ' ')))}</td>
                <td>${typeof v === 'number' ? fmt(v) : h(String(v))}</td>
            </tr>`
        ).join('') || `<tr><td colspan="2">${h(t('no_data'))}</td></tr>`;
    }

    // ═══════════════════════════════════════════
    // EXPORT
    // ═══════════════════════════════════════════
    async function requestExport(format) {
        if (!currentReportData) return;

        try {
            const resp = await apiPost('export', {
                report_type: currentReportData.report_type,
                start_date: currentReportData.period?.start,
                end_date: currentReportData.period?.end,
                tenant_id: currentReportData.tenant_id || '',
                export_format: format
            });

            if (resp.success) {
                alert(resp.data?.message || resp.message || 'Export requested successfully');
            } else {
                alert(resp.message || 'Export failed');
            }
        } catch (e) {
            console.error('Export failed:', e);
            alert('Export failed');
        }
    }

    // ═══════════════════════════════════════════
    // UI HELPERS
    // ═══════════════════════════════════════════
    function showLoading(show) {
        const el = $('#prLoading');
        if (el) el.style.display = show ? 'flex' : 'none';
    }

    function showResults(show) {
        const el = $('#prReportResults');
        const exp = $('#prExportSection');
        const noData = $('#prNoData');
        if (el) el.style.display = show ? 'block' : 'none';
        if (exp) exp.style.display = show ? 'flex' : 'none';
        if (noData) noData.style.display = 'none';
    }

    function hideResults() {
        showResults(false);
        const noData = $('#prNoData');
        if (noData) noData.style.display = 'none';
    }

    function showNoData(msg) {
        const el = $('#prNoData');
        if (el) {
            el.style.display = 'block';
            if (msg) el.querySelector('p').textContent = msg;
        }
        showResults(false);
    }

    // ═══════════════════════════════════════════
    // BOOTSTRAP (fragment + standalone + SPA)
    // ═══════════════════════════════════════════
    function bootstrap() {
        if ($('#platformReportApp')) {
            init();
        }
    }

    // For admin SPA navigation
    window.page = { run: bootstrap };

    // For fragment mode (AJAX load)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        // Already loaded (fragment)
        const checkReady = setInterval(function () {
            if ($('#platformReportApp')) {
                clearInterval(checkReady);
                bootstrap();
            }
        }, 100);
        // Cleanup after 10s
        setTimeout(function () { clearInterval(checkReady); }, 10000);
    }

})();
