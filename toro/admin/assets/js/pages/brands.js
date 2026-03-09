(function () {
  'use strict';

  const CONFIG = window.BRANDS_CONFIG || {};
  const LANGS = CONFIG.languages || ['ar', 'en'];

  const state = {
    page: 1,
    perPage: 25,
    total: 0,
    items: [],
    filters: { search: '', status: '' },
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
    return (CONFIG.apiUrl || '/api/brands') + (path || '');
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
  function slugify(str) {
    return str.toLowerCase().trim()
      .replace(/[\s_]+/g, '-')
      .replace(/[^\w-]/g, '')
      .replace(/--+/g, '-');
  }
  function setLoading(on) {
    const el = document.getElementById('brandsTableBody');
    if (el) el.style.opacity = on ? '0.5' : '1';
    const btn = document.getElementById('brandsLoadingIndicator');
    if (btn) btn.style.display = on ? 'inline-block' : 'none';
  }
  function getModal() {
    return document.getElementById('brandModal');
  }
  function showModal() {
    const m = getModal();
    if (!m) return;
    m.classList.add('is-active');
    m.style.display = 'flex';
  }
  function hideModal() {
    const m = getModal();
    if (!m) return;
    m.classList.remove('is-active');
    m.style.display = 'none';
  }

  /* ── Core Module ────────────────────────────────────────── */
  const BrandsModule = {
    init() {
      BrandsModule._injectHTML();
      BrandsModule._bindEvents();
      BrandsModule.load();
    },

    _injectHTML() {
      const container = document.getElementById('brandsContainer');
      if (!container) return;

      const langTabs = LANGS.map((l, i) =>
        `<button type="button" class="lang-tab-btn${i === 0 ? ' active' : ''}" data-lang="${l}">${l.toUpperCase()}</button>`
      ).join('');
      const langPanels = LANGS.map((l, i) =>
        `<div class="lang-panel${i === 0 ? '' : ' hidden'}" data-lang="${l}">
          <div class="form-group">
            <label>Name (${l.toUpperCase()})</label>
            <input type="text" id="brandName_${l}" class="form-control brand-name-input" data-lang="${l}" placeholder="Brand name in ${l}">
          </div>
        </div>`
      ).join('');

      container.innerHTML = `
        <div class="page-header">
          <h2>Brands</h2>
          <button id="brandCreateBtn" class="btn btn-primary">+ Add Brand</button>
        </div>
        <div class="filters-bar">
          <input type="text" id="brandSearch" class="form-control" placeholder="Search by name or slug…" style="max-width:260px">
          <select id="brandStatusFilter" class="form-control" style="max-width:160px">
            <option value="">All Statuses</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          <button id="brandSearchBtn" class="btn btn-secondary">Search</button>
          <span id="brandsLoadingIndicator" style="display:none">⏳</span>
        </div>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Logo</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Website</th>
                <th>Sort</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="brandsTableBody"><tr><td colspan="8">Loading…</td></tr></tbody>
          </table>
        </div>
        <div id="brandsPagination" class="pagination-bar"></div>

        <!-- Modal -->
        <div id="brandModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="brandModalBackdrop"></div>
          <div class="modal-box">
            <div class="modal-header">
              <h3 id="brandModalTitle">Add Brand</h3>
              <button class="modal-close" id="brandModalClose">&times;</button>
            </div>
            <div class="modal-body">
              <div class="lang-tabs">${langTabs}</div>
              ${langPanels}
              <div class="form-group">
                <label>Slug</label>
                <input type="text" id="brandSlug" class="form-control" placeholder="auto-generated">
              </div>
              <div class="form-group">
                <label>Website</label>
                <input type="url" id="brandWebsite" class="form-control" placeholder="https://…">
              </div>
              <div class="form-group">
                <label>Logo URL</label>
                <input type="url" id="brandLogoUrl" class="form-control" placeholder="https://…/logo.png">
              </div>
              <div class="form-group">
                <label>Sort Order</label>
                <input type="number" id="brandSortOrder" class="form-control" value="0" min="0">
              </div>
              <div class="form-group form-check">
                <input type="checkbox" id="brandIsActive" checked>
                <label for="brandIsActive">Active</label>
              </div>
            </div>
            <div class="modal-footer">
              <button id="brandSaveBtn" class="btn btn-primary">Save</button>
              <button id="brandCancelBtn" class="btn btn-secondary">Cancel</button>
            </div>
          </div>
        </div>`;
    },

    _bindEvents() {
      document.addEventListener('click', function (e) {
        if (e.target.id === 'brandCreateBtn') BrandsModule.openCreate();
        if (e.target.id === 'brandSaveBtn') BrandsModule.save();
        if (e.target.id === 'brandCancelBtn' || e.target.id === 'brandModalClose' || e.target.id === 'brandModalBackdrop') hideModal();
        if (e.target.matches('.brand-edit-btn')) BrandsModule.openEdit(e.target.dataset.id);
        if (e.target.matches('.brand-delete-btn')) BrandsModule.remove(e.target.dataset.id);
        if (e.target.matches('.lang-tab-btn')) BrandsModule._switchLang(e.target.dataset.lang, e.target);
        if (e.target.matches('.brands-page-btn')) {
          state.page = parseInt(e.target.dataset.page, 10);
          BrandsModule.load();
        }
      });

      document.addEventListener('input', function (e) {
        if (e.target.matches('.brand-name-input') && e.target.dataset.lang === LANGS[0]) {
          const slugEl = document.getElementById('brandSlug');
          if (slugEl && !state.current) slugEl.value = slugify(e.target.value);
        }
      });

      document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.id === 'brandSearch') BrandsModule._applyFilters();
      });

      document.addEventListener('click', function (e) {
        if (e.target.id === 'brandSearchBtn') BrandsModule._applyFilters();
      });
    },

    _switchLang(lang, btn) {
      document.querySelectorAll('.lang-tab-btn').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.lang-panel').forEach(p => p.classList.add('hidden'));
      if (btn) btn.classList.add('active');
      const panel = document.querySelector(`.lang-panel[data-lang="${lang}"]`);
      if (panel) panel.classList.remove('hidden');
    },

    _applyFilters() {
      state.filters.search = (document.getElementById('brandSearch') || {}).value || '';
      state.filters.status = (document.getElementById('brandStatusFilter') || {}).value || '';
      state.page = 1;
      BrandsModule.load();
    },

    async load() {
      setLoading(true);
      try {
        const params = new URLSearchParams({
          page: state.page,
          per_page: state.perPage,
        });
        if (state.filters.search) params.set('search', state.filters.search);
        if (state.filters.status !== '') params.set('is_active', state.filters.status);
        const data = await apiFetch(`${apiUrl()}?${params}`);
        state.items = data.items || data.data || data || [];
        state.total = data.total || state.items.length;
        BrandsModule.renderTable(state.items);
        BrandsModule.renderPagination();
      } catch (err) {
        toast(err.message, 'error');
        const tb = document.getElementById('brandsTableBody');
        if (tb) tb.innerHTML = `<tr><td colspan="8" class="text-danger">${err.message}</td></tr>`;
      } finally {
        setLoading(false);
      }
    },

    renderTable(items) {
      const tb = document.getElementById('brandsTableBody');
      if (!tb) return;
      if (!items.length) {
        tb.innerHTML = '<tr><td colspan="8" class="text-center">No brands found.</td></tr>';
        return;
      }
      tb.innerHTML = items.map(b => {
        const nameTranslations = b.name_translations || {};
        const displayName = nameTranslations[LANGS[0]] || nameTranslations['en'] || nameTranslations['ar'] || b.name || '—';
        const logo = b.logo_url
          ? `<img src="${escHtml(b.logo_url)}" alt="" style="height:32px;width:auto;border-radius:4px">`
          : '<span class="text-muted">—</span>';
        const status = b.is_active
          ? '<span class="badge badge-success">Active</span>'
          : '<span class="badge badge-secondary">Inactive</span>';
        return `<tr>
          <td>${b.id}</td>
          <td>${logo}</td>
          <td>${escHtml(displayName)}</td>
          <td><code>${escHtml(b.slug || '')}</code></td>
          <td>${b.website ? `<a href="${escHtml(b.website)}" target="_blank" rel="noopener">${escHtml(b.website)}</a>` : '—'}</td>
          <td>${b.sort_order ?? 0}</td>
          <td>${status}</td>
          <td class="actions">
            <button class="btn btn-sm btn-outline brand-edit-btn" data-id="${b.id}">Edit</button>
            <button class="btn btn-sm btn-danger brand-delete-btn" data-id="${b.id}">Delete</button>
          </td>
        </tr>`;
      }).join('');
    },

    renderPagination() {
      const el = document.getElementById('brandsPagination');
      if (!el) return;
      const pages = Math.ceil(state.total / state.perPage);
      if (pages <= 1) { el.innerHTML = ''; return; }
      let html = '';
      for (let i = 1; i <= pages; i++) {
        html += `<button class="btn btn-sm brands-page-btn${i === state.page ? ' btn-primary' : ' btn-outline'}" data-page="${i}">${i}</button>`;
      }
      el.innerHTML = `<div class="pagination">${html}</div>`;
    },

    openCreate() {
      state.current = null;
      state.mode = 'create';
      document.getElementById('brandModalTitle').textContent = 'Add Brand';
      LANGS.forEach(l => {
        const el = document.getElementById(`brandName_${l}`);
        if (el) el.value = '';
      });
      document.getElementById('brandSlug').value = '';
      document.getElementById('brandWebsite').value = '';
      document.getElementById('brandLogoUrl').value = '';
      document.getElementById('brandSortOrder').value = '0';
      document.getElementById('brandIsActive').checked = true;
      BrandsModule._switchLang(LANGS[0], document.querySelector(`.lang-tab-btn[data-lang="${LANGS[0]}"]`));
      showModal();
    },

    async openEdit(id) {
      try {
        const brand = await apiFetch(apiUrl(`/${id}`));
        state.current = brand;
        state.mode = 'edit';
        document.getElementById('brandModalTitle').textContent = 'Edit Brand';
        const translations = brand.name_translations || {};
        LANGS.forEach(l => {
          const el = document.getElementById(`brandName_${l}`);
          if (el) el.value = translations[l] || brand.name || '';
        });
        document.getElementById('brandSlug').value = brand.slug || '';
        document.getElementById('brandWebsite').value = brand.website || '';
        document.getElementById('brandLogoUrl').value = brand.logo_url || '';
        document.getElementById('brandSortOrder').value = brand.sort_order ?? 0;
        document.getElementById('brandIsActive').checked = !!brand.is_active;
        BrandsModule._switchLang(LANGS[0], document.querySelector(`.lang-tab-btn[data-lang="${LANGS[0]}"]`));
        showModal();
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    async save() {
      const name_translations = {};
      LANGS.forEach(l => {
        const el = document.getElementById(`brandName_${l}`);
        if (el) name_translations[l] = el.value.trim();
      });
      const payload = {
        name_translations,
        slug: document.getElementById('brandSlug').value.trim() || slugify(name_translations[LANGS[0]] || ''),
        website: document.getElementById('brandWebsite').value.trim(),
        logo_url: document.getElementById('brandLogoUrl').value.trim(),
        sort_order: parseInt(document.getElementById('brandSortOrder').value, 10) || 0,
        is_active: document.getElementById('brandIsActive').checked,
      };

      if (!name_translations[LANGS[0]] && !name_translations['en']) {
        toast('Name is required.', 'error'); return;
      }

      const saveBtn = document.getElementById('brandSaveBtn');
      if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }

      try {
        if (state.mode === 'edit' && state.current) {
          await apiFetch(apiUrl(`/${state.current.id}`), { method: 'PATCH', body: JSON.stringify(payload) });
          toast('Brand updated.');
        } else {
          await apiFetch(apiUrl(), { method: 'POST', body: JSON.stringify(payload) });
          toast('Brand created.');
        }
        hideModal();
        BrandsModule.load();
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
      }
    },

    async remove(id) {
      if (!confirm('Delete this brand? This cannot be undone.')) return;
      try {
        await apiFetch(apiUrl(`/${id}`), { method: 'DELETE' });
        toast('Brand deleted.');
        BrandsModule.load();
      } catch (err) {
        toast(err.message, 'error');
      }
    },
  };

  window.BrandsModule = BrandsModule;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => BrandsModule.init());
  else BrandsModule.init();
})();
