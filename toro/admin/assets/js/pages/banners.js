(function () {
  'use strict';

  const CONFIG = window.BANNERS_CONFIG || {};

  const POSITIONS = [
    'home_hero', 'home_secondary', 'sidebar', 'category_top',
    'category_bottom', 'product_sidebar', 'footer',
  ];

  const state = {
    page: 1,
    perPage: 25,
    total: 0,
    items: [],
    filters: { position: '', status: '' },
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
    return (CONFIG.apiUrl || '/api/banners') + (path || '');
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
  function setLoading(on) {
    const el = document.getElementById('bannersTableBody');
    if (el) el.style.opacity = on ? '0.5' : '1';
  }
  function showModal() {
    const m = document.getElementById('bannerModal');
    if (m) { m.classList.add('is-active'); m.style.display = 'flex'; }
  }
  function hideModal() {
    const m = document.getElementById('bannerModal');
    if (m) { m.classList.remove('is-active'); m.style.display = 'none'; }
  }

  /* ── Module ─────────────────────────────────────────────── */
  const BannersModule = {
    init() {
      BannersModule._injectHTML();
      BannersModule._bindEvents();
      BannersModule.load();
    },

    _injectHTML() {
      const container = document.getElementById('bannersContainer');
      if (!container) return;
      const positionOptions = POSITIONS.map(p =>
        `<option value="${p}">${p.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase())}</option>`
      ).join('');

      container.innerHTML = `
        <div class="page-header">
          <h2>Banners</h2>
          <button id="bannerCreateBtn" class="btn btn-primary">+ Add Banner</button>
        </div>
        <div class="filters-bar">
          <select id="bannerPositionFilter" class="form-control" style="max-width:200px">
            <option value="">All Positions</option>
            ${positionOptions}
          </select>
          <select id="bannerStatusFilter" class="form-control" style="max-width:160px">
            <option value="">All Statuses</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          <button id="bannerSearchBtn" class="btn btn-secondary">Filter</button>
        </div>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Preview</th>
                <th>Title</th>
                <th>Position</th>
                <th>Sort</th>
                <th>Active</th>
                <th>Start</th>
                <th>End</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="bannersTableBody"><tr><td colspan="9">Loading…</td></tr></tbody>
          </table>
        </div>
        <div id="bannersPagination" class="pagination-bar"></div>

        <div id="bannerModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="bannerModalBackdrop"></div>
          <div class="modal-box">
            <div class="modal-header">
              <h3 id="bannerModalTitle">Add Banner</h3>
              <button class="modal-close" id="bannerModalClose">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Title <span class="required">*</span></label>
                <input type="text" id="bannerTitle" class="form-control" placeholder="Banner title">
              </div>
              <div class="form-group">
                <label>Image URL <span class="required">*</span></label>
                <input type="url" id="bannerImageUrl" class="form-control" placeholder="https://…/banner.jpg">
                <div id="bannerImagePreview" style="margin-top:8px"></div>
              </div>
              <div class="form-group">
                <label>Link URL</label>
                <input type="url" id="bannerLinkUrl" class="form-control" placeholder="https://…">
              </div>
              <div class="form-group">
                <label>Position <span class="required">*</span></label>
                <select id="bannerPosition" class="form-control">
                  ${positionOptions}
                </select>
              </div>
              <div class="form-row" style="display:flex;gap:12px">
                <div class="form-group" style="flex:1">
                  <label>Sort Order</label>
                  <input type="number" id="bannerSortOrder" class="form-control" value="0" min="0">
                </div>
                <div class="form-group form-check" style="align-self:flex-end;padding-bottom:8px">
                  <input type="checkbox" id="bannerIsActive" checked>
                  <label for="bannerIsActive">Active</label>
                </div>
              </div>
              <div class="form-row" style="display:flex;gap:12px">
                <div class="form-group" style="flex:1">
                  <label>Starts At</label>
                  <input type="datetime-local" id="bannerStartsAt" class="form-control">
                </div>
                <div class="form-group" style="flex:1">
                  <label>Ends At</label>
                  <input type="datetime-local" id="bannerEndsAt" class="form-control">
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button id="bannerSaveBtn" class="btn btn-primary">Save</button>
              <button id="bannerCancelBtn" class="btn btn-secondary">Cancel</button>
            </div>
          </div>
        </div>`;
    },

    _bindEvents() {
      document.addEventListener('click', function (e) {
        if (e.target.id === 'bannerCreateBtn') BannersModule.openCreate();
        if (e.target.id === 'bannerSaveBtn') BannersModule.save();
        if (e.target.id === 'bannerCancelBtn' || e.target.id === 'bannerModalClose' || e.target.id === 'bannerModalBackdrop') hideModal();
        if (e.target.matches('.banner-edit-btn')) BannersModule.openEdit(e.target.dataset.id);
        if (e.target.matches('.banner-delete-btn')) BannersModule.remove(e.target.dataset.id);
        if (e.target.id === 'bannerSearchBtn') BannersModule._applyFilters();
        if (e.target.matches('.banners-page-btn')) {
          state.page = parseInt(e.target.dataset.page, 10);
          BannersModule.load();
        }
      });
      document.addEventListener('input', function (e) {
        if (e.target.id === 'bannerImageUrl') {
          const prev = document.getElementById('bannerImagePreview');
          if (prev) {
            const url = e.target.value.trim();
            prev.innerHTML = url ? `<img src="${escHtml(url)}" alt="" style="max-height:80px;border-radius:4px;border:1px solid #ddd">` : '';
          }
        }
      });
    },

    _applyFilters() {
      state.filters.position = (document.getElementById('bannerPositionFilter') || {}).value || '';
      state.filters.status = (document.getElementById('bannerStatusFilter') || {}).value || '';
      state.page = 1;
      BannersModule.load();
    },

    async load() {
      setLoading(true);
      try {
        const params = new URLSearchParams({ page: state.page, per_page: state.perPage });
        if (state.filters.position) params.set('position', state.filters.position);
        if (state.filters.status !== '') params.set('is_active', state.filters.status);
        const data = await apiFetch(`${apiUrl()}?${params}`);
        state.items = data.items || data.data || data || [];
        state.total = data.total || state.items.length;
        BannersModule.renderTable(state.items);
        BannersModule.renderPagination();
      } catch (err) {
        toast(err.message, 'error');
        const tb = document.getElementById('bannersTableBody');
        if (tb) tb.innerHTML = `<tr><td colspan="9" class="text-danger">${err.message}</td></tr>`;
      } finally {
        setLoading(false);
      }
    },

    renderTable(items) {
      const tb = document.getElementById('bannersTableBody');
      if (!tb) return;
      if (!items.length) {
        tb.innerHTML = '<tr><td colspan="9" class="text-center">No banners found.</td></tr>';
        return;
      }
      tb.innerHTML = items.map(b => {
        const thumb = b.image_url
          ? `<img src="${escHtml(b.image_url)}" alt="" style="height:36px;width:auto;border-radius:4px;max-width:80px;object-fit:cover">`
          : '<span class="text-muted">—</span>';
        const status = b.is_active
          ? '<span class="badge badge-success">Active</span>'
          : '<span class="badge badge-secondary">Inactive</span>';
        const posLabel = (b.position || '').replace(/_/g, ' ');
        return `<tr>
          <td>${b.id}</td>
          <td>${thumb}</td>
          <td>${escHtml(b.title || '')}</td>
          <td><span class="badge badge-info">${escHtml(posLabel)}</span></td>
          <td>${b.sort_order ?? 0}</td>
          <td>${status}</td>
          <td>${fmtDate(b.starts_at)}</td>
          <td>${fmtDate(b.ends_at)}</td>
          <td class="actions">
            <button class="btn btn-sm btn-outline banner-edit-btn" data-id="${b.id}">Edit</button>
            <button class="btn btn-sm btn-danger banner-delete-btn" data-id="${b.id}">Delete</button>
          </td>
        </tr>`;
      }).join('');
    },

    renderPagination() {
      const el = document.getElementById('bannersPagination');
      if (!el) return;
      const pages = Math.ceil(state.total / state.perPage);
      if (pages <= 1) { el.innerHTML = ''; return; }
      let html = '';
      for (let i = 1; i <= pages; i++) {
        html += `<button class="btn btn-sm banners-page-btn${i === state.page ? ' btn-primary' : ' btn-outline'}" data-page="${i}">${i}</button>`;
      }
      el.innerHTML = `<div class="pagination">${html}</div>`;
    },

    _toDatetimeLocal(str) {
      if (!str) return '';
      return new Date(str).toISOString().slice(0, 16);
    },

    openCreate() {
      state.current = null;
      state.mode = 'create';
      document.getElementById('bannerModalTitle').textContent = 'Add Banner';
      document.getElementById('bannerTitle').value = '';
      document.getElementById('bannerImageUrl').value = '';
      document.getElementById('bannerImagePreview').innerHTML = '';
      document.getElementById('bannerLinkUrl').value = '';
      document.getElementById('bannerPosition').value = POSITIONS[0];
      document.getElementById('bannerSortOrder').value = '0';
      document.getElementById('bannerIsActive').checked = true;
      document.getElementById('bannerStartsAt').value = '';
      document.getElementById('bannerEndsAt').value = '';
      showModal();
    },

    async openEdit(id) {
      try {
        const b = await apiFetch(apiUrl(`/${id}`));
        state.current = b;
        state.mode = 'edit';
        document.getElementById('bannerModalTitle').textContent = 'Edit Banner';
        document.getElementById('bannerTitle').value = b.title || '';
        document.getElementById('bannerImageUrl').value = b.image_url || '';
        const prev = document.getElementById('bannerImagePreview');
        if (prev) prev.innerHTML = b.image_url ? `<img src="${escHtml(b.image_url)}" alt="" style="max-height:80px;border-radius:4px;border:1px solid #ddd">` : '';
        document.getElementById('bannerLinkUrl').value = b.link_url || '';
        document.getElementById('bannerPosition').value = b.position || POSITIONS[0];
        document.getElementById('bannerSortOrder').value = b.sort_order ?? 0;
        document.getElementById('bannerIsActive').checked = !!b.is_active;
        document.getElementById('bannerStartsAt').value = BannersModule._toDatetimeLocal(b.starts_at);
        document.getElementById('bannerEndsAt').value = BannersModule._toDatetimeLocal(b.ends_at);
        showModal();
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    async save() {
      const title = document.getElementById('bannerTitle').value.trim();
      const imageUrl = document.getElementById('bannerImageUrl').value.trim();
      if (!title) { toast('Title is required.', 'error'); return; }
      if (!imageUrl) { toast('Image URL is required.', 'error'); return; }
      const payload = {
        title,
        image_url: imageUrl,
        link_url: document.getElementById('bannerLinkUrl').value.trim() || null,
        position: document.getElementById('bannerPosition').value,
        sort_order: parseInt(document.getElementById('bannerSortOrder').value, 10) || 0,
        is_active: document.getElementById('bannerIsActive').checked,
        starts_at: document.getElementById('bannerStartsAt').value || null,
        ends_at: document.getElementById('bannerEndsAt').value || null,
      };
      const btn = document.getElementById('bannerSaveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
      try {
        if (state.mode === 'edit' && state.current) {
          await apiFetch(apiUrl(`/${state.current.id}`), { method: 'PATCH', body: JSON.stringify(payload) });
          toast('Banner updated.');
        } else {
          await apiFetch(apiUrl(), { method: 'POST', body: JSON.stringify(payload) });
          toast('Banner created.');
        }
        hideModal();
        BannersModule.load();
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
      }
    },

    async remove(id) {
      if (!confirm('Delete this banner? This cannot be undone.')) return;
      try {
        await apiFetch(apiUrl(`/${id}`), { method: 'DELETE' });
        toast('Banner deleted.');
        BannersModule.load();
      } catch (err) {
        toast(err.message, 'error');
      }
    },
  };

  window.BannersModule = BannersModule;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => BannersModule.init());
  else BannersModule.init();
})();
