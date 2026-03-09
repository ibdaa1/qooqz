(function () {
  'use strict';

  const CONFIG = window.ORDERS_CONFIG || {};

  const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
  const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded', 'partial'];

  const STATUS_COLORS = {
    pending: 'warning', confirmed: 'info', processing: 'info',
    shipped: 'primary', delivered: 'success', cancelled: 'danger', refunded: 'secondary',
  };
  const PAYMENT_COLORS = {
    pending: 'warning', paid: 'success', failed: 'danger',
    refunded: 'secondary', partial: 'info',
  };

  const state = {
    page: 1,
    perPage: 25,
    total: 0,
    items: [],
    filters: { search: '', status: '', payment_status: '', date_from: '', date_to: '' },
    current: null,
  };

  /* ── Utilities ─────────────────────────────────────────── */
  function toast(msg, type = 'success') {
    const c = document.getElementById('toastContainer') || createToastContainer();
    const t = document.createElement('div');
    t.className = 'toast toast-' + type;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3500);
  }
  function createToastContainer() {
    const c = document.createElement('div');
    c.id = 'toastContainer';
    c.className = 'toast-container';
    document.body.appendChild(c);
    return c;
  }
  function csrf() {
    return CONFIG.csrfToken || document.querySelector('[name=csrf_token]')?.value || '';
  }
  function apiUrl(path) {
    return (CONFIG.apiUrl || '/api/orders') + (path || '');
  }
  async function apiFetch(url, opts = {}) {
    const headers = {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrf(),
      'X-Requested-With': 'XMLHttpRequest',
      ...opts.headers,
    };
    const res = await fetch(url, { ...opts, headers });
    if (!res.ok) {
      const e = await res.json().catch(() => ({ message: res.statusText }));
      throw new Error(e.message || res.statusText);
    }
    return res.json();
  }
  function escHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function fmtDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
  }
  function fmtCurrency(v) {
    return Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function statusBadge(s, colorMap) {
    const color = colorMap[s] || 'secondary';
    return `<span class="badge badge-${color}">${escHtml(s || '—')}</span>`;
  }
  function setLoading(on) {
    const el = document.getElementById('ordersTableBody');
    if (el) el.style.opacity = on ? '0.5' : '1';
  }
  function showModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('is-active'); m.style.display = 'flex'; }
  }
  function hideModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('is-active'); m.style.display = 'none'; }
  }

  /* ── Module ─────────────────────────────────────────────── */
  const OrdersModule = {
    init() {
      OrdersModule._injectHTML();
      OrdersModule._bindEvents();
      OrdersModule.load();
    },

    _injectHTML() {
      const container = document.getElementById('ordersContainer');
      if (!container) return;
      const statusOpts = ORDER_STATUSES.map(s => `<option value="${s}">${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('');
      const payOpts = PAYMENT_STATUSES.map(s => `<option value="${s}">${s.charAt(0).toUpperCase() + s.slice(1)}</option>`).join('');

      container.innerHTML = `
        <div class="page-header">
          <h2>Orders</h2>
        </div>
        <div class="filters-bar" style="flex-wrap:wrap;gap:8px">
          <input type="text" id="orderSearch" class="form-control" placeholder="Order # or customer…" style="max-width:220px">
          <select id="orderStatusFilter" class="form-control" style="max-width:160px">
            <option value="">All Statuses</option>
            ${statusOpts}
          </select>
          <select id="orderPaymentFilter" class="form-control" style="max-width:160px">
            <option value="">All Payments</option>
            ${payOpts}
          </select>
          <input type="date" id="orderDateFrom" class="form-control" style="max-width:150px" title="From date">
          <input type="date" id="orderDateTo" class="form-control" style="max-width:150px" title="To date">
          <button id="orderSearchBtn" class="btn btn-secondary">Filter</button>
        </div>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Total</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="ordersTableBody"><tr><td colspan="7">Loading…</td></tr></tbody>
          </table>
        </div>
        <div id="ordersPagination" class="pagination-bar"></div>

        <!-- View/Edit Modal -->
        <div id="orderModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="orderModalBackdrop"></div>
          <div class="modal-box modal-lg">
            <div class="modal-header">
              <h3 id="orderModalTitle">Order Details</h3>
              <button class="modal-close" id="orderModalClose">&times;</button>
            </div>
            <div class="modal-body" id="orderModalBody">Loading…</div>
            <div class="modal-footer">
              <div style="display:flex;gap:12px;align-items:center;flex:1">
                <label style="margin:0;white-space:nowrap">Update Status:</label>
                <select id="orderStatusSelect" class="form-control" style="max-width:180px">${statusOpts}</select>
                <button id="orderStatusUpdateBtn" class="btn btn-primary">Update Status</button>
              </div>
              <button id="orderModalCloseBtn" class="btn btn-secondary">Close</button>
            </div>
          </div>
        </div>`;
    },

    _bindEvents() {
      document.addEventListener('click', function (e) {
        if (e.target.id === 'orderSearchBtn') OrdersModule._applyFilters();
        if (e.target.id === 'orderModalClose' || e.target.id === 'orderModalCloseBtn' || e.target.id === 'orderModalBackdrop') hideModal('orderModal');
        if (e.target.matches('.order-view-btn')) OrdersModule.openView(e.target.dataset.id);
        if (e.target.id === 'orderStatusUpdateBtn') OrdersModule.updateStatus();
        if (e.target.matches('.orders-page-btn')) {
          state.page = parseInt(e.target.dataset.page, 10);
          OrdersModule.load();
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.id === 'orderSearch') OrdersModule._applyFilters();
      });
    },

    _applyFilters() {
      state.filters.search = (document.getElementById('orderSearch') || {}).value || '';
      state.filters.status = (document.getElementById('orderStatusFilter') || {}).value || '';
      state.filters.payment_status = (document.getElementById('orderPaymentFilter') || {}).value || '';
      state.filters.date_from = (document.getElementById('orderDateFrom') || {}).value || '';
      state.filters.date_to = (document.getElementById('orderDateTo') || {}).value || '';
      state.page = 1;
      OrdersModule.load();
    },

    async load() {
      setLoading(true);
      try {
        const params = new URLSearchParams({ page: state.page, per_page: state.perPage });
        if (state.filters.search) params.set('search', state.filters.search);
        if (state.filters.status) params.set('status', state.filters.status);
        if (state.filters.payment_status) params.set('payment_status', state.filters.payment_status);
        if (state.filters.date_from) params.set('date_from', state.filters.date_from);
        if (state.filters.date_to) params.set('date_to', state.filters.date_to);
        const data = await apiFetch(`${apiUrl()}?${params}`);
        state.items = data.items || data.data || data || [];
        state.total = data.total || state.items.length;
        OrdersModule.renderTable(state.items);
        OrdersModule.renderPagination();
      } catch (err) {
        toast(err.message, 'error');
        const tb = document.getElementById('ordersTableBody');
        if (tb) tb.innerHTML = `<tr><td colspan="7" class="text-danger">${err.message}</td></tr>`;
      } finally {
        setLoading(false);
      }
    },

    renderTable(items) {
      const tb = document.getElementById('ordersTableBody');
      if (!tb) return;
      if (!items.length) {
        tb.innerHTML = '<tr><td colspan="7" class="text-center">No orders found.</td></tr>';
        return;
      }
      tb.innerHTML = items.map(o => `<tr>
        <td><strong>#${escHtml(o.order_number || String(o.id))}</strong></td>
        <td>
          <div>${escHtml(o.customer_name || '—')}</div>
          <small class="text-muted">${escHtml(o.customer_email || '')}</small>
        </td>
        <td><strong>${fmtCurrency(o.total_amount)}</strong></td>
        <td>${statusBadge(o.status, STATUS_COLORS)}</td>
        <td>${statusBadge(o.payment_status, PAYMENT_COLORS)}</td>
        <td>${fmtDate(o.created_at)}</td>
        <td class="actions">
          <button class="btn btn-sm btn-outline order-view-btn" data-id="${o.id}">View</button>
        </td>
      </tr>`).join('');
    },

    renderPagination() {
      const el = document.getElementById('ordersPagination');
      if (!el) return;
      const pages = Math.ceil(state.total / state.perPage);
      if (pages <= 1) { el.innerHTML = ''; return; }
      let html = '';
      for (let i = 1; i <= pages; i++) {
        html += `<button class="btn btn-sm orders-page-btn${i === state.page ? ' btn-primary' : ' btn-outline'}" data-page="${i}">${i}</button>`;
      }
      el.innerHTML = `<div class="pagination">${html}</div>`;
    },

    async openView(id) {
      const body = document.getElementById('orderModalBody');
      if (body) body.innerHTML = '<p>Loading…</p>';
      showModal('orderModal');
      try {
        const order = await apiFetch(apiUrl(`/${id}`));
        state.current = order;
        document.getElementById('orderModalTitle').textContent = `Order #${order.order_number || order.id}`;
        const sel = document.getElementById('orderStatusSelect');
        if (sel) sel.value = order.status || '';

        const items = order.items || order.order_items || [];
        const itemsHtml = items.length
          ? `<table class="admin-table" style="font-size:0.9em">
              <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr></thead>
              <tbody>
                ${items.map(i => `<tr>
                  <td>${escHtml(i.product_name || i.name || '—')}</td>
                  <td><code>${escHtml(i.sku || '—')}</code></td>
                  <td>${i.quantity}</td>
                  <td>${fmtCurrency(i.unit_price || i.price)}</td>
                  <td>${fmtCurrency(i.subtotal || (i.quantity * (i.unit_price || i.price || 0)))}</td>
                </tr>`).join('')}
              </tbody>
            </table>`
          : '<p class="text-muted">No items.</p>';

        const addr = order.shipping_address || {};
        const addrHtml = Object.keys(addr).length
          ? `<div><strong>Shipping Address:</strong><br>
              ${[addr.name, addr.line1, addr.line2, addr.city, addr.state, addr.zip, addr.country].filter(Boolean).map(escHtml).join(', ')}</div>`
          : '';

        if (body) body.innerHTML = `
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
            <div>
              <p><strong>Customer:</strong> ${escHtml(order.customer_name || '—')}</p>
              <p><strong>Email:</strong> ${escHtml(order.customer_email || '—')}</p>
              <p><strong>Phone:</strong> ${escHtml(order.customer_phone || '—')}</p>
            </div>
            <div>
              <p><strong>Status:</strong> ${statusBadge(order.status, STATUS_COLORS)}</p>
              <p><strong>Payment:</strong> ${statusBadge(order.payment_status, PAYMENT_COLORS)}</p>
              <p><strong>Total:</strong> <strong>${fmtCurrency(order.total_amount)}</strong></p>
            </div>
          </div>
          ${addrHtml}
          <div style="margin-top:12px"><strong>Items:</strong></div>
          ${itemsHtml}
          ${order.notes ? `<p style="margin-top:12px"><strong>Notes:</strong> ${escHtml(order.notes)}</p>` : ''}
          <p class="text-muted" style="margin-top:8px">Placed: ${fmtDate(order.created_at)}</p>`;
      } catch (err) {
        toast(err.message, 'error');
        if (body) body.innerHTML = `<p class="text-danger">${err.message}</p>`;
      }
    },

    async updateStatus() {
      if (!state.current) return;
      const newStatus = document.getElementById('orderStatusSelect').value;
      if (!newStatus) { toast('Select a status.', 'error'); return; }
      const btn = document.getElementById('orderStatusUpdateBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Updating…'; }
      try {
        await apiFetch(apiUrl(`/${state.current.id}/status`), {
          method: 'PATCH',
          body: JSON.stringify({ new_status: newStatus }),
        });
        toast(`Order status updated to "${newStatus}".`);
        state.current.status = newStatus;
        hideModal('orderModal');
        OrdersModule.load();
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Update Status'; }
      }
    },
  };

  window.OrdersModule = OrdersModule;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => OrdersModule.init());
  else OrdersModule.init();
})();
