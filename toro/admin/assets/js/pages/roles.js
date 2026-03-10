(function () {
  'use strict';

  const CONFIG = window.ROLES_CONFIG || {};

  const state = {
    // Tab 1 – Roles & Permissions
    roles: [],
    permissions: [],
    permissionGroups: [],
    selectedRole: null,
    rolePermissions: [],
    rolesSearch: '',
    roleMode: 'create',
    currentRole: null,

    // Tab 2 – User Roles
    users: [],
    usersPage: 1,
    usersPerPage: 25,
    usersTotal: 0,
    usersSearch: '',
    selectedUser: null,
    userCurrentRoles: [],

    activeTab: 'roles',
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
  function rolesApiUrl(path) {
    return (CONFIG.apiUrl || '/api/roles') + (path || '');
  }
  function permApiUrl(path) {
    return (CONFIG.permissionsApiUrl || '/api/permissions') + (path || '');
  }
  function userRolesApiUrl(path) {
    return (CONFIG.userRolesApiUrl || '/api/user_roles') + (path || '');
  }
  function usersApiUrl(path) {
    return (CONFIG.usersApiUrl || '/api/users') + (path || '');
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
    return str.toLowerCase().trim().replace(/[\s_]+/g, '-').replace(/[^\w-]/g, '').replace(/--+/g, '-');
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
  const RolesModule = {
    init() {
      RolesModule._injectHTML();
      RolesModule._bindEvents();
      RolesModule._checkSuperAdmin();
      RolesModule._loadRolesTab();
    },

    _checkSuperAdmin() {
      if (!CONFIG.isSuperAdmin) {
        const warning = document.getElementById('rolesPermWarning');
        if (warning) warning.style.display = 'block';
      }
    },

    _injectHTML() {
      const container = document.getElementById('rolesContainer');
      if (!container) return;
      container.innerHTML = `
        <div class="page-header">
          <h2>Roles &amp; Permissions</h2>
        </div>

        <div id="rolesPermWarning" class="alert alert-warning" style="display:none;padding:12px 16px;background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;margin-bottom:16px">
          ⚠️ You do not have super-admin privileges. Some operations may be restricted.
        </div>

        <!-- Tabs -->
        <div class="tabs-bar" style="display:flex;gap:0;border-bottom:2px solid #e2e8f0;margin-bottom:20px">
          <button class="tab-btn active" id="tabRoles" data-tab="roles"
            style="padding:10px 20px;border:none;background:none;cursor:pointer;font-weight:600;border-bottom:2px solid #6366f1;margin-bottom:-2px">
            Roles &amp; Permissions
          </button>
          <button class="tab-btn" id="tabUserRoles" data-tab="user-roles"
            style="padding:10px 20px;border:none;background:none;cursor:pointer;color:#64748b;border-bottom:2px solid transparent;margin-bottom:-2px">
            User Role Assignment
          </button>
        </div>

        <!-- Tab 1: Roles & Permissions -->
        <div id="tabPanelRoles">
          <div style="display:grid;grid-template-columns:300px 1fr;gap:20px;min-height:400px">

            <!-- Roles List -->
            <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden">
              <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
                <strong>Roles</strong>
                <button id="roleCreateBtn" class="btn btn-sm btn-primary">+ New Role</button>
              </div>
              <div style="padding:8px">
                <input type="text" id="rolesSearch" class="form-control" placeholder="Search roles…" style="margin-bottom:8px">
                <div id="rolesListPanel"></div>
              </div>
            </div>

            <!-- Permissions Panel -->
            <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden">
              <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
                <strong id="rolePermPanelTitle">Select a role to manage permissions</strong>
                <button id="rolePermSaveBtn" class="btn btn-sm btn-primary" style="display:none">Save Permissions</button>
              </div>
              <div id="rolePermissionsPanel" style="padding:16px">
                <p class="text-muted">Select a role from the left.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Tab 2: User Role Assignment -->
        <div id="tabPanelUserRoles" style="display:none">
          <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;min-height:400px">

            <!-- Users list -->
            <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden">
              <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;gap:8px;align-items:center">
                <strong>Users</strong>
                <input type="text" id="userRolesSearch" class="form-control" placeholder="Search users…" style="max-width:220px;margin-left:8px">
                <button id="userRolesSearchBtn" class="btn btn-sm btn-secondary">Search</button>
              </div>
              <div id="userRolesTableContainer" style="padding:12px">Loading…</div>
              <div id="userRolesPagination" class="pagination-bar" style="padding:8px 12px"></div>
            </div>

            <!-- Role assignment panel -->
            <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden">
              <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0">
                <strong id="userRoleAssignTitle">Select a user</strong>
              </div>
              <div id="userRoleAssignPanel" style="padding:16px">
                <p class="text-muted">Select a user from the left to assign roles.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Role Modal -->
        <div id="roleModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="roleModalBackdrop"></div>
          <div class="modal-box">
            <div class="modal-header">
              <h3 id="roleModalTitle">Add Role</h3>
              <button class="modal-close" id="roleModalClose">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Name <span class="required">*</span></label>
                <input type="text" id="roleName" class="form-control" placeholder="Editor">
              </div>
              <div class="form-group">
                <label>Slug</label>
                <input type="text" id="roleSlug" class="form-control" placeholder="auto-generated">
              </div>
              <div class="form-group">
                <label>Description</label>
                <textarea id="roleDescription" class="form-control" rows="3" placeholder="Role description…"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button id="roleSaveBtn" class="btn btn-primary">Save</button>
              <button id="roleCancelBtn" class="btn btn-secondary">Cancel</button>
              <button id="roleDeleteBtn" class="btn btn-danger" style="display:none;margin-left:auto">Delete Role</button>
            </div>
          </div>
        </div>`;
    },

    _bindEvents() {
      document.addEventListener('click', function (e) {
        // Tabs
        if (e.target.matches('.tab-btn')) RolesModule._switchTab(e.target.dataset.tab, e.target);
        // Roles tab
        if (e.target.id === 'roleCreateBtn') RolesModule.openCreateRole();
        if (e.target.id === 'roleSaveBtn') RolesModule.saveRole();
        if (e.target.id === 'roleCancelBtn' || e.target.id === 'roleModalClose' || e.target.id === 'roleModalBackdrop') hideModal('roleModal');
        if (e.target.id === 'roleDeleteBtn') RolesModule.removeRole();
        if (e.target.matches('.roles-list-item')) RolesModule.selectRole(e.target.dataset.id);
        if (e.target.matches('.role-edit-btn')) RolesModule.openEditRole(e.target.dataset.id);
        if (e.target.id === 'rolePermSaveBtn') RolesModule.saveRolePermissions();
        // User roles tab
        if (e.target.id === 'userRolesSearchBtn') RolesModule._applyUsersSearch();
        if (e.target.matches('.user-roles-select-btn')) RolesModule.selectUser(e.target.dataset.id);
        if (e.target.id === 'userRoleSaveBtn') RolesModule.saveUserRoles();
        if (e.target.matches('.user-roles-page-btn')) {
          state.usersPage = parseInt(e.target.dataset.page, 10);
          RolesModule.loadUsers();
        }
      });
      document.addEventListener('input', function (e) {
        if (e.target.id === 'roleName') {
          const s = document.getElementById('roleSlug');
          if (s && !state.currentRole) s.value = slugify(e.target.value);
        }
        if (e.target.id === 'rolesSearch') RolesModule._filterRolesList();
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.id === 'userRolesSearch') RolesModule._applyUsersSearch();
      });
    },

    _switchTab(tab, btn) {
      state.activeTab = tab;
      document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('active');
        b.style.color = '#64748b';
        b.style.borderBottomColor = 'transparent';
        b.style.fontWeight = 'normal';
      });
      if (btn) {
        btn.classList.add('active');
        btn.style.color = '';
        btn.style.borderBottomColor = '#6366f1';
        btn.style.fontWeight = '600';
      }
      document.getElementById('tabPanelRoles').style.display = tab === 'roles' ? 'block' : 'none';
      document.getElementById('tabPanelUserRoles').style.display = tab === 'user-roles' ? 'block' : 'none';
      if (tab === 'user-roles' && !state.users.length) RolesModule.loadUsers();
    },

    /* ── Roles Tab ─── */
    async _loadRolesTab() {
      await Promise.all([RolesModule.loadRoles(), RolesModule.loadPermissions()]);
    },

    async loadRoles() {
      const panel = document.getElementById('rolesListPanel');
      if (panel) panel.innerHTML = '<p style="color:#64748b">Loading…</p>';
      try {
        const data = await apiFetch(`${rolesApiUrl()}?per_page=200`);
        state.roles = data.items || data.data || data || [];
        RolesModule.renderRolesList();
      } catch (err) {
        toast(err.message, 'error');
        if (panel) panel.innerHTML = `<p class="text-danger">${err.message}</p>`;
      }
    },

    async loadPermissions() {
      try {
        const data = await apiFetch(`${permApiUrl()}?per_page=500`);
        state.permissions = data.items || data.data || data || [];
        state.permissionGroups = [...new Set(state.permissions.map(p => (p.slug || '').split('.')[0]).filter(Boolean))].sort();
      } catch (_) { /* non-fatal */ }
    },

    renderRolesList() {
      const panel = document.getElementById('rolesListPanel');
      if (!panel) return;
      const search = state.rolesSearch.toLowerCase();
      const filtered = state.roles.filter(r => !search || (r.name || '').toLowerCase().includes(search));
      if (!filtered.length) {
        panel.innerHTML = '<p style="color:#64748b">No roles found.</p>';
        return;
      }
      panel.innerHTML = filtered.map(r => {
        const active = state.selectedRole && String(state.selectedRole.id) === String(r.id);
        const systemBadge = r.is_system ? ' <span class="badge badge-secondary" style="font-size:0.7rem">system</span>' : '';
        return `<div class="roles-list-item${active ? ' active' : ''}" data-id="${r.id}"
          style="padding:10px 12px;cursor:pointer;border-radius:6px;display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;${active ? 'background:#e0e7ff;font-weight:600' : ''}">
          <span class="roles-list-item" data-id="${r.id}" style="pointer-events:none">${escHtml(r.name || '')}${systemBadge}</span>
          <button class="btn btn-xs btn-outline role-edit-btn" data-id="${r.id}" style="pointer-events:all">✎</button>
        </div>`;
      }).join('');
    },

    _filterRolesList() {
      state.rolesSearch = (document.getElementById('rolesSearch') || {}).value || '';
      RolesModule.renderRolesList();
    },

    async selectRole(id) {
      const role = state.roles.find(r => String(r.id) === String(id));
      if (!role) return;
      state.selectedRole = role;
      RolesModule.renderRolesList();
      const titleEl = document.getElementById('rolePermPanelTitle');
      if (titleEl) titleEl.textContent = `Permissions: ${role.name}`;
      const saveBtn = document.getElementById('rolePermSaveBtn');
      if (saveBtn) saveBtn.style.display = 'inline-block';
      await RolesModule.loadRolePermissions(id);
      RolesModule.renderPermissionsPanel();
    },

    async loadRolePermissions(roleId) {
      try {
        const data = await apiFetch(rolesApiUrl(`/${roleId}/permissions`));
        state.rolePermissions = (data.items || data.data || data || []).map(p => String(p.id || p));
      } catch (_) {
        state.rolePermissions = [];
      }
    },

    renderPermissionsPanel() {
      const panel = document.getElementById('rolePermissionsPanel');
      if (!panel) return;
      if (!state.permissions.length) {
        panel.innerHTML = '<p class="text-muted">No permissions defined.</p>';
        return;
      }
      const byGroup = {};
      state.permissions.forEach(p => {
        const group = (p.slug || '').split('.')[0] || p.group || 'general';
        if (!byGroup[group]) byGroup[group] = [];
        byGroup[group].push(p);
      });
      panel.innerHTML = Object.entries(byGroup).map(([group, perms]) => `
        <div style="margin-bottom:16px">
          <div style="font-weight:700;text-transform:capitalize;font-size:0.9rem;margin-bottom:8px;padding-bottom:4px;border-bottom:1px solid #e2e8f0">${escHtml(group)}</div>
          <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:6px">
            ${perms.map(p => {
              const checked = state.rolePermissions.includes(String(p.id));
              const slug = p.slug || '';
              const action = slug.split('.')[1] || slug;
              return `<label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:4px 6px;border-radius:4px;hover:background:#f8fafc">
                <input type="checkbox" class="perm-checkbox" data-id="${p.id}" ${checked ? 'checked' : ''} style="width:15px;height:15px">
                <span style="font-size:0.85rem"><code>${escHtml(action)}</code>${p.description ? ` <span class="text-muted" style="font-size:0.8em">— ${escHtml(p.description)}</span>` : ''}</span>
              </label>`;
            }).join('')}
          </div>
        </div>`).join('');

      // "Select all / none" per group
      panel.insertAdjacentHTML('afterbegin', `
        <div style="margin-bottom:12px;display:flex;gap:8px">
          <button type="button" id="permSelectAll" class="btn btn-sm btn-outline">Select All</button>
          <button type="button" id="permSelectNone" class="btn btn-sm btn-outline">Select None</button>
        </div>`);

      document.getElementById('permSelectAll')?.addEventListener('click', () => {
        panel.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = true; });
      });
      document.getElementById('permSelectNone')?.addEventListener('click', () => {
        panel.querySelectorAll('.perm-checkbox').forEach(cb => { cb.checked = false; });
      });
    },

    async saveRolePermissions() {
      if (!state.selectedRole) return;
      const checked = [...document.querySelectorAll('.perm-checkbox:checked')].map(cb => parseInt(cb.dataset.id, 10));
      const btn = document.getElementById('rolePermSaveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
      try {
        await apiFetch(rolesApiUrl(`/${state.selectedRole.id}/permissions`), {
          method: 'PATCH',
          body: JSON.stringify({ permission_ids: checked }),
        });
        state.rolePermissions = checked.map(String);
        toast(`Permissions updated for "${state.selectedRole.name}".`);
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save Permissions'; }
      }
    },

    openCreateRole() {
      state.currentRole = null;
      state.roleMode = 'create';
      document.getElementById('roleModalTitle').textContent = 'Add Role';
      document.getElementById('roleName').value = '';
      document.getElementById('roleSlug').value = '';
      document.getElementById('roleDescription').value = '';
      document.getElementById('roleDeleteBtn').style.display = 'none';
      showModal('roleModal');
    },

    openEditRole(id) {
      const role = state.roles.find(r => String(r.id) === String(id));
      if (!role) return;
      state.currentRole = role;
      state.roleMode = 'edit';
      document.getElementById('roleModalTitle').textContent = 'Edit Role';
      document.getElementById('roleName').value = role.name || '';
      document.getElementById('roleSlug').value = role.slug || '';
      document.getElementById('roleDescription').value = role.description || '';
      const delBtn = document.getElementById('roleDeleteBtn');
      if (delBtn) delBtn.style.display = role.is_system ? 'none' : 'inline-block';
      showModal('roleModal');
    },

    async saveRole() {
      const name = document.getElementById('roleName').value.trim();
      if (!name) { toast('Name is required.', 'error'); return; }
      const payload = {
        name,
        slug: document.getElementById('roleSlug').value.trim() || slugify(name),
        description: document.getElementById('roleDescription').value.trim(),
      };
      const btn = document.getElementById('roleSaveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
      try {
        if (state.roleMode === 'edit' && state.currentRole) {
          await apiFetch(rolesApiUrl(`/${state.currentRole.id}`), { method: 'PATCH', body: JSON.stringify(payload) });
          toast('Role updated.');
        } else {
          await apiFetch(rolesApiUrl(), { method: 'POST', body: JSON.stringify(payload) });
          toast('Role created.');
        }
        hideModal('roleModal');
        RolesModule.loadRoles();
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
      }
    },

    async removeRole() {
      if (!state.currentRole) return;
      if (state.currentRole.is_system) { toast('System roles cannot be deleted.', 'error'); return; }
      if (!confirm(`Delete role "${state.currentRole.name}"? Users with this role will be unaffected but lose it.`)) return;
      try {
        await apiFetch(rolesApiUrl(`/${state.currentRole.id}`), { method: 'DELETE' });
        toast('Role deleted.');
        if (state.selectedRole && String(state.selectedRole.id) === String(state.currentRole.id)) {
          state.selectedRole = null;
          state.rolePermissions = [];
          const titleEl = document.getElementById('rolePermPanelTitle');
          if (titleEl) titleEl.textContent = 'Select a role to manage permissions';
          const saveBtn = document.getElementById('rolePermSaveBtn');
          if (saveBtn) saveBtn.style.display = 'none';
          const panel = document.getElementById('rolePermissionsPanel');
          if (panel) panel.innerHTML = '<p class="text-muted">Select a role from the left.</p>';
        }
        hideModal('roleModal');
        RolesModule.loadRoles();
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    /* ── User Roles Tab ─── */
    _applyUsersSearch() {
      state.usersSearch = (document.getElementById('userRolesSearch') || {}).value || '';
      state.usersPage = 1;
      RolesModule.loadUsers();
    },

    async loadUsers() {
      const container = document.getElementById('userRolesTableContainer');
      if (container) container.innerHTML = '<p>Loading…</p>';
      try {
        const params = new URLSearchParams({ page: state.usersPage, per_page: state.usersPerPage });
        if (state.usersSearch) params.set('search', state.usersSearch);
        const data = await apiFetch(`${usersApiUrl()}?${params}`);
        state.users = data.items || data.data || data || [];
        state.usersTotal = data.total || state.users.length;
        RolesModule.renderUsersTable();
        RolesModule.renderUsersPagination();
      } catch (err) {
        toast(err.message, 'error');
        if (container) container.innerHTML = `<p class="text-danger">${err.message}</p>`;
      }
    },

    renderUsersTable() {
      const container = document.getElementById('userRolesTableContainer');
      if (!container) return;
      if (!state.users.length) {
        container.innerHTML = '<p class="text-center text-muted">No users found.</p>';
        return;
      }
      const roleMap = Object.fromEntries(state.roles.map(r => [r.id, r.name]));
      container.innerHTML = `
        <table class="admin-table">
          <thead><tr><th>Name</th><th>Email</th><th>Current Role</th><th>Action</th></tr></thead>
          <tbody>
            ${state.users.map(u => {
              const selected = state.selectedUser && String(state.selectedUser.id) === String(u.id);
              return `<tr style="${selected ? 'background:#eff6ff' : ''}">
                <td>${escHtml(u.name || '—')}</td>
                <td><small>${escHtml(u.email || '')}</small></td>
                <td>${u.role_id ? escHtml(roleMap[u.role_id] || `#${u.role_id}`) : '<em>None</em>'}</td>
                <td><button class="btn btn-xs btn-outline user-roles-select-btn" data-id="${u.id}">Assign Roles</button></td>
              </tr>`;
            }).join('')}
          </tbody>
        </table>`;
    },

    renderUsersPagination() {
      const el = document.getElementById('userRolesPagination');
      if (!el) return;
      const pages = Math.ceil(state.usersTotal / state.usersPerPage);
      if (pages <= 1) { el.innerHTML = ''; return; }
      let html = '';
      for (let i = 1; i <= pages; i++) {
        html += `<button class="btn btn-xs user-roles-page-btn${i === state.usersPage ? ' btn-primary' : ' btn-outline'}" data-page="${i}">${i}</button>`;
      }
      el.innerHTML = `<div class="pagination">${html}</div>`;
    },

    async selectUser(id) {
      const user = state.users.find(u => String(u.id) === String(id));
      if (!user) return;
      state.selectedUser = user;
      RolesModule.renderUsersTable();
      const titleEl = document.getElementById('userRoleAssignTitle');
      if (titleEl) titleEl.textContent = `Assign Roles: ${user.name}`;
      await RolesModule.loadUserRoles(id);
      RolesModule.renderUserRoleAssign();
    },

    async loadUserRoles(userId) {
      try {
        const data = await apiFetch(userRolesApiUrl(`/${userId}`));
        state.userCurrentRoles = (data.roles || data.items || data || []).map(r => String(r.id || r));
      } catch (_) {
        state.userCurrentRoles = [];
      }
    },

    renderUserRoleAssign() {
      const panel = document.getElementById('userRoleAssignPanel');
      if (!panel) return;
      if (!state.roles.length) {
        panel.innerHTML = '<p class="text-muted">No roles available.</p>';
        return;
      }
      panel.innerHTML = `
        <div style="margin-bottom:12px">
          ${state.roles.map(r => {
            const checked = state.userCurrentRoles.includes(String(r.id));
            const systemBadge = r.is_system ? ' <span class="badge badge-secondary" style="font-size:0.7rem">system</span>' : '';
            return `<label style="display:flex;align-items:center;gap:10px;padding:8px 4px;cursor:pointer;border-bottom:1px solid #f1f5f9">
              <input type="checkbox" class="user-role-checkbox" data-role-id="${r.id}" ${checked ? 'checked' : ''} style="width:16px;height:16px">
              <span>
                <strong>${escHtml(r.name || '')}${systemBadge}</strong>
                ${r.description ? `<br><small class="text-muted">${escHtml(r.description)}</small>` : ''}
              </span>
            </label>`;
          }).join('')}
        </div>
        <button id="userRoleSaveBtn" class="btn btn-primary btn-sm">Save Role Assignments</button>`;
    },

    async saveUserRoles() {
      if (!state.selectedUser) return;
      const checked = [...document.querySelectorAll('.user-role-checkbox:checked')].map(cb => parseInt(cb.dataset.roleId, 10));
      const btn = document.getElementById('userRoleSaveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
      try {
        await apiFetch(userRolesApiUrl(`/${state.selectedUser.id}`), {
          method: 'PATCH',
          body: JSON.stringify({ role_ids: checked }),
        });
        state.userCurrentRoles = checked.map(String);
        toast(`Roles updated for "${state.selectedUser.name}".`);
        RolesModule.loadUsers();
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save Role Assignments'; }
      }
    },
  };

  window.RolesModule = RolesModule;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => RolesModule.init());
  else RolesModule.init();
})();
