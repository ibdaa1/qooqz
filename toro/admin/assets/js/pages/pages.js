(function () {
  'use strict';

  const CONFIG = window.PAGES_CONFIG || {};

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
    return (CONFIG.apiUrl || '/api/pages') + (path || '');
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
  function slugify(str) {
    return str.toLowerCase().trim().replace(/[\s_]+/g, '-').replace(/[^\w-]/g, '').replace(/--+/g, '-');
  }
  function setLoading(on) {
    const el = document.getElementById('pagesTableBody');
    if (el) el.style.opacity = on ? '0.5' : '1';
  }
  function showModal() {
    const m = document.getElementById('pageModal');
    if (m) { m.classList.add('is-active'); m.style.display = 'flex'; }
  }
  function hideModal() {
    const m = document.getElementById('pageModal');
    if (m) { m.classList.remove('is-active'); m.style.display = 'none'; }
  }

  /* ── Module ─────────────────────────────────────────────── */
  const PagesModule = {
    init() {
      PagesModule._injectHTML();
      PagesModule._bindEvents();
      PagesModule.load();
    },

    _injectHTML() {
      const container = document.getElementById('pagesContainer');
      if (!container) return;
      container.innerHTML = `
        <div class="page-header">
          <h2>Pages</h2>
          <button id="pageCreateBtn" class="btn btn-primary">+ Add Page</button>
        </div>
        <div class="filters-bar">
          <input type="text" id="pageSearch" class="form-control" placeholder="Search by title or slug…" style="max-width:280px">
          <select id="pageStatusFilter" class="form-control" style="max-width:160px">
            <option value="">All Statuses</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
          </select>
          <button id="pageSearchBtn" class="btn btn-secondary">Search</button>
        </div>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Updated</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="pagesTableBody"><tr><td colspan="6">Loading…</td></tr></tbody>
          </table>
        </div>
        <div id="pagesPagination" class="pagination-bar"></div>

        <div id="pageModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="pageModalBackdrop"></div>
          <div class="modal-box modal-lg">
            <div class="modal-header">
              <h3 id="pageModalTitle">Add Page</h3>
              <button class="modal-close" id="pageModalClose">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Title <span class="required">*</span></label>
                <input type="text" id="pageTitle" class="form-control" placeholder="About Us">
              </div>
              <div class="form-group">
                <label>Slug</label>
                <input type="text" id="pageSlug" class="form-control" placeholder="auto-generated">
              </div>
              <div class="form-group">
                <label>Status</label>
                <select id="pageStatus" class="form-control">
                  <option value="draft">Draft</option>
                  <option value="published">Published</option>
                </select>
              </div>
              <div class="form-group">
                <label>Content <small class="text-muted">(HTML supported)</small></label>
                <textarea id="pageContent" class="form-control" rows="10" placeholder="Page content…" style="font-family:monospace;font-size:0.9em"></textarea>
              </div>
              <hr>
              <details>
                <summary style="cursor:pointer;font-weight:600;padding:4px 0">SEO / Meta</summary>
                <div style="padding-top:12px">
                  <div class="form-group">
                    <label>Meta Title</label>
                    <input type="text" id="pageMetaTitle" class="form-control" placeholder="SEO title (defaults to page title)">
                  </div>
                  <div class="form-group">
                    <label>Meta Description</label>
                    <textarea id="pageMetaDescription" class="form-control" rows="3" placeholder="SEO description (max ~160 chars)"></textarea>
                  </div>
                </div>
              </details>
            </div>
            <div class="modal-footer">
              <button id="pageSaveBtn" class="btn btn-primary">Save</button>
              <button id="pageSaveDraftBtn" class="btn btn-secondary">Save as Draft</button>
              <button id="pageCancelBtn" class="btn btn-outline">Cancel</button>
            </div>
          </div>
        </div>`;
    },

    _bindEvents() {
      document.addEventListener('click', function (e) {
        if (e.target.id === 'pageCreateBtn') PagesModule.openCreate();
        if (e.target.id === 'pageSaveBtn') PagesModule.save('published');
        if (e.target.id === 'pageSaveDraftBtn') PagesModule.save('draft');
        if (e.target.id === 'pageCancelBtn' || e.target.id === 'pageModalClose' || e.target.id === 'pageModalBackdrop') hideModal();
        if (e.target.matches('.page-edit-btn')) PagesModule.openEdit(e.target.dataset.id);
        if (e.target.matches('.page-delete-btn')) PagesModule.remove(e.target.dataset.id);
        if (e.target.id === 'pageSearchBtn') PagesModule._applyFilters();
        if (e.target.matches('.pages-page-btn')) {
          state.page = parseInt(e.target.dataset.page, 10);
          PagesModule.load();
        }
      });
      document.addEventListener('input', function (e) {
        if (e.target.id === 'pageTitle') {
          const slugEl = document.getElementById('pageSlug');
          if (slugEl && !state.current) slugEl.value = slugify(e.target.value);
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.id === 'pageSearch') PagesModule._applyFilters();
      });
    },

    _applyFilters() {
      state.filters.search = (document.getElementById('pageSearch') || {}).value || '';
      state.filters.status = (document.getElementById('pageStatusFilter') || {}).value || '';
      state.page = 1;
      PagesModule.load();
    },

    async load() {
      setLoading(true);
      try {
        const params = new URLSearchParams({ page: state.page, per_page: state.perPage });
        if (state.filters.search) params.set('search', state.filters.search);
        if (state.filters.status) params.set('status', state.filters.status);
        const data = await apiFetch(`${apiUrl()}?${params}`);
        state.items = data.items || data.data || data || [];
        state.total = data.total || state.items.length;
        PagesModule.renderTable(state.items);
        PagesModule.renderPagination();
      } catch (err) {
        toast(err.message, 'error');
        const tb = document.getElementById('pagesTableBody');
        if (tb) tb.innerHTML = `<tr><td colspan="6" class="text-danger">${err.message}</td></tr>`;
      } finally {
        setLoading(false);
      }
    },

    renderTable(items) {
      const tb = document.getElementById('pagesTableBody');
      if (!tb) return;
      if (!items.length) {
        tb.innerHTML = '<tr><td colspan="6" class="text-center">No pages found.</td></tr>';
        return;
      }
      tb.innerHTML = items.map(p => {
        const statusBadge = p.status === 'published'
          ? '<span class="badge badge-success">Published</span>'
          : '<span class="badge badge-warning">Draft</span>';
        return `<tr>
          <td>${p.id}</td>
          <td><strong>${escHtml(p.title || '—')}</strong></td>
          <td><code>${escHtml(p.slug || '')}</code></td>
          <td>${statusBadge}</td>
          <td>${fmtDate(p.updated_at || p.created_at)}</td>
          <td class="actions">
            <button class="btn btn-sm btn-outline page-edit-btn" data-id="${p.id}">Edit</button>
            <button class="btn btn-sm btn-danger page-delete-btn" data-id="${p.id}">Delete</button>
          </td>
        </tr>`;
      }).join('');
    },

    renderPagination() {
      const el = document.getElementById('pagesPagination');
      if (!el) return;
      const pages = Math.ceil(state.total / state.perPage);
      if (pages <= 1) { el.innerHTML = ''; return; }
      let html = '';
      for (let i = 1; i <= pages; i++) {
        html += `<button class="btn btn-sm pages-page-btn${i === state.page ? ' btn-primary' : ' btn-outline'}" data-page="${i}">${i}</button>`;
      }
      el.innerHTML = `<div class="pagination">${html}</div>`;
    },

    openCreate() {
      state.current = null;
      state.mode = 'create';
      document.getElementById('pageModalTitle').textContent = 'Add Page';
      document.getElementById('pageTitle').value = '';
      document.getElementById('pageSlug').value = '';
      document.getElementById('pageStatus').value = 'draft';
      document.getElementById('pageContent').value = '';
      document.getElementById('pageMetaTitle').value = '';
      document.getElementById('pageMetaDescription').value = '';
      const draftBtn = document.getElementById('pageSaveDraftBtn');
      if (draftBtn) draftBtn.style.display = 'inline-block';
      showModal();
    },

    async openEdit(id) {
      try {
        const page = await apiFetch(apiUrl(`/${id}`));
        state.current = page;
        state.mode = 'edit';
        document.getElementById('pageModalTitle').textContent = 'Edit Page';
        document.getElementById('pageTitle').value = page.title || '';
        document.getElementById('pageSlug').value = page.slug || '';
        document.getElementById('pageStatus').value = page.status || 'draft';
        document.getElementById('pageContent').value = page.content || '';
        document.getElementById('pageMetaTitle').value = page.meta_title || '';
        document.getElementById('pageMetaDescription').value = page.meta_description || '';
        showModal();
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    async save(forceStatus) {
      const title = document.getElementById('pageTitle').value.trim();
      if (!title) { toast('Title is required.', 'error'); return; }
      const status = forceStatus || document.getElementById('pageStatus').value;
      const payload = {
        title,
        slug: document.getElementById('pageSlug').value.trim() || slugify(title),
        status,
        content: document.getElementById('pageContent').value,
        meta_title: document.getElementById('pageMetaTitle').value.trim() || null,
        meta_description: document.getElementById('pageMetaDescription').value.trim() || null,
      };
      const btn = document.getElementById('pageSaveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
      try {
        if (state.mode === 'edit' && state.current) {
          await apiFetch(apiUrl(`/${state.current.id}`), { method: 'PATCH', body: JSON.stringify(payload) });
          toast(`Page ${status === 'published' ? 'published' : 'saved as draft'}.`);
        } else {
          await apiFetch(apiUrl(), { method: 'POST', body: JSON.stringify(payload) });
          toast('Page created.');
        }
        hideModal();
        PagesModule.load();
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
      }
    },

    async remove(id) {
      if (!confirm('Delete this page? This cannot be undone.')) return;
      try {
        await apiFetch(apiUrl(`/${id}`), { method: 'DELETE' });
        toast('Page deleted.');
        PagesModule.load();
      } catch (err) {
        toast(err.message, 'error');
      }
    },
  };

  window.PagesModule = PagesModule;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => PagesModule.init());
  else PagesModule.init();
})();
