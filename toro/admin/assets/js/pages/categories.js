(function () {
  'use strict';

  const CONFIG = window.CATEGORIES_CONFIG || {};

  const state = {
    page: 1,
    perPage: 25,
    total: 0,
    items: [],
    allCategories: [],
    filters: { search: '', status: '', parent_id: '' },
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
    return (CONFIG.apiUrl || '/api/categories') + (path || '');
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
  function slugify(str) {
    return str.toLowerCase().trim().replace(/[\s_]+/g, '-').replace(/[^\w-]/g, '').replace(/--+/g, '-');
  }
  function escHtml(str) {
    return String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function setLoading(on) {
    const el = document.getElementById('categoriesTableBody');
    if (el) el.style.opacity = on ? '0.5' : '1';
  }
  function showModal() {
    const m = document.getElementById('categoryModal');
    if (m) { m.classList.add('is-active'); m.style.display = 'flex'; }
  }
  function hideModal() {
    const m = document.getElementById('categoryModal');
    if (m) { m.classList.remove('is-active'); m.style.display = 'none'; }
  }

  /* ── Module ─────────────────────────────────────────────── */
  const CategoriesModule = {
    init() {
      CategoriesModule._injectHTML();
      CategoriesModule._bindEvents();
      CategoriesModule.loadAll().then(() => CategoriesModule.load());
    },

    _injectHTML() {
      const container = document.getElementById('categoriesContainer');
      if (!container) return;
      container.innerHTML = `
        <div class="page-header">
          <h2>Categories</h2>
          <button id="categoryCreateBtn" class="btn btn-primary">+ Add Category</button>
        </div>
        <div class="filters-bar">
          <input type="text" id="categorySearch" class="form-control" placeholder="Search by name or slug…" style="max-width:260px">
          <select id="categoryStatusFilter" class="form-control" style="max-width:160px">
            <option value="">All Statuses</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          <select id="categoryParentFilter" class="form-control" style="max-width:200px">
            <option value="">All Parents</option>
          </select>
          <button id="categorySearchBtn" class="btn btn-secondary">Search</button>
        </div>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Parent</th>
                <th>Sort</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="categoriesTableBody"><tr><td colspan="8">Loading…</td></tr></tbody>
          </table>
        </div>
        <div id="categoriesPagination" class="pagination-bar"></div>

        <div id="categoryModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="categoryModalBackdrop"></div>
          <div class="modal-box">
            <div class="modal-header">
              <h3 id="categoryModalTitle">Add Category</h3>
              <button class="modal-close" id="categoryModalClose">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Name <span class="required">*</span></label>
                <input type="text" id="categoryName" class="form-control" placeholder="Category name">
              </div>
              <div class="form-group">
                <label>Slug</label>
                <input type="text" id="categorySlug" class="form-control" placeholder="auto-generated">
              </div>
              <div class="form-group">
                <label>Parent Category</label>
                <select id="categoryParentId" class="form-control">
                  <option value="">— No Parent (Root) —</option>
                </select>
              </div>
              <div class="form-group">
                <label>Image URL</label>
                <input type="url" id="categoryImageUrl" class="form-control" placeholder="https://…/image.jpg">
              </div>
              <div class="form-group">
                <label>Sort Order</label>
                <input type="number" id="categorySortOrder" class="form-control" value="0" min="0">
              </div>
              <div class="form-group form-check">
                <input type="checkbox" id="categoryIsActive" checked>
                <label for="categoryIsActive">Active</label>
              </div>
            </div>
            <div class="modal-footer">
              <button id="categorySaveBtn" class="btn btn-primary">Save</button>
              <button id="categoryCancelBtn" class="btn btn-secondary">Cancel</button>
            </div>
          </div>
        </div>`;
    },

    _bindEvents() {
      document.addEventListener('click', function (e) {
        if (e.target.id === 'categoryCreateBtn') CategoriesModule.openCreate();
        if (e.target.id === 'categorySaveBtn') CategoriesModule.save();
        if (e.target.id === 'categoryCancelBtn' || e.target.id === 'categoryModalClose' || e.target.id === 'categoryModalBackdrop') hideModal();
        if (e.target.matches('.category-edit-btn')) CategoriesModule.openEdit(e.target.dataset.id);
        if (e.target.matches('.category-delete-btn')) CategoriesModule.remove(e.target.dataset.id);
        if (e.target.id === 'categorySearchBtn') CategoriesModule._applyFilters();
        if (e.target.matches('.categories-page-btn')) {
          state.page = parseInt(e.target.dataset.page, 10);
          CategoriesModule.load();
        }
      });
      document.addEventListener('input', function (e) {
        if (e.target.id === 'categoryName') {
          const slugEl = document.getElementById('categorySlug');
          if (slugEl && !state.current) slugEl.value = slugify(e.target.value);
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.id === 'categorySearch') CategoriesModule._applyFilters();
      });
    },

    _applyFilters() {
      state.filters.search = (document.getElementById('categorySearch') || {}).value || '';
      state.filters.status = (document.getElementById('categoryStatusFilter') || {}).value || '';
      state.filters.parent_id = (document.getElementById('categoryParentFilter') || {}).value || '';
      state.page = 1;
      CategoriesModule.load();
    },

    async loadAll() {
      try {
        const data = await apiFetch(`${apiUrl()}?per_page=500`);
        state.allCategories = data.items || data.data || data || [];
        CategoriesModule._populateParentSelects();
      } catch (_) { /* non-fatal */ }
    },

    _populateParentSelects() {
      const opts = state.allCategories.map(c =>
        `<option value="${c.id}">${escHtml(c.name || '')}</option>`
      ).join('');
      ['categoryParentId', 'categoryParentFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const extra = id === 'categoryParentFilter' ? '<option value="">All Parents</option>' : '<option value="">— No Parent (Root) —</option>';
        el.innerHTML = extra + opts;
      });
    },

    async load() {
      setLoading(true);
      try {
        const params = new URLSearchParams({ page: state.page, per_page: state.perPage });
        if (state.filters.search) params.set('search', state.filters.search);
        if (state.filters.status !== '') params.set('is_active', state.filters.status);
        if (state.filters.parent_id !== '') params.set('parent_id', state.filters.parent_id);
        const data = await apiFetch(`${apiUrl()}?${params}`);
        state.items = data.items || data.data || data || [];
        state.total = data.total || state.items.length;
        CategoriesModule.renderTable(state.items);
        CategoriesModule.renderPagination();
      } catch (err) {
        toast(err.message, 'error');
        const tb = document.getElementById('categoriesTableBody');
        if (tb) tb.innerHTML = `<tr><td colspan="8" class="text-danger">${err.message}</td></tr>`;
      } finally {
        setLoading(false);
      }
    },

    renderTable(items) {
      const tb = document.getElementById('categoriesTableBody');
      if (!tb) return;
      if (!items.length) {
        tb.innerHTML = '<tr><td colspan="8" class="text-center">No categories found.</td></tr>';
        return;
      }
      const parentMap = Object.fromEntries(state.allCategories.map(c => [c.id, c.name]));
      tb.innerHTML = items.map(c => {
        const thumb = c.image_url
          ? `<img src="${escHtml(c.image_url)}" alt="" style="height:32px;width:32px;object-fit:cover;border-radius:4px">`
          : '<span class="text-muted">—</span>';
        const parent = c.parent_id ? escHtml(parentMap[c.parent_id] || `#${c.parent_id}`) : '<em>Root</em>';
        const status = c.is_active
          ? '<span class="badge badge-success">Active</span>'
          : '<span class="badge badge-secondary">Inactive</span>';
        return `<tr>
          <td>${c.id}</td>
          <td>${thumb}</td>
          <td>${escHtml(c.name || '')}</td>
          <td><code>${escHtml(c.slug || '')}</code></td>
          <td>${parent}</td>
          <td>${c.sort_order ?? 0}</td>
          <td>${status}</td>
          <td class="actions">
            <button class="btn btn-sm btn-outline category-edit-btn" data-id="${c.id}">Edit</button>
            <button class="btn btn-sm btn-danger category-delete-btn" data-id="${c.id}">Delete</button>
          </td>
        </tr>`;
      }).join('');
    },

    renderPagination() {
      const el = document.getElementById('categoriesPagination');
      if (!el) return;
      const pages = Math.ceil(state.total / state.perPage);
      if (pages <= 1) { el.innerHTML = ''; return; }
      let html = '';
      for (let i = 1; i <= pages; i++) {
        html += `<button class="btn btn-sm categories-page-btn${i === state.page ? ' btn-primary' : ' btn-outline'}" data-page="${i}">${i}</button>`;
      }
      el.innerHTML = `<div class="pagination">${html}</div>`;
    },

    openCreate() {
      state.current = null;
      state.mode = 'create';
      document.getElementById('categoryModalTitle').textContent = 'Add Category';
      document.getElementById('categoryName').value = '';
      document.getElementById('categorySlug').value = '';
      document.getElementById('categoryParentId').value = '';
      document.getElementById('categoryImageUrl').value = '';
      document.getElementById('categorySortOrder').value = '0';
      document.getElementById('categoryIsActive').checked = true;
      showModal();
    },

    async openEdit(id) {
      try {
        const cat = await apiFetch(apiUrl(`/${id}`));
        state.current = cat;
        state.mode = 'edit';
        document.getElementById('categoryModalTitle').textContent = 'Edit Category';
        document.getElementById('categoryName').value = cat.name || '';
        document.getElementById('categorySlug').value = cat.slug || '';
        document.getElementById('categoryParentId').value = cat.parent_id || '';
        document.getElementById('categoryImageUrl').value = cat.image_url || '';
        document.getElementById('categorySortOrder').value = cat.sort_order ?? 0;
        document.getElementById('categoryIsActive').checked = !!cat.is_active;
        showModal();
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    async save() {
      const name = document.getElementById('categoryName').value.trim();
      if (!name) { toast('Name is required.', 'error'); return; }
      const payload = {
        name,
        slug: document.getElementById('categorySlug').value.trim() || slugify(name),
        parent_id: document.getElementById('categoryParentId').value || null,
        image_url: document.getElementById('categoryImageUrl').value.trim() || null,
        sort_order: parseInt(document.getElementById('categorySortOrder').value, 10) || 0,
        is_active: document.getElementById('categoryIsActive').checked,
      };
      const saveBtn = document.getElementById('categorySaveBtn');
      if (saveBtn) { saveBtn.disabled = true; saveBtn.textContent = 'Saving…'; }
      try {
        if (state.mode === 'edit' && state.current) {
          await apiFetch(apiUrl(`/${state.current.id}`), { method: 'PATCH', body: JSON.stringify(payload) });
          toast('Category updated.');
        } else {
          await apiFetch(apiUrl(), { method: 'POST', body: JSON.stringify(payload) });
          toast('Category created.');
        }
        hideModal();
        await CategoriesModule.loadAll();
        CategoriesModule.load();
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (saveBtn) { saveBtn.disabled = false; saveBtn.textContent = 'Save'; }
      }
    },

    async remove(id) {
      if (!confirm('Delete this category? Child categories may be affected.')) return;
      try {
        await apiFetch(apiUrl(`/${id}`), { method: 'DELETE' });
        toast('Category deleted.');
        await CategoriesModule.loadAll();
        CategoriesModule.load();
      } catch (err) {
        toast(err.message, 'error');
      }
    },
  };

  window.CategoriesModule = CategoriesModule;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => CategoriesModule.init());
  else CategoriesModule.init();
})();
