(function () {
  'use strict';

  const CONFIG = window.COUPONS_CONFIG || {};

  const state = {
    page: 1,
    perPage: 25,
    total: 0,
    items: [],
    filters: { search: '', type: '', status: '' },
    current: null,
    mode: 'create',
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
    return (CONFIG.apiUrl || '/api/coupons') + (path || '');
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
    return new Date(d).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }
  function toDatetimeLocal(str) {
    if (!str) return '';
    return new Date(str).toISOString().slice(0, 16);
  }
  function randomCode(len = 8) {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    let code = '';
    const arr = new Uint8Array(len);
    crypto.getRandomValues(arr);
    arr.forEach(b => { code += chars[b % chars.length]; });
    return code;
  }
  function setLoading(on) {
    const el = document.getElementById('couponsTableBody');
    if (el) el.style.opacity = on ? '0.5' : '1';
  }
  function showModal() {
    const m = document.getElementById('couponModal');
    if (m) { m.classList.add('is-active'); m.style.display = 'flex'; }
  }
  function hideModal() {
    const m = document.getElementById('couponModal');
    if (m) { m.classList.remove('is-active'); m.style.display = 'none'; }
  }

  /* ── Module ─────────────────────────────────────────────── */
  const CouponsModule = {
    init() {
      CouponsModule._injectHTML();
      CouponsModule._bindEvents();
      CouponsModule.load();
    },

    _injectHTML() {
      const container = document.getElementById('couponsContainer');
      if (!container) return;
      container.innerHTML = `
        <div class="page-header">
          <h2>Coupons</h2>
          <button id="couponCreateBtn" class="btn btn-primary">+ Add Coupon</button>
        </div>
        <div class="filters-bar">
          <input type="text" id="couponSearch" class="form-control" placeholder="Search by code…" style="max-width:220px">
          <select id="couponTypeFilter" class="form-control" style="max-width:160px">
            <option value="">All Types</option>
            <option value="percentage">Percentage</option>
            <option value="fixed">Fixed</option>
          </select>
          <select id="couponStatusFilter" class="form-control" style="max-width:160px">
            <option value="">All Statuses</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          <button id="couponSearchBtn" class="btn btn-secondary">Search</button>
        </div>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Code</th>
                <th>Type</th>
                <th>Discount</th>
                <th>Min Order</th>
                <th>Uses</th>
                <th>Expires</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="couponsTableBody"><tr><td colspan="9">Loading…</td></tr></tbody>
          </table>
        </div>
        <div id="couponsPagination" class="pagination-bar"></div>

        <div id="couponModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="couponModalBackdrop"></div>
          <div class="modal-box">
            <div class="modal-header">
              <h3 id="couponModalTitle">Add Coupon</h3>
              <button class="modal-close" id="couponModalClose">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Code <span class="required">*</span></label>
                <div style="display:flex;gap:8px">
                  <input type="text" id="couponCode" class="form-control" placeholder="SAVE20" style="text-transform:uppercase">
                  <button type="button" id="couponGenerateBtn" class="btn btn-secondary" style="white-space:nowrap">Generate</button>
                </div>
              </div>
              <div class="form-group">
                <label>Type <span class="required">*</span></label>
                <select id="couponType" class="form-control">
                  <option value="percentage">Percentage (%)</option>
                  <option value="fixed">Fixed Amount</option>
                </select>
              </div>
              <div class="form-group">
                <label>Discount Value <span class="required">*</span></label>
                <input type="number" id="couponDiscountValue" class="form-control" min="0" step="0.01" placeholder="e.g. 10 for 10% or 5.00 for $5">
              </div>
              <div class="form-group">
                <label>Minimum Order Amount</label>
                <input type="number" id="couponMinOrder" class="form-control" min="0" step="0.01" placeholder="0 = no minimum">
              </div>
              <div class="form-group">
                <label>Max Uses</label>
                <input type="number" id="couponMaxUses" class="form-control" min="0" placeholder="0 = unlimited">
              </div>
              <div class="form-row" style="display:flex;gap:12px">
                <div class="form-group" style="flex:1">
                  <label>Starts At</label>
                  <input type="datetime-local" id="couponStartsAt" class="form-control">
                </div>
                <div class="form-group" style="flex:1">
                  <label>Expires At</label>
                  <input type="datetime-local" id="couponExpiresAt" class="form-control">
                </div>
              </div>
              <div class="form-group form-check">
                <input type="checkbox" id="couponIsActive" checked>
                <label for="couponIsActive">Active</label>
              </div>
            </div>
            <div class="modal-footer">
              <button id="couponSaveBtn" class="btn btn-primary">Save</button>
              <button id="couponCancelBtn" class="btn btn-secondary">Cancel</button>
            </div>
          </div>
        </div>`;
    },

    _bindEvents() {
      document.addEventListener('click', function (e) {
        if (e.target.id === 'couponCreateBtn') CouponsModule.openCreate();
        if (e.target.id === 'couponSaveBtn') CouponsModule.save();
        if (e.target.id === 'couponCancelBtn' || e.target.id === 'couponModalClose' || e.target.id === 'couponModalBackdrop') hideModal();
        if (e.target.id === 'couponGenerateBtn') {
          const codeEl = document.getElementById('couponCode');
          if (codeEl) codeEl.value = randomCode();
        }
        if (e.target.matches('.coupon-edit-btn')) CouponsModule.openEdit(e.target.dataset.id);
        if (e.target.matches('.coupon-delete-btn')) CouponsModule.remove(e.target.dataset.id);
        if (e.target.id === 'couponSearchBtn') CouponsModule._applyFilters();
        if (e.target.matches('.coupons-page-btn')) {
          state.page = parseInt(e.target.dataset.page, 10);
          CouponsModule.load();
        }
      });
      document.addEventListener('input', function (e) {
        if (e.target.id === 'couponCode') {
          e.target.value = e.target.value.toUpperCase().replace(/[^A-Z0-9_-]/g, '');
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.id === 'couponSearch') CouponsModule._applyFilters();
      });
    },

    _applyFilters() {
      state.filters.search = (document.getElementById('couponSearch') || {}).value || '';
      state.filters.type = (document.getElementById('couponTypeFilter') || {}).value || '';
      state.filters.status = (document.getElementById('couponStatusFilter') || {}).value || '';
      state.page = 1;
      CouponsModule.load();
    },

    async load() {
      setLoading(true);
      try {
        const params = new URLSearchParams({ page: state.page, per_page: state.perPage });
        if (state.filters.search) params.set('search', state.filters.search);
        if (state.filters.type) params.set('type', state.filters.type);
        if (state.filters.status !== '') params.set('is_active', state.filters.status);
        const data = await apiFetch(`${apiUrl()}?${params}`);
        state.items = data.items || data.data || data || [];
        state.total = data.total || state.items.length;
        CouponsModule.renderTable(state.items);
        CouponsModule.renderPagination();
      } catch (err) {
        toast(err.message, 'error');
        const tb = document.getElementById('couponsTableBody');
        if (tb) tb.innerHTML = `<tr><td colspan="9" class="text-danger">${err.message}</td></tr>`;
      } finally {
        setLoading(false);
      }
    },

    renderTable(items) {
      const tb = document.getElementById('couponsTableBody');
      if (!tb) return;
      if (!items.length) {
        tb.innerHTML = '<tr><td colspan="9" class="text-center">No coupons found.</td></tr>';
        return;
      }
      tb.innerHTML = items.map(c => {
        const typeLabel = c.type === 'percentage' ? '%' : '$';
        const discountDisplay = c.type === 'percentage'
          ? `${c.discount_value}%`
          : `${typeLabel}${Number(c.discount_value).toFixed(2)}`;
        const usesDisplay = c.max_uses
          ? `${c.used_count || 0} / ${c.max_uses}`
          : `${c.used_count || 0} / ∞`;
        const status = c.is_active
          ? '<span class="badge badge-success">Active</span>'
          : '<span class="badge badge-secondary">Inactive</span>';
        return `<tr>
          <td>${c.id}</td>
          <td><code class="coupon-code">${escHtml(c.code || '')}</code></td>
          <td><span class="badge badge-info">${escHtml(c.type || '')}</span></td>
          <td><strong>${escHtml(discountDisplay)}</strong></td>
          <td>${c.min_order_amount ? `$${Number(c.min_order_amount).toFixed(2)}` : '—'}</td>
          <td>${usesDisplay}</td>
          <td>${fmtDate(c.expires_at)}</td>
          <td>${status}</td>
          <td class="actions">
            <button class="btn btn-sm btn-outline coupon-edit-btn" data-id="${c.id}">Edit</button>
            <button class="btn btn-sm btn-danger coupon-delete-btn" data-id="${c.id}">Delete</button>
          </td>
        </tr>`;
      }).join('');
    },

    renderPagination() {
      const el = document.getElementById('couponsPagination');
      if (!el) return;
      const pages = Math.ceil(state.total / state.perPage);
      if (pages <= 1) { el.innerHTML = ''; return; }
      let html = '';
      for (let i = 1; i <= pages; i++) {
        html += `<button class="btn btn-sm coupons-page-btn${i === state.page ? ' btn-primary' : ' btn-outline'}" data-page="${i}">${i}</button>`;
      }
      el.innerHTML = `<div class="pagination">${html}</div>`;
    },

    openCreate() {
      state.current = null;
      state.mode = 'create';
      document.getElementById('couponModalTitle').textContent = 'Add Coupon';
      document.getElementById('couponCode').value = '';
      document.getElementById('couponType').value = 'percentage';
      document.getElementById('couponDiscountValue').value = '';
      document.getElementById('couponMinOrder').value = '';
      document.getElementById('couponMaxUses').value = '';
      document.getElementById('couponStartsAt').value = '';
      document.getElementById('couponExpiresAt').value = '';
      document.getElementById('couponIsActive').checked = true;
      showModal();
    },

    async openEdit(id) {
      try {
        const c = await apiFetch(apiUrl(`/${id}`));
        state.current = c;
        state.mode = 'edit';
        document.getElementById('couponModalTitle').textContent = 'Edit Coupon';
        document.getElementById('couponCode').value = c.code || '';
        document.getElementById('couponType').value = c.type || 'percentage';
        document.getElementById('couponDiscountValue').value = c.discount_value ?? '';
        document.getElementById('couponMinOrder').value = c.min_order_amount ?? '';
        document.getElementById('couponMaxUses').value = c.max_uses ?? '';
        document.getElementById('couponStartsAt').value = toDatetimeLocal(c.starts_at);
        document.getElementById('couponExpiresAt').value = toDatetimeLocal(c.expires_at);
        document.getElementById('couponIsActive').checked = !!c.is_active;
        showModal();
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    async save() {
      const code = document.getElementById('couponCode').value.trim().toUpperCase();
      const discountValue = parseFloat(document.getElementById('couponDiscountValue').value);
      if (!code) { toast('Code is required.', 'error'); return; }
      if (isNaN(discountValue) || discountValue <= 0) { toast('Discount value must be positive.', 'error'); return; }
      const couponType = document.getElementById('couponType').value;
      if (couponType === 'percentage' && discountValue > 100) {
        toast('Percentage discount cannot exceed 100%.', 'error'); return;
      }
      const payload = {
        code,
        type: couponType,
        discount_value: discountValue,
        min_order_amount: parseFloat(document.getElementById('couponMinOrder').value) || 0,
        max_uses: parseInt(document.getElementById('couponMaxUses').value, 10) || 0,
        starts_at: document.getElementById('couponStartsAt').value || null,
        expires_at: document.getElementById('couponExpiresAt').value || null,
        is_active: document.getElementById('couponIsActive').checked,
      };
      const btn = document.getElementById('couponSaveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
      try {
        if (state.mode === 'edit' && state.current) {
          await apiFetch(apiUrl(`/${state.current.id}`), { method: 'PATCH', body: JSON.stringify(payload) });
          toast('Coupon updated.');
        } else {
          await apiFetch(apiUrl(), { method: 'POST', body: JSON.stringify(payload) });
          toast('Coupon created.');
        }
        hideModal();
        CouponsModule.load();
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
      }
    },

    async remove(id) {
      if (!confirm('Delete this coupon? This cannot be undone.')) return;
      try {
        await apiFetch(apiUrl(`/${id}`), { method: 'DELETE' });
        toast('Coupon deleted.');
        CouponsModule.load();
      } catch (err) {
        toast(err.message, 'error');
      }
    },
  };

  window.CouponsModule = CouponsModule;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => CouponsModule.init());
  else CouponsModule.init();
})();
