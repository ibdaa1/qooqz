/**
 * /admin/assets/js/pages/CertificateManagement.js
 * Certificate Management — Audit workflow, payment verification, issuance & logs
 */
(function () {
    'use strict';

    const CFG = window.CERT_MGMT_CFG || {};

    /* ── Endpoints ─────────────────────────────────────────────────── */
    const API_REQ = CFG.apiRequests || '/api/certificates_requests';
    const API_AUDITS = CFG.apiAudits || '/api/certificates_audits';
    const API_PAY = CFG.apiPayments || '/api/certificates_payments';
    const API_ISSUED = CFG.apiIssued || '/api/certificates_issued';
    const API_LOGS = CFG.apiLogs || '/api/certificates_logs';
    const API_ITEMS = CFG.apiItems || '/api/certificates_request_items';
    const API_CERTS = CFG.apiCertificates || '/api/certificates';
    const API_ENT = CFG.apiEntities || '/api/entities';
    const API_PROD = CFG.apiProducts || '/api/certificates_products';
    const API_PT = CFG.apiProductsTrans || '/api/certificates_products_translations';
    const API_TU = CFG.apiTenantUsers || '/api/tenant_users';

    /* ── Low-level HTTP ─────────────────────────────────────────────── */
    const CSRF = () => window.CERT_MGMT_CFG?.csrfToken
        || document.querySelector('meta[name="csrf-token"]')?.content || '';

    async function http(method, url, body) {
        const opts = {
            method,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF() }
        };
        if (body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res = await fetch(url, opts);
        if (!res.ok) {
            let msg = `HTTP ${res.status}`;
            try { const d = await res.json(); msg = d.message || d.error || msg; } catch { }
            throw new Error(msg);
        }
        return res.json().catch(() => ({}));
    }
    const apiGet = url => http('GET', url);
    const apiPost = (url, body) => http('POST', url, body);
    const apiPut = (url, body) => http('PUT', url, body);   // AF only has PUT, no PATCH

    /* ── State ─────────────────────────────────────────────────────── */
    const S = {
        lang: window.USER_LANGUAGE || window.ADMIN_UI?.lang || 'ar',
        tenantId: window.APP_CONFIG?.TENANT_ID || 1,
        perms: CFG.perms || {},
        perPage: CFG.perPage || 25,
        tr: {},
        entities: [],
        certificates: [],
        products: [],
        tenantUsers: [],
        activeTab: 'requests',
        pages: { requests: 1, audits: 1, payments: 1, issued: 1, logs: 1 },
        filters: { requests: {}, audits: {}, payments: {}, issued: {}, logs: {} },
        currentAuditRequestId: null,
        currentPaymentId: null,
    };

    /* ── Helpers ───────────────────────────────────────────────────── */
    const esc = s => {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"]/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m]));
    };
    const q = id => document.getElementById(id);
    const sd = (el, v) => { if (el) el.textContent = (v ?? ''); };

    function buildUrl(base, params = {}) {
        const p = {};
        Object.entries(params).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '') p[k] = v; });
        const qs = new URLSearchParams(p).toString();
        return qs ? `${base}?${qs}` : base;
    }

    function pick(data) {
        if (!data) return [];
        if (Array.isArray(data)) return data;
        // { success, data: { items: [...], meta: {...} } }  ← main API envelope
        if (data.data && Array.isArray(data.data.items)) return data.data.items;
        // { success, data: [...] }
        if (Array.isArray(data.data)) return data.data;
        // { items: [...] } or { data: { items } } second level
        if (Array.isArray(data.items)) return data.items;
        // single object
        if (data?.id) return [data];
        return [];
    }

    function getMeta(data, items, page) {
        // { success, data: { items, meta: { total, page, per_page, ... } } }
        if (data?.data?.meta?.total !== undefined) return data.data.meta;
        // flat meta on response root
        if (data?.meta?.total !== undefined) return data.meta;
        if (data?.total !== undefined) return {
            total: data.total, page, per_page: S.perPage,
            from: (page - 1) * S.perPage + 1, to: Math.min(page * S.perPage, data.total),
            last_page: Math.ceil(data.total / S.perPage)
        };
        return {
            total: items.length, page, per_page: S.perPage,
            from: items.length ? (page - 1) * S.perPage + 1 : 0,
            to: Math.min(page * S.perPage, items.length),
            last_page: Math.ceil(items.length / S.perPage) || 1
        };
    }

    /* ── i18n ──────────────────────────────────────────────────────── */
    async function loadI18n() {
        try {
            const r = await fetch(`/languages/CertificateManagement/${encodeURIComponent(S.lang)}.json`, { credentials: 'same-origin' });
            if (!r.ok) throw new Error('not found');
            S.tr = await r.json();
        } catch (_) {
            if (S.lang !== 'en') {
                try {
                    const r2 = await fetch('/languages/CertificateManagement/en.json', { credentials: 'same-origin' });
                    if (r2.ok) S.tr = await r2.json();
                } catch (__) { }
            }
        }
        applyI18n();
    }

    function t(key, fallback) {
        const val = key.split('.').reduce((o, k) => (o && o[k] !== undefined ? o[k] : null), S.tr);
        return (val !== null && val !== undefined) ? String(val) : (fallback !== undefined ? fallback : key);
    }

    function applyI18n() {
        const root = q('certMgmtPage');
        if (!root) return;
        root.querySelectorAll('[data-i18n]').forEach(n => {
            const v = t(n.getAttribute('data-i18n'), '');
            if (v) n.textContent = v;
        });
        root.querySelectorAll('[data-i18n-placeholder]').forEach(n => {
            const v = t(n.getAttribute('data-i18n-placeholder'), '');
            if (v) n.placeholder = v;
        });
    }

    /* ── Toast ─────────────────────────────────────────────────────── */
    function toast(msg, ok = true) {
        const el = q('cmToast');
        if (!el) return;
        el.textContent = msg;
        el.className = `cm-toast ${ok ? 'cm-toast-ok' : 'cm-toast-err'}`;
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3500);
    }

    /* ── Lookups ───────────────────────────────────────────────────── */
    async function loadLookups() {
        await Promise.allSettled([
            loadEntities(), loadCertificates(), loadTenantUsers(), loadProducts()
        ]);
    }

    async function loadEntities() {
        try {
            const p = { limit: 1000, lang: S.lang };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            S.entities = pick(await apiGet(buildUrl(API_ENT, p)))
                .map(e => ({ id: e.id, name: e.store_name || e.name || `#${e.id}` }));
            populateEntityFilter();
        } catch (e) { console.warn('entities:', e.message); }
    }

    async function loadCertificates() {
        try { S.certificates = pick(await apiGet(buildUrl(API_CERTS, { limit: 200 }))); }
        catch (e) { console.warn('certs:', e.message); }
    }

    async function loadTenantUsers() {
        try {
            const p = { limit: 500 };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            S.tenantUsers = pick(await apiGet(buildUrl(API_TU, p)));
            populateAuditorFilter();
            populateAuditAssignSelect();
        } catch (e) { console.warn('tenant users:', e.message); }
    }

    async function loadProducts() {
        try {
            const rawP = pick(await apiGet(buildUrl(API_PROD, { limit: 2000 })));
            const rawT = pick(await apiGet(buildUrl(API_PT, { limit: 10000 })));
            const map = {};
            rawT.forEach(r => {
                if (!map[r.product_id]) map[r.product_id] = {};
                map[r.product_id][r.language_code] = r.name || '';
            });
            S.products = rawP.map(p => {
                const names = map[p.id] || {};
                const name = names[S.lang] || names['ar'] || names['en'] || Object.values(names)[0] || `#${p.id}`;
                return { id: p.id, name };
            });
        } catch (e) { console.warn('products:', e.message); }
    }

    const getEntityName = id => S.entities.find(e => e.id == id)?.name || `#${id}`;
    const getCertName = id => { const c = S.certificates.find(c => c.id == id); return c ? (c.code || c.description || `#${id}`) : `#${id}`; };
    const getProductName = id => S.products.find(p => p.id == id)?.name || `#${id}`;
    const getUserName = id => {
        const u = S.tenantUsers.find(u => u.id == id || u.user_id == id);
        return u ? (u.name || u.username || u.email || `#${id}`) : (id ? `#${id}` : '—');
    };

    function populateEntityFilter() {
        const s = q('reqEntityFilter'); if (!s) return;
        const cur = s.value;
        s.innerHTML = `<option value="">${t('filters.all_entities', 'All Entities')}</option>`;
        S.entities.forEach(e => { const o = document.createElement('option'); o.value = e.id; o.textContent = e.name; if (o.value == cur) o.selected = true; s.appendChild(o); });
    }

    function populateAuditorFilter() {
        const s = q('reqAuditorFilter'); if (!s) return;
        s.innerHTML = `<option value="">${t('filters.all', 'All')}</option>`;
        S.tenantUsers.forEach(u => { const o = document.createElement('option'); o.value = u.id || u.user_id; o.textContent = u.name || u.username || u.email || `#${o.value}`; s.appendChild(o); });
    }

    function populateAuditAssignSelect() {
        const s = q('auditAssignUser'); if (!s) return;
        s.innerHTML = `<option value="">${t('audit.form.no_change', '— No Change —')}</option>`;
        S.tenantUsers.forEach(u => { const o = document.createElement('option'); o.value = u.id || u.user_id; o.textContent = u.name || u.username || u.email || `#${o.value}`; s.appendChild(o); });
    }

    /* ── Badges ─────────────────────────────────────────────────────── */
    const STATUS_CLS = { draft: 'badge-draft', under_review: 'badge-under_review', payment_pending: 'badge-payment_pending', approved: 'badge-approved', rejected: 'badge-rejected', issued: 'badge-issued' };
    const PAY_CLS = { unpaid: 'badge-unpaid', waiting: 'badge-waiting', paid: 'badge-paid', rejected: 'badge-rejected' };

    const statusBadge = s => `<span class="badge ${STATUS_CLS[s] || ''}">${esc(t('status.' + s, s))}</span>`;
    const payBadge = s => s ? `<span class="badge ${PAY_CLS[s] || ''}">${esc(s)}</span>` : '—';
    const auditBadge = s => { const c = s === 'approved' ? 'badge-approved' : s === 'rejected' ? 'badge-rejected' : 'badge-under_review'; return `<span class="badge ${c}">${esc(t('audit.status.' + s, s))}</span>`; };
    const payVerifyBadge = s => { const c = s === 'verified' ? 'badge-approved' : s === 'rejected' ? 'badge-rejected' : 'badge-under_review'; return `<span class="badge ${c}">${esc(t('pay.status.' + s, s))}</span>`; };

    /* ══════════════════════════════════════════════════════════════════
       TAB: REQUESTS
    ══════════════════════════════════════════════════════════════════ */
    async function loadRequests(page = 1) {
        S.pages.requests = page;
        tabState('requests', 'loading');
        try {
            const f = S.filters.requests;
            const p = { page, limit: S.perPage, lang: S.lang };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            if (f.search) p.search = f.search;
            if (f.entity) p.entity_id = f.entity;
            if (f.status) p.status = f.status;
            if (f.auditor) p.auditor_user_id = f.auditor;
            if (f.tenant) p.tenant_id = f.tenant;

            const raw = await apiGet(buildUrl(API_REQ, p));
            const items = pick(raw);
            const meta = getMeta(raw, items, page);

            updateStatBadge('badgeRequests', meta.total);
            if (!items.length) { tabState('requests', 'empty'); return; }

            const isSA = S.perms.isSuperAdmin;
            q('reqTableBody').innerHTML = items.map(r => {
                const itemsNum = r.items_count ?? r.request_items_count ?? '—';
                return `<tr>
<td>${r.id}</td>
${isSA ? `<td>${esc(r.tenant_name || r.tenant_id || '')}</td>` : ''}
<td class="td-entity">${esc(getEntityName(r.entity_id))}</td>
<td>${esc(getCertName(r.certificate_id))}</td>
<td class="td-long">${esc(r.importer_name || '—')}</td>
<td class="td-center">${esc(String(itemsNum))}</td>
<td>${statusBadge(r.status)}</td>
<td>${payBadge(r.payment_status)}</td>
<td class="td-sm">${esc(getUserName(r.auditor_user_id))}</td>
<td class="td-sm">${r.created_at ? new Date(r.created_at).toLocaleDateString() : '—'}</td>
<td>
  <div class="td-actions">
    <button class="btn btn-sm btn-outline" onclick="CertificateManagement.viewDetail(${r.id})" title="${t('common.view', 'View')}"><i class="fas fa-eye"></i></button>
    ${S.perms.canAudit && r.status === 'under_review' ? `<button class="btn btn-sm btn-secondary" onclick="CertificateManagement.openAudit(${r.id})"><i class="fas fa-search"></i></button>` : ''}
  </div>
</td>
</tr>`;
            }).join('');

            tabState('requests', 'table');
            renderPager('req', meta, loadRequests);
            updateStats(items);
        } catch (e) { console.error(e); tabState('requests', 'error'); }
    }

    function updateStats(items) {
        sd(q('statPendingNum'), items.filter(r => r.status === 'under_review').length);
        sd(q('statPaymentNum'), items.filter(r => r.status === 'payment_pending').length);
        sd(q('statApprovedNum'), items.filter(r => r.status === 'approved').length);
        sd(q('statIssuedNum'), items.filter(r => r.status === 'issued').length);
    }

    /* ══════════════════════════════════════════════════════════════════
       TAB: AUDITS
    ══════════════════════════════════════════════════════════════════ */
    async function loadAudits(page = 1) {
        S.pages.audits = page;
        tabState('audits', 'loading');
        try {
            const f = S.filters.audits;
            const p = { page, limit: S.perPage };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            if (f.request_id) p.request_id = f.request_id;
            if (f.status) p.status = f.status;

            const raw = await apiGet(buildUrl(API_AUDITS, p));
            const items = pick(raw);
            const meta = getMeta(raw, items, page);

            updateStatBadge('badgeAudits', meta.total);
            if (!items.length) { tabState('audits', 'empty'); return; }

            q('auditTableBody').innerHTML = items.map(a => `<tr>
<td>${a.id}</td>
<td><a href="#" onclick="CertificateManagement.viewDetail(${a.request_id});return false;">#${a.request_id}</a></td>
<td class="td-sm">—</td>
<td class="td-sm">${esc(getUserName(a.auditor_id))}</td>
<td>${auditBadge(a.status)}</td>
<td class="td-sm">${a.audit_date ? new Date(a.audit_date).toLocaleString() : '—'}</td>
<td class="td-notes">${esc(a.notes || '')}</td>
<td>
  <div class="td-actions">
    <button class="btn btn-sm btn-secondary" onclick="CertificateManagement.openAudit(${a.request_id})"><i class="fas fa-search"></i></button>
  </div>
</td>
</tr>`).join('');

            tabState('audits', 'table');
            renderPager('audit', meta, loadAudits);
        } catch (e) { console.error(e); tabState('audits', 'error'); }
    }

    /* ══════════════════════════════════════════════════════════════════
       TAB: PAYMENTS
    ══════════════════════════════════════════════════════════════════ */
    async function loadPayments(page = 1) {
        S.pages.payments = page;
        tabState('payments', 'loading');
        try {
            const f = S.filters.payments;
            const p = { page, limit: S.perPage };
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;
            if (f.request_id) p.request_id = f.request_id;
            if (f.verify_status) p.verification_status = f.verify_status;

            const raw = await apiGet(buildUrl(API_PAY, p));
            const items = pick(raw);
            const meta = getMeta(raw, items, page);

            const waiting = items.filter(i => i.verification_status === 'waiting_verification').length;
            updateStatBadge('badgePayments', waiting || null);
            if (!items.length) { tabState('payments', 'empty'); return; }

            q('payTableBody').innerHTML = items.map(py => {
                const canVerify = S.perms.canVerifyPay && py.verification_status === 'waiting_verification';
                return `<tr>
<td>${py.id}</td>
<td><a href="#" onclick="CertificateManagement.viewDetail(${py.request_id});return false;">#${py.request_id}</a></td>
<td class="td-sm">${esc(py.payment_type || '')}</td>
<td class="td-sm">${esc(py.amount || '0')} ${esc(py.currency || '')}</td>
<td class="td-sm">${esc(py.payment_reference || '—')}</td>
<td class="td-sm">${py.payment_date ? new Date(py.payment_date).toLocaleDateString() : '—'}</td>
<td>${py.receipt_file ? `<a href="${esc(py.receipt_file)}" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-file"></i></a>` : '—'}</td>
<td>${payVerifyBadge(py.verification_status)}</td>
<td>
  <div class="td-actions">
    ${canVerify ? `<button class="btn btn-sm btn-success" onclick="CertificateManagement.openPaymentVerify(${py.id})"><i class="fas fa-check-circle"></i></button>` : ''}
  </div>
</td>
</tr>`;
            }).join('');

            tabState('payments', 'table');
            renderPager('pay', meta, loadPayments);
        } catch (e) { console.error(e); tabState('payments', 'error'); }
    }

    /* ══════════════════════════════════════════════════════════════════
       TAB: ISSUED
    ══════════════════════════════════════════════════════════════════ */
    async function loadIssued(page = 1) {
        S.pages.issued = page;
        tabState('issued', 'loading');
        try {
            const f = S.filters.issued;
            const p = { page, limit: S.perPage };
            if (f.search) p.search = f.search;
            if (f.cancelled !== undefined && f.cancelled !== '') p.is_cancelled = f.cancelled;

            const raw = await apiGet(buildUrl(API_ISSUED, p));
            const items = pick(raw);
            const meta = getMeta(raw, items, page);
            if (!items.length) { tabState('issued', 'empty'); return; }

            q('issuedTableBody').innerHTML = items.map(ci => `<tr>
<td>${ci.id}</td>
<td><strong>${esc(ci.certificate_number || '—')}</strong></td>
<td class="td-sm">#${ci.version_id || '—'}</td>
<td class="td-sm">${ci.issued_at ? new Date(ci.issued_at).toLocaleString() : '—'}</td>
<td class="td-sm">${ci.printable_until ? new Date(ci.printable_until).toLocaleString() : '—'}</td>
<td class="td-sm">${esc(getUserName(ci.issued_by))}</td>
<td class="td-sm">${esc(ci.language_code || '—')}</td>
<td>${ci.is_cancelled ? `<span class="badge badge-inactive">${t('filters.cancelled', 'Cancelled')}</span>` : `<span class="badge badge-active">${t('filters.active', 'Active')}</span>`}</td>
<td>
  <div class="td-actions">
    ${ci.pdf_path ? `<a href="${esc(ci.pdf_path)}" target="_blank" class="btn btn-sm btn-primary"><i class="fas fa-file-pdf"></i></a>` : ''}
    ${ci.qr_code_path ? `<a href="${esc(ci.qr_code_path)}" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-qrcode"></i></a>` : ''}
  </div>
</td>
</tr>`).join('');

            tabState('issued', 'table');
            renderPager('issued', meta, loadIssued);
        } catch (e) { console.error(e); tabState('issued', 'error'); }
    }

    /* ══════════════════════════════════════════════════════════════════
       TAB: LOGS
    ══════════════════════════════════════════════════════════════════ */
    async function loadLogs(page = 1) {
        S.pages.logs = page;
        tabState('logs', 'loading');
        try {
            const f = S.filters.logs;
            const p = { page, limit: S.perPage };
            if (f.request_id) p.request_id = f.request_id;
            if (f.action) p.action_type = f.action;
            if (!S.perms.isSuperAdmin) p.tenant_id = S.tenantId;

            const raw = await apiGet(buildUrl(API_LOGS, p));
            const items = pick(raw);
            const meta = getMeta(raw, items, page);
            if (!items.length) { tabState('logs', 'empty'); return; }

            const ACTION_ICON = {
                create: 'plus-circle', update: 'edit', approve: 'check-circle',
                audit: 'search', payment_sent: 'credit-card', issue: 'stamp', reject: 'times-circle'
            };

            q('logTableBody').innerHTML = items.map(l => `<tr>
<td>${l.id}</td>
<td><a href="#" onclick="CertificateManagement.viewDetail(${l.request_id});return false;">#${l.request_id}</a></td>
<td class="td-sm">${esc(getUserName(l.user_id))}</td>
<td><span class="cm-action-badge cm-action-${esc(l.action_type)}"><i class="fas fa-${ACTION_ICON[l.action_type] || 'info-circle'}"></i> ${esc(l.action_type || '')}</span></td>
<td class="td-notes">${esc(l.notes || '')}</td>
<td class="td-sm">${l.created_at ? new Date(l.created_at).toLocaleString() : '—'}</td>
</tr>`).join('');

            tabState('logs', 'table');
            renderPager('log', meta, loadLogs);
        } catch (e) { console.error(e); tabState('logs', 'error'); }
    }

    /* ══════════════════════════════════════════════════════════════════
       MODAL: REQUEST DETAIL
    ══════════════════════════════════════════════════════════════════ */
    async function viewDetail(id) {
        const modal = q('modalDetail'); if (!modal) return;
        const body = q('detailModalBody');
        body.innerHTML = '<div class="loading-state"><div class="spinner"></div></div>';
        modal.style.display = 'flex';
        sd(q('detailModalTitle'), `${t('detail.title', 'Request Details')} #${id}`);

        try {
            const [raw, itemsRaw, auditRaw, payRaw] = await Promise.all([
                apiGet(buildUrl(API_REQ, { id, lang: S.lang })),
                apiGet(buildUrl(API_ITEMS, { request_id: id, limit: 200 })),
                apiGet(buildUrl(API_AUDITS, { request_id: id, limit: 1 })),
                apiGet(buildUrl(API_PAY, { request_id: id, limit: 10 })),
            ]);

            const req = pick(raw)[0] || (raw?.data?.id ? raw.data : raw?.id ? raw : null);
            if (!req) throw new Error('Not found');
            const items = pick(itemsRaw);
            const a = pick(auditRaw)[0];
            const pay = pick(payRaw)[0];

            const auditBtn = q('btnAuditFromDetail');
            if (auditBtn) {
                auditBtn.style.display = (S.perms.canAudit && req.status === 'under_review') ? '' : 'none';
                auditBtn.onclick = () => { closeModal('modalDetail'); openAudit(id); };
            }

            const itemsHtml = items.length ? `
<table class="cm-detail-table"><thead><tr>
  <th>#</th><th>${t('req_table.product', 'Product')}</th>
  <th>${t('detail.qty', 'Qty')}</th><th>${t('detail.weight', 'Net Wt')}</th>
  <th>${t('detail.prod_date', 'Prod Date')}</th><th>${t('detail.exp_date', 'Exp Date')}</th>
</tr></thead><tbody>
${items.map((it, i) => `<tr><td>${i + 1}</td><td>${esc(getProductName(it.product_id))}</td>
  <td>${esc(String(it.quantity || '—'))}</td><td>${esc(String(it.net_weight || '—'))}</td>
  <td>${it.production_date || '—'}</td><td>${it.expiry_date || '—'}</td></tr>`).join('')}
</tbody></table>` : `<p class="cm-no-items">${t('detail.no_items', 'No items')}</p>`;

            body.innerHTML = `
<div class="cm-detail-grid">
  <div class="cm-detail-block">
    <div class="cm-detail-row"><span>${t('req_table.entity', 'Entity')}</span><strong>${esc(getEntityName(req.entity_id))}</strong></div>
    <div class="cm-detail-row"><span>${t('req_table.certificate', 'Certificate')}</span><strong>${esc(getCertName(req.certificate_id))}</strong></div>
    <div class="cm-detail-row"><span>${t('req_table.status', 'Status')}</span>${statusBadge(req.status)}</div>
    <div class="cm-detail-row"><span>${t('req_table.payment', 'Payment')}</span>${payBadge(req.payment_status)}</div>
    <div class="cm-detail-row"><span>${t('req_table.auditor', 'Auditor')}</span><span>${esc(getUserName(req.auditor_user_id))}</span></div>
  </div>
  <div class="cm-detail-block">
    <div class="cm-detail-row"><span>${t('detail.importer_name', 'Importer')}</span><strong>${esc(req.importer_name || '—')}</strong></div>
    <div class="cm-detail-row"><span>${t('detail.importer_addr', 'Address')}</span><span>${esc(req.importer_address || '—')}</span></div>
    <div class="cm-detail-row"><span>${t('detail.transport', 'Transport')}</span><span>${esc(req.transport_method || '—')}</span></div>
    <div class="cm-detail-row"><span>${t('detail.gcc', 'GCC')}</span><span>${esc(req.certificate_type || '—')}</span></div>
    <div class="cm-detail-row"><span>${t('detail.operation', 'Operation')}</span><span>${esc(req.operation_type || '—')}</span></div>
  </div>
</div>
${a ? `<div class="cm-detail-audit">
  <div class="cm-section-label"><i class="fas fa-search"></i> ${t('tabs.audits', 'Last Audit')}</div>
  <div class="cm-detail-row"><span>${t('audit_table.status', 'Status')}</span>${auditBadge(a.status)}</div>
  <div class="cm-detail-row"><span>${t('audit_table.audit_date', 'Date')}</span><span>${a.audit_date ? new Date(a.audit_date).toLocaleString() : '—'}</span></div>
  <div class="cm-detail-row"><span>${t('audit_table.notes', 'Notes')}</span><span>${esc(a.notes || '—')}</span></div>
</div>` : ''}
${pay ? `<div class="cm-detail-payment">
  <div class="cm-section-label"><i class="fas fa-credit-card"></i> ${t('tabs.payments', 'Payment')}</div>
  <div class="cm-detail-row"><span>${t('pay_table.amount', 'Amount')}</span><strong>${esc(String(pay.amount))} ${esc(pay.currency)}</strong></div>
  <div class="cm-detail-row"><span>${t('pay_table.status', 'Status')}</span>${payVerifyBadge(pay.verification_status)}</div>
  <div class="cm-detail-row"><span>${t('pay_table.reference', 'Reference')}</span><span>${esc(pay.payment_reference || '—')}</span></div>
</div>` : ''}
<div class="cm-section-label"><i class="fas fa-boxes"></i> ${t('tabs.items', 'Items')}</div>
${itemsHtml}`;

        } catch (e) {
            body.innerHTML = `<div class="error-state"><i class="fas fa-exclamation-triangle"></i> ${esc(e.message)}</div>`;
        }
    }

    /* ══════════════════════════════════════════════════════════════════
       MODAL: AUDIT
    ══════════════════════════════════════════════════════════════════ */
    async function openAudit(requestId) {
        const modal = q('modalAudit'); if (!modal) return;
        S.currentAuditRequestId = requestId;

        const dtInput = q('auditFormDate');
        if (dtInput) dtInput.value = new Date().toISOString().slice(0, 16);
        const sf = q('auditFormStatus'); if (sf) sf.value = 'approved';
        const nf = q('auditFormNotes'); if (nf) nf.value = '';
        q('auditFormRequestId').value = requestId;
        q('auditFormId').value = '';

        try {
            const raw = await apiGet(buildUrl(API_REQ, { id: requestId, lang: S.lang }));
            const req = pick(raw)[0] || (raw?.id ? raw : null);
            if (req) {
                q('auditRequestSummary').innerHTML = `
<div class="cm-summary-row"><i class="fas fa-building"></i> <strong>${esc(getEntityName(req.entity_id))}</strong></div>
<div class="cm-summary-row"><i class="fas fa-certificate"></i> ${esc(getCertName(req.certificate_id))}</div>
<div class="cm-summary-row"><i class="fas fa-user-tie"></i> ${esc(req.importer_name || '—')}</div>
<div class="cm-summary-row">${statusBadge(req.status)} ${payBadge(req.payment_status)}</div>`;
                const as = q('auditAssignUser');
                if (as && req.auditor_user_id) as.value = req.auditor_user_id;
            }

            const items = pick(await apiGet(buildUrl(API_ITEMS, { request_id: requestId, limit: 200 })));
            q('auditItemsList').innerHTML = items.length
                ? `<table class="cm-detail-table"><thead><tr><th>#</th><th>${t('req_table.product', 'Product')}</th><th>${t('detail.qty', 'Qty')}</th><th>${t('detail.weight', 'Net Wt')}</th></tr></thead>
<tbody>${items.map((it, i) => `<tr><td>${i + 1}</td><td>${esc(getProductName(it.product_id))}</td><td>${esc(String(it.quantity || '—'))}</td><td>${esc(String(it.net_weight || '—'))}</td></tr>`).join('')}</tbody></table>`
                : `<p class="cm-no-items">${t('detail.no_items', 'No items')}</p>`;

            const audits = pick(await apiGet(buildUrl(API_AUDITS, { request_id: requestId, limit: 1 })));
            if (audits[0]) {
                q('auditFormId').value = audits[0].id;
                if (sf) sf.value = audits[0].status || 'approved';
                if (nf) nf.value = audits[0].notes || '';
                if (dtInput && audits[0].audit_date) dtInput.value = audits[0].audit_date.slice(0, 16);
            }
        } catch (e) { console.warn('openAudit load:', e.message); }

        modal.style.display = 'flex';
    }

    async function submitAudit() {
        const btn = q('btnSubmitAudit');
        const reqId = S.currentAuditRequestId;
        const auditId = parseInt(q('auditFormId')?.value || '0');
        const status = q('auditFormStatus')?.value;
        const date = q('auditFormDate')?.value;
        const notes = q('auditFormNotes')?.value || '';
        const assign = q('auditAssignUser')?.value;

        if (!status || !date) { toast(t('common.validation', 'Fill required fields'), false); return; }
        if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i>`; }

        try {
            const auditPayload = {
                request_id: reqId,
                auditor_id: window.APP_CONFIG?.USER_ID || 0, audit_date: date, status, notes
            };

            if (auditId) { await apiPut(`${API_AUDITS}/${auditId}`, auditPayload); }
            else { await apiPost(API_AUDITS, auditPayload); }

            const newStatus = status === 'approved' ? 'payment_pending' : 'rejected';
            const reqPatch = { status: newStatus };
            if (assign) reqPatch.auditor_user_id = assign;
            await apiPut(`${API_REQ}/${reqId}`, reqPatch);

            closeModal('modalAudit');
            toast(t('audit.success', 'Audit submitted successfully'));
            loadRequests(S.pages.requests);
            if (S.activeTab === 'audits') loadAudits(S.pages.audits);
        } catch (e) {
            toast(t('common.error', 'Error: ') + e.message, false);
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fas fa-check"></i> ${t('audit.form.submit', 'Submit Audit')}`; }
        }
    }

    /* ══════════════════════════════════════════════════════════════════
       MODAL: PAYMENT VERIFY
    ══════════════════════════════════════════════════════════════════ */
    async function openPaymentVerify(payId) {
        const modal = q('modalPayment'); if (!modal) return;
        S.currentPaymentId = payId;
        q('payFormId').value = payId;
        const sf = q('payFormStatus'); if (sf) sf.value = 'verified';
        const nf = q('payFormNotes'); if (nf) nf.value = '';

        try {
            const raw = await apiGet(`${API_PAY}/${payId}`);
            const py = pick(raw)[0] || (raw?.id ? raw : null);
            if (py) {
                q('paymentDetails').innerHTML = `
<div class="cm-detail-grid">
  <div class="cm-detail-row"><span>${t('pay_table.request_id', 'Request')}</span><strong>#${esc(String(py.request_id))}</strong></div>
  <div class="cm-detail-row"><span>${t('pay_table.amount', 'Amount')}</span><strong>${esc(String(py.amount))} ${esc(py.currency)}</strong></div>
  <div class="cm-detail-row"><span>${t('pay_table.reference', 'Reference')}</span><span>${esc(py.payment_reference || '—')}</span></div>
  <div class="cm-detail-row"><span>${t('pay_table.date', 'Date')}</span><span>${py.payment_date ? new Date(py.payment_date).toLocaleDateString() : '—'}</span></div>
  <div class="cm-detail-row"><span>${t('pay_table.type', 'Type')}</span><span>${esc(py.payment_type || '—')}</span></div>
  ${py.receipt_file ? `<div class="cm-detail-row"><span>${t('pay_table.receipt', 'Receipt')}</span><a href="${esc(py.receipt_file)}" target="_blank" class="btn btn-sm btn-outline"><i class="fas fa-file-alt"></i> View</a></div>` : ''}
</div>`;
            }
        } catch (e) { console.warn('openPaymentVerify:', e.message); }

        modal.style.display = 'flex';
    }

    async function submitPaymentVerify() {
        const btn = q('btnSubmitPay');
        const payId = S.currentPaymentId;
        const status = q('payFormStatus')?.value;
        const notes = q('payFormNotes')?.value || '';

        if (!status) { toast(t('common.validation', 'Fill required fields'), false); return; }
        if (btn) { btn.disabled = true; btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i>`; }

        try {
            await apiPut(`${API_PAY}/${payId}`, {
                verification_status: status,
                verified_by: window.APP_CONFIG?.USER_ID || 0,
                verified_at: new Date().toISOString().slice(0, 19).replace('T', ' '),
                notes,
            });
            closeModal('modalPayment');
            toast(t('pay.success', 'Payment verification updated'));
            if (S.activeTab === 'payments') loadPayments(S.pages.payments);
            loadRequests(S.pages.requests);
        } catch (e) {
            toast(t('common.error', 'Error: ') + e.message, false);
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = `<i class="fas fa-check-circle"></i> ${t('pay.form.submit', 'Confirm')}`; }
        }
    }

    /* ── Modals ─────────────────────────────────────────────────────── */
    function closeModal(id) { const m = q(id); if (m) m.style.display = 'none'; }

    function tabState(tab, state) {
        const prefix = { requests: 'req', audits: 'audit', payments: 'pay', issued: 'issued', logs: 'log' }[tab];
        if (!prefix) return;
        ['Loading', 'Table', 'Empty'].forEach(suf => {
            const el = q(`${prefix}${suf}`);
            if (el) el.style.display = (
                suf === 'Loading' && state === 'loading' ? 'flex' :
                    suf === 'Table' && state === 'table' ? 'block' :
                        suf === 'Empty' && state === 'empty' ? 'flex' : 'none');
        });
    }

    function renderPager(prefix, meta, loadFn) {
        const info = q(`${prefix}PaginationInfo`);
        const pager = q(`${prefix}Pagination`);
        if (info) sd(info, `${meta.from || 0}–${meta.to || 0} / ${meta.total || 0}`);
        if (pager) {
            // build simple prev/next pagination
            const pages = meta.last_page || Math.ceil(meta.total / S.perPage) || 1;
            const page = meta.page || 1;
            let html = '';
            if (page > 1) html += `<button class="pagination-btn" onclick="(${loadFn.name}||CertificateManagement._tabLoad('${prefix}'))(${page - 1})"><i class="fas fa-chevron-left"></i></button>`;
            html += `<span class="pagination-btn active">${page} / ${pages}</span>`;
            if (page < pages) html += `<button class="pagination-btn" onclick="(${loadFn.name}||CertificateManagement._tabLoad('${prefix}'))(${page + 1})"><i class="fas fa-chevron-right"></i></button>`;
            pager.innerHTML = html;
        }
    }

    function updateStatBadge(id, n) {
        const el = q(id); if (!el) return;
        el.textContent = n ? String(n) : ''; el.style.display = n ? '' : 'none';
    }

    /* ── Tab switching ──────────────────────────────────────────────── */
    function switchTab(name) {
        S.activeTab = name;
        document.querySelectorAll('.cm-tab').forEach(bt => bt.classList.toggle('active', bt.dataset.tab === name));
        document.querySelectorAll('.cm-tab-panel').forEach(p => p.style.display = 'none');
        const panel = q(`panel${name.charAt(0).toUpperCase() + name.slice(1)}`);
        if (panel) panel.style.display = '';
        const loaders = { requests: loadRequests, audits: loadAudits, payments: loadPayments, issued: loadIssued, logs: loadLogs };
        if (loaders[name]) loaders[name](1);
    }

    /* ── Bind events ────────────────────────────────────────────────── */
    function bindEvents() {
        document.querySelectorAll('.cm-tab').forEach(btn => btn.addEventListener('click', () => switchTab(btn.dataset.tab)));

        const filterBind = (applyId, resetId, filterKeys, filterState, loadFn) => {
            q(applyId)?.addEventListener('click', () => {
                const f = {};
                Object.entries(filterKeys).forEach(([k, id]) => { const el = q(id); if (el) f[k] = el.value; });
                S.filters[filterState] = f; loadFn(1);
            });
            q(resetId)?.addEventListener('click', () => {
                Object.values(filterKeys).forEach(id => { const el = q(id); if (el) el.value = ''; });
                S.filters[filterState] = {}; loadFn(1);
            });
        };

        filterBind('btnReqFilter', 'btnReqReset', { search: 'reqSearch', entity: 'reqEntityFilter', status: 'reqStatusFilter', auditor: 'reqAuditorFilter', tenant: 'reqTenantFilter' }, 'requests', loadRequests);
        filterBind('btnAuditFilter', 'btnAuditReset', { request_id: 'auditReqIdFilter', status: 'auditStatusFilter' }, 'audits', loadAudits);
        filterBind('btnPayFilter', 'btnPayReset', { request_id: 'payReqIdFilter', verify_status: 'payStatusFilter' }, 'payments', loadPayments);
        filterBind('btnIssuedFilter', 'btnIssuedReset', { search: 'issuedSearchFilter', cancelled: 'issuedCancelledFilter' }, 'issued', loadIssued);
        filterBind('btnLogFilter', 'btnLogReset', { request_id: 'logReqIdFilter', action: 'logActionFilter' }, 'logs', loadLogs);

        q('reqSearch')?.addEventListener('keydown', e => { if (e.key === 'Enter') q('btnReqFilter')?.click(); });

        q('btnSubmitAudit')?.addEventListener('click', submitAudit);
        q('btnCancelAudit')?.addEventListener('click', () => closeModal('modalAudit'));
        q('btnCloseAuditModal')?.addEventListener('click', () => closeModal('modalAudit'));
        q('btnSubmitPay')?.addEventListener('click', submitPaymentVerify);
        q('btnCancelPay')?.addEventListener('click', () => closeModal('modalPayment'));
        q('btnClosePayModal')?.addEventListener('click', () => closeModal('modalPayment'));
        q('btnCloseDetail')?.addEventListener('click', () => closeModal('modalDetail'));
        q('btnCloseDetailFooter')?.addEventListener('click', () => closeModal('modalDetail'));

        ['modalAudit', 'modalPayment', 'modalDetail'].forEach(id => {
            const m = q(id);
            if (m) m.addEventListener('click', e => { if (e.target === m) closeModal(id); });
        });
    }

    /* ── Init ───────────────────────────────────────────────────────── */
    async function init() {
        await loadI18n();
        await loadLookups();
        bindEvents();
        await loadRequests(1);
    }

    /* ── Public ─────────────────────────────────────────────────────── */
    window.CertificateManagement = { init, switchTab, viewDetail, openAudit, openPaymentVerify };

    // Named functions for pagination inline onclick
    window.loadRequests = loadRequests;
    window.loadAudits = loadAudits;
    window.loadPayments = loadPayments;
    window.loadIssued = loadIssued;
    window.loadLogs = loadLogs;

})();
