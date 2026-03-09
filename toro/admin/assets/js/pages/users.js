(function () {
  'use strict';

  const CONFIG = window.USERS_CONFIG || {};

  const state = {
    page: 1,
    perPage: 25,
    total: 0,
    items: [],
    roles: [],
    filters: { search: '', role_id: '', status: '' },
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
    return (CONFIG.apiUrl || '/api/users') + (path || '');
  }
  function rolesApiUrl(path) {
    return (CONFIG.rolesApiUrl || '/api/roles') + (path || '');
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
  function initials(name) {
    return (name || '?').split(/\s+/).slice(0, 2).map(w => w[0]).join('').toUpperCase();
  }
  function avatarColor(name) {
    const colors = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981','#3b82f6','#ef4444','#14b8a6'];
    let hash = 0;
    for (const ch of (name || 'U')) hash = hash * 31 + ch.charCodeAt(0);
    return colors[Math.abs(hash) % colors.length];
  }
  function setLoading(on) {
    const el = document.getElementById('usersTableBody');
    if (el) el.style.opacity = on ? '0.5' : '1';
  }
  function showModal() {
    const m = document.getElementById('userModal');
    if (m) { m.classList.add('is-active'); m.style.display = 'flex'; }
  }
  function hideModal() {
    const m = document.getElementById('userModal');
    if (m) { m.classList.remove('is-active'); m.style.display = 'none'; }
  }

  /* ── Module ─────────────────────────────────────────────── */
  const UsersModule = {
    init() {
      UsersModule._injectHTML();
      UsersModule._bindEvents();
      UsersModule.loadRoles().then(() => UsersModule.load());
    },

    _injectHTML() {
      const container = document.getElementById('usersContainer');
      if (!container) return;
      container.innerHTML = `
        <div class="page-header">
          <h2>Users</h2>
          <button id="userCreateBtn" class="btn btn-primary">+ Add User</button>
        </div>
        <div class="filters-bar">
          <input type="text" id="userSearch" class="form-control" placeholder="Search name or email…" style="max-width:260px">
          <select id="userRoleFilter" class="form-control" style="max-width:180px">
            <option value="">All Roles</option>
          </select>
          <select id="userStatusFilter" class="form-control" style="max-width:160px">
            <option value="">All Statuses</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          <button id="userSearchBtn" class="btn btn-secondary">Search</button>
        </div>
        <div class="table-responsive">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Avatar</th>
                <th>Name / Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Status</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="usersTableBody"><tr><td colspan="7">Loading…</td></tr></tbody>
          </table>
        </div>
        <div id="usersPagination" class="pagination-bar"></div>

        <div id="userModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="userModalBackdrop"></div>
          <div class="modal-box">
            <div class="modal-header">
              <h3 id="userModalTitle">Add User</h3>
              <button class="modal-close" id="userModalClose">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Full Name <span class="required">*</span></label>
                <input type="text" id="userName" class="form-control" placeholder="Jane Doe">
              </div>
              <div class="form-group">
                <label>Email <span class="required">*</span></label>
                <input type="email" id="userEmail" class="form-control" placeholder="jane@example.com">
              </div>
              <div class="form-group">
                <label>Phone</label>
                <input type="tel" id="userPhone" class="form-control" placeholder="+1 555 000 0000">
              </div>
              <div class="form-group" id="userPasswordGroup">
                <label id="userPasswordLabel">Password <span class="required">*</span></label>
                <input type="password" id="userPassword" class="form-control" placeholder="Minimum 8 characters" autocomplete="new-password">
              </div>
              <div class="form-group">
                <label>Role</label>
                <select id="userRoleId" class="form-control">
                  <option value="">— No Role —</option>
                </select>
              </div>
              <div class="form-group form-check">
                <input type="checkbox" id="userIsActive" checked>
                <label for="userIsActive">Active</label>
              </div>
            </div>
            <div class="modal-footer">
              <button id="userSaveBtn" class="btn btn-primary">Save</button>
              <button id="userCancelBtn" class="btn btn-secondary">Cancel</button>
            </div>
          </div>
        </div>`;
    },

    _bindEvents() {
      document.addEventListener('click', function (e) {
        if (e.target.id === 'userCreateBtn') UsersModule.openCreate();
        if (e.target.id === 'userSaveBtn') UsersModule.save();
        if (e.target.id === 'userCancelBtn' || e.target.id === 'userModalClose' || e.target.id === 'userModalBackdrop') hideModal();
        if (e.target.matches('.user-edit-btn')) UsersModule.openEdit(e.target.dataset.id);
        if (e.target.matches('.user-delete-btn')) UsersModule.remove(e.target.dataset.id);
        if (e.target.id === 'userSearchBtn') UsersModule._applyFilters();
        if (e.target.matches('.users-page-btn')) {
          state.page = parseInt(e.target.dataset.page, 10);
          UsersModule.load();
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.id === 'userSearch') UsersModule._applyFilters();
      });
    },

    _applyFilters() {
      state.filters.search = (document.getElementById('userSearch') || {}).value || '';
      state.filters.role_id = (document.getElementById('userRoleFilter') || {}).value || '';
      state.filters.status = (document.getElementById('userStatusFilter') || {}).value || '';
      state.page = 1;
      UsersModule.load();
    },

    async loadRoles() {
      try {
        const data = await apiFetch(`${rolesApiUrl()}?per_page=200`);
        state.roles = data.items || data.data || data || [];
        UsersModule._populateRoleSelects();
      } catch (_) { /* non-fatal */ }
    },

    _populateRoleSelects() {
      const opts = state.roles.map(r =>
        `<option value="${r.id}">${escHtml(r.name || '')}</option>`
      ).join('');
      ['userRoleId', 'userRoleFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const placeholder = id === 'userRoleFilter' ? '<option value="">All Roles</option>' : '<option value="">— No Role —</option>';
        el.innerHTML = placeholder + opts;
      });
    },

    async load() {
      setLoading(true);
      try {
        const params = new URLSearchParams({ page: state.page, per_page: state.perPage });
        if (state.filters.search) params.set('search', state.filters.search);
        if (state.filters.role_id) params.set('role_id', state.filters.role_id);
        if (state.filters.status !== '') params.set('is_active', state.filters.status);
        const data = await apiFetch(`${apiUrl()}?${params}`);
        state.items = data.items || data.data || data || [];
        state.total = data.total || state.items.length;
        UsersModule.renderTable(state.items);
        UsersModule.renderPagination();
      } catch (err) {
        toast(err.message, 'error');
        const tb = document.getElementById('usersTableBody');
        if (tb) tb.innerHTML = `<tr><td colspan="7" class="text-danger">${err.message}</td></tr>`;
      } finally {
        setLoading(false);
      }
    },

    renderTable(items) {
      const tb = document.getElementById('usersTableBody');
      if (!tb) return;
      if (!items.length) {
        tb.innerHTML = '<tr><td colspan="7" class="text-center">No users found.</td></tr>';
        return;
      }
      const roleMap = Object.fromEntries(state.roles.map(r => [r.id, r.name]));
      tb.innerHTML = items.map(u => {
        const bgColor = avatarColor(u.name);
        const avatar = `<div style="width:36px;height:36px;border-radius:50%;background:${bgColor};color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600">${escHtml(initials(u.name))}</div>`;
        const roleName = u.role_id ? escHtml(roleMap[u.role_id] || `#${u.role_id}`) : '<em class="text-muted">None</em>';
        const status = u.is_active
          ? '<span class="badge badge-success">Active</span>'
          : '<span class="badge badge-secondary">Inactive</span>';
        return `<tr>
          <td>${avatar}</td>
          <td>
            <div>${escHtml(u.name || '—')}</div>
            <small class="text-muted">${escHtml(u.email || '')}</small>
          </td>
          <td>${escHtml(u.phone || '—')}</td>
          <td>${roleName}</td>
          <td>${status}</td>
          <td>${fmtDate(u.created_at)}</td>
          <td class="actions">
            <button class="btn btn-sm btn-outline user-edit-btn" data-id="${u.id}">Edit</button>
            <button class="btn btn-sm btn-danger user-delete-btn" data-id="${u.id}">Delete</button>
          </td>
        </tr>`;
      }).join('');
    },

    renderPagination() {
      const el = document.getElementById('usersPagination');
      if (!el) return;
      const pages = Math.ceil(state.total / state.perPage);
      if (pages <= 1) { el.innerHTML = ''; return; }
      let html = '';
      for (let i = 1; i <= pages; i++) {
        html += `<button class="btn btn-sm users-page-btn${i === state.page ? ' btn-primary' : ' btn-outline'}" data-page="${i}">${i}</button>`;
      }
      el.innerHTML = `<div class="pagination">${html}</div>`;
    },

    openCreate() {
      state.current = null;
      state.mode = 'create';
      document.getElementById('userModalTitle').textContent = 'Add User';
      document.getElementById('userName').value = '';
      document.getElementById('userEmail').value = '';
      document.getElementById('userPhone').value = '';
      document.getElementById('userPassword').value = '';
      document.getElementById('userRoleId').value = '';
      document.getElementById('userIsActive').checked = true;
      const pwdLabel = document.getElementById('userPasswordLabel');
      if (pwdLabel) pwdLabel.innerHTML = 'Password <span class="required">*</span>';
      document.getElementById('userPassword').required = true;
      showModal();
    },

    async openEdit(id) {
      try {
        const u = await apiFetch(apiUrl(`/${id}`));
        state.current = u;
        state.mode = 'edit';
        document.getElementById('userModalTitle').textContent = 'Edit User';
        document.getElementById('userName').value = u.name || '';
        document.getElementById('userEmail').value = u.email || '';
        document.getElementById('userPhone').value = u.phone || '';
        document.getElementById('userPassword').value = '';
        document.getElementById('userRoleId').value = u.role_id || '';
        document.getElementById('userIsActive').checked = !!u.is_active;
        const pwdLabel = document.getElementById('userPasswordLabel');
        if (pwdLabel) pwdLabel.innerHTML = 'Password <small class="text-muted">(leave blank to keep current)</small>';
        document.getElementById('userPassword').required = false;
        showModal();
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    async save() {
      const name = document.getElementById('userName').value.trim();
      const email = document.getElementById('userEmail').value.trim();
      const password = document.getElementById('userPassword').value;
      if (!name) { toast('Name is required.', 'error'); return; }
      if (!email) { toast('Email is required.', 'error'); return; }
      if (state.mode === 'create' && !password) { toast('Password is required for new users.', 'error'); return; }
      if (password && password.length < 8) { toast('Password must be at least 8 characters.', 'error'); return; }
      const payload = {
        name,
        email,
        phone: document.getElementById('userPhone').value.trim() || null,
        role_id: document.getElementById('userRoleId').value || null,
        is_active: document.getElementById('userIsActive').checked,
      };
      if (password) payload.password = password;
      const btn = document.getElementById('userSaveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
      try {
        if (state.mode === 'edit' && state.current) {
          await apiFetch(apiUrl(`/${state.current.id}`), { method: 'PATCH', body: JSON.stringify(payload) });
          toast('User updated.');
        } else {
          await apiFetch(apiUrl(), { method: 'POST', body: JSON.stringify(payload) });
          toast('User created.');
        }
        hideModal();
        UsersModule.load();
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
      }
    },

    async remove(id) {
      if (!confirm('Delete this user? This action cannot be undone.')) return;
      try {
        await apiFetch(apiUrl(`/${id}`), { method: 'DELETE' });
        toast('User deleted.');
        UsersModule.load();
      } catch (err) {
        toast(err.message, 'error');
      }
    },
  };

  window.UsersModule = UsersModule;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => UsersModule.init());
  else UsersModule.init();
})();
