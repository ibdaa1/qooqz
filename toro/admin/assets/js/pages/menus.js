(function () {
  'use strict';

  const CONFIG = window.MENUS_CONFIG || {};

  const state = {
    menus: [],
    menuItems: [],
    selectedMenuId: null,
    currentMenu: null,
    currentItem: null,
    menuMode: 'create',
    itemMode: 'create',
  };

  const LOCATIONS = ['primary', 'secondary', 'footer', 'sidebar', 'mobile'];

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
  function menuApiUrl(path) {
    return (CONFIG.apiUrl || '/api/menus') + (path || '');
  }
  function itemApiUrl(path) {
    return (CONFIG.itemsApiUrl || '/api/menu_items') + (path || '');
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
  const MenusModule = {
    init() {
      MenusModule._injectHTML();
      MenusModule._bindEvents();
      MenusModule.loadMenus();
    },

    _injectHTML() {
      const container = document.getElementById('menusContainer');
      if (!container) return;
      const locationOpts = LOCATIONS.map(l => `<option value="${l}">${l.charAt(0).toUpperCase() + l.slice(1)}</option>`).join('');

      container.innerHTML = `
        <div class="page-header">
          <h2>Menus</h2>
        </div>
        <div class="menus-layout" style="display:grid;grid-template-columns:280px 1fr;gap:20px;min-height:400px">

          <!-- Left: menus list -->
          <div class="menus-sidebar" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden">
            <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
              <strong>Menus</strong>
              <button id="menuCreateBtn" class="btn btn-sm btn-primary">+ New</button>
            </div>
            <div id="menusListPanel" style="padding:8px"></div>
          </div>

          <!-- Right: items panel -->
          <div class="menu-items-panel" style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden">
            <div style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center">
              <strong id="menuItemsPanelTitle">Select a menu</strong>
              <button id="menuItemCreateBtn" class="btn btn-sm btn-success" style="display:none">+ Add Item</button>
            </div>
            <div id="menuItemsPanel" style="padding:12px">
              <p class="text-muted">Select a menu from the left to manage its items.</p>
            </div>
          </div>
        </div>

        <!-- Menu Modal -->
        <div id="menuModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="menuModalBackdrop"></div>
          <div class="modal-box">
            <div class="modal-header">
              <h3 id="menuModalTitle">Add Menu</h3>
              <button class="modal-close" id="menuModalClose">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Name <span class="required">*</span></label>
                <input type="text" id="menuName" class="form-control" placeholder="Main Navigation">
              </div>
              <div class="form-group">
                <label>Slug</label>
                <input type="text" id="menuSlug" class="form-control" placeholder="auto-generated">
              </div>
              <div class="form-group">
                <label>Location</label>
                <select id="menuLocation" class="form-control">${locationOpts}</select>
              </div>
              <div class="form-group form-check">
                <input type="checkbox" id="menuIsActive" checked>
                <label for="menuIsActive">Active</label>
              </div>
            </div>
            <div class="modal-footer">
              <button id="menuSaveBtn" class="btn btn-primary">Save</button>
              <button id="menuCancelBtn" class="btn btn-secondary">Cancel</button>
              <button id="menuDeleteBtn" class="btn btn-danger" style="display:none;margin-left:auto">Delete Menu</button>
            </div>
          </div>
        </div>

        <!-- Menu Item Modal -->
        <div id="menuItemModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="menuItemModalBackdrop"></div>
          <div class="modal-box">
            <div class="modal-header">
              <h3 id="menuItemModalTitle">Add Menu Item</h3>
              <button class="modal-close" id="menuItemModalClose">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Label <span class="required">*</span></label>
                <input type="text" id="menuItemLabel" class="form-control" placeholder="Home">
              </div>
              <div class="form-group">
                <label>URL <span class="required">*</span></label>
                <input type="text" id="menuItemUrl" class="form-control" placeholder="/about">
              </div>
              <div class="form-group">
                <label>Icon <small class="text-muted">(CSS class or emoji)</small></label>
                <input type="text" id="menuItemIcon" class="form-control" placeholder="fa fa-home">
              </div>
              <div class="form-group">
                <label>Parent Item</label>
                <select id="menuItemParentId" class="form-control">
                  <option value="">— Top Level —</option>
                </select>
              </div>
              <div class="form-group">
                <label>Sort Order</label>
                <input type="number" id="menuItemSort" class="form-control" value="0" min="0">
              </div>
              <div class="form-group">
                <label>Open in</label>
                <select id="menuItemTarget" class="form-control">
                  <option value="_self">Same tab (_self)</option>
                  <option value="_blank">New tab (_blank)</option>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button id="menuItemSaveBtn" class="btn btn-primary">Save</button>
              <button id="menuItemCancelBtn" class="btn btn-secondary">Cancel</button>
            </div>
          </div>
        </div>`;
    },

    _bindEvents() {
      document.addEventListener('click', function (e) {
        // Menus
        if (e.target.id === 'menuCreateBtn') MenusModule.openCreateMenu();
        if (e.target.id === 'menuSaveBtn') MenusModule.saveMenu();
        if (e.target.id === 'menuCancelBtn' || e.target.id === 'menuModalClose' || e.target.id === 'menuModalBackdrop') hideModal('menuModal');
        if (e.target.id === 'menuDeleteBtn') MenusModule.removeMenu();
        if (e.target.matches('.menus-list-item')) MenusModule.selectMenu(e.target.dataset.id);
        if (e.target.matches('.menu-edit-btn')) MenusModule.openEditMenu(e.target.dataset.id);
        // Items
        if (e.target.id === 'menuItemCreateBtn') MenusModule.openCreateItem();
        if (e.target.id === 'menuItemSaveBtn') MenusModule.saveItem();
        if (e.target.id === 'menuItemCancelBtn' || e.target.id === 'menuItemModalClose' || e.target.id === 'menuItemModalBackdrop') hideModal('menuItemModal');
        if (e.target.matches('.menu-item-edit-btn')) MenusModule.openEditItem(e.target.dataset.id);
        if (e.target.matches('.menu-item-delete-btn')) MenusModule.removeItem(e.target.dataset.id);
        if (e.target.matches('.menu-item-up-btn')) MenusModule.moveItem(e.target.dataset.id, -1);
        if (e.target.matches('.menu-item-down-btn')) MenusModule.moveItem(e.target.dataset.id, 1);
      });
      document.addEventListener('input', function (e) {
        if (e.target.id === 'menuName') {
          const s = document.getElementById('menuSlug');
          if (s && !state.currentMenu) s.value = slugify(e.target.value);
        }
      });
    },

    async loadMenus() {
      const panel = document.getElementById('menusListPanel');
      if (panel) panel.innerHTML = '<p style="padding:8px;color:#64748b">Loading…</p>';
      try {
        const data = await apiFetch(`${menuApiUrl()}?per_page=200`);
        state.menus = data.items || data.data || data || [];
        MenusModule.renderMenusList();
        if (state.selectedMenuId) MenusModule.selectMenu(state.selectedMenuId);
      } catch (err) {
        toast(err.message, 'error');
        if (panel) panel.innerHTML = `<p class="text-danger">${err.message}</p>`;
      }
    },

    renderMenusList() {
      const panel = document.getElementById('menusListPanel');
      if (!panel) return;
      if (!state.menus.length) {
        panel.innerHTML = '<p style="padding:8px;color:#64748b">No menus yet.</p>';
        return;
      }
      panel.innerHTML = state.menus.map(m => {
        const active = String(m.id) === String(state.selectedMenuId);
        return `<div class="menus-list-item${active ? ' active' : ''}" data-id="${m.id}"
          style="padding:10px 12px;cursor:pointer;border-radius:6px;display:flex;justify-content:space-between;align-items:center;margin-bottom:2px;${active ? 'background:#e0e7ff;font-weight:600' : 'background:transparent'}">
          <span class="menus-list-item" data-id="${m.id}" style="pointer-events:none">${escHtml(m.name || '')} <small style="color:#64748b">${escHtml(m.location || '')}</small></span>
          <button class="btn btn-xs btn-outline menu-edit-btn" data-id="${m.id}" style="pointer-events:all">✎</button>
        </div>`;
      }).join('');
    },

    async selectMenu(id) {
      state.selectedMenuId = String(id);
      MenusModule.renderMenusList();
      const titleEl = document.getElementById('menuItemsPanelTitle');
      const addBtn = document.getElementById('menuItemCreateBtn');
      const menu = state.menus.find(m => String(m.id) === String(id));
      if (titleEl && menu) titleEl.textContent = `Items: ${menu.name}`;
      if (addBtn) addBtn.style.display = 'inline-block';
      await MenusModule.loadItems(id);
    },

    async loadItems(menuId) {
      const panel = document.getElementById('menuItemsPanel');
      if (panel) panel.innerHTML = '<p>Loading…</p>';
      try {
        const data = await apiFetch(`${itemApiUrl()}?menu_id=${menuId}&per_page=200`);
        state.menuItems = data.items || data.data || data || [];
        MenusModule.renderItems();
      } catch (err) {
        toast(err.message, 'error');
        if (panel) panel.innerHTML = `<p class="text-danger">${err.message}</p>`;
      }
    },

    renderItems() {
      const panel = document.getElementById('menuItemsPanel');
      if (!panel) return;
      const items = state.menuItems;
      if (!items.length) {
        panel.innerHTML = '<p class="text-muted">No items. Click "+ Add Item" to get started.</p>';
        return;
      }
      // Build tree: top-level first, then children
      const roots = items.filter(i => !i.parent_id);
      const children = {};
      items.filter(i => i.parent_id).forEach(i => {
        if (!children[i.parent_id]) children[i.parent_id] = [];
        children[i.parent_id].push(i);
      });

      function renderRow(item, depth = 0) {
        const indent = depth * 24;
        const childRows = (children[item.id] || [])
          .sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0))
          .map(c => renderRow(c, depth + 1)).join('');
        return `<tr>
          <td style="padding-left:${12 + indent}px">${escHtml(item.label || '')}</td>
          <td><a href="${escHtml(item.url || '#')}" target="_blank" rel="noopener" style="font-size:0.85em">${escHtml(item.url || '')}</a></td>
          <td>${item.icon ? escHtml(item.icon) : '—'}</td>
          <td>${item.target === '_blank' ? '<span class="badge badge-info">_blank</span>' : '_self'}</td>
          <td>${item.sort_order ?? 0}</td>
          <td class="actions">
            <button class="btn btn-xs btn-outline menu-item-up-btn" data-id="${item.id}" title="Move up">↑</button>
            <button class="btn btn-xs btn-outline menu-item-down-btn" data-id="${item.id}" title="Move down">↓</button>
            <button class="btn btn-xs btn-outline menu-item-edit-btn" data-id="${item.id}">Edit</button>
            <button class="btn btn-xs btn-danger menu-item-delete-btn" data-id="${item.id}">×</button>
          </td>
        </tr>${childRows}`;
      }

      panel.innerHTML = `
        <table class="admin-table">
          <thead><tr><th>Label</th><th>URL</th><th>Icon</th><th>Target</th><th>Sort</th><th>Actions</th></tr></thead>
          <tbody>${roots.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)).map(r => renderRow(r)).join('')}</tbody>
        </table>`;
    },

    _populateParentItemSelect(excludeId) {
      const select = document.getElementById('menuItemParentId');
      if (!select) return;
      const opts = state.menuItems
        .filter(i => !i.parent_id && String(i.id) !== String(excludeId))
        .map(i => `<option value="${i.id}">${escHtml(i.label || '')}</option>`)
        .join('');
      select.innerHTML = '<option value="">— Top Level —</option>' + opts;
    },

    openCreateMenu() {
      state.currentMenu = null;
      state.menuMode = 'create';
      document.getElementById('menuModalTitle').textContent = 'Add Menu';
      document.getElementById('menuName').value = '';
      document.getElementById('menuSlug').value = '';
      document.getElementById('menuLocation').value = LOCATIONS[0];
      document.getElementById('menuIsActive').checked = true;
      document.getElementById('menuDeleteBtn').style.display = 'none';
      showModal('menuModal');
    },

    openEditMenu(id) {
      const menu = state.menus.find(m => String(m.id) === String(id));
      if (!menu) return;
      state.currentMenu = menu;
      state.menuMode = 'edit';
      document.getElementById('menuModalTitle').textContent = 'Edit Menu';
      document.getElementById('menuName').value = menu.name || '';
      document.getElementById('menuSlug').value = menu.slug || '';
      document.getElementById('menuLocation').value = menu.location || LOCATIONS[0];
      document.getElementById('menuIsActive').checked = !!menu.is_active;
      document.getElementById('menuDeleteBtn').style.display = 'inline-block';
      showModal('menuModal');
    },

    async saveMenu() {
      const name = document.getElementById('menuName').value.trim();
      if (!name) { toast('Name is required.', 'error'); return; }
      const payload = {
        name,
        slug: document.getElementById('menuSlug').value.trim() || slugify(name),
        location: document.getElementById('menuLocation').value,
        is_active: document.getElementById('menuIsActive').checked,
      };
      const btn = document.getElementById('menuSaveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
      try {
        if (state.menuMode === 'edit' && state.currentMenu) {
          await apiFetch(menuApiUrl(`/${state.currentMenu.id}`), { method: 'PATCH', body: JSON.stringify(payload) });
          toast('Menu updated.');
        } else {
          await apiFetch(menuApiUrl(), { method: 'POST', body: JSON.stringify(payload) });
          toast('Menu created.');
        }
        hideModal('menuModal');
        MenusModule.loadMenus();
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
      }
    },

    async removeMenu() {
      if (!state.currentMenu) return;
      if (!confirm('Delete this menu and all its items?')) return;
      try {
        await apiFetch(menuApiUrl(`/${state.currentMenu.id}`), { method: 'DELETE' });
        toast('Menu deleted.');
        if (String(state.selectedMenuId) === String(state.currentMenu.id)) {
          state.selectedMenuId = null;
          const titleEl = document.getElementById('menuItemsPanelTitle');
          if (titleEl) titleEl.textContent = 'Select a menu';
          const addBtn = document.getElementById('menuItemCreateBtn');
          if (addBtn) addBtn.style.display = 'none';
          const panel = document.getElementById('menuItemsPanel');
          if (panel) panel.innerHTML = '<p class="text-muted">Select a menu from the left to manage its items.</p>';
        }
        hideModal('menuModal');
        MenusModule.loadMenus();
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    openCreateItem() {
      if (!state.selectedMenuId) { toast('Select a menu first.', 'error'); return; }
      state.currentItem = null;
      state.itemMode = 'create';
      document.getElementById('menuItemModalTitle').textContent = 'Add Menu Item';
      document.getElementById('menuItemLabel').value = '';
      document.getElementById('menuItemUrl').value = '';
      document.getElementById('menuItemIcon').value = '';
      document.getElementById('menuItemSort').value = '0';
      document.getElementById('menuItemTarget').value = '_self';
      MenusModule._populateParentItemSelect(null);
      showModal('menuItemModal');
    },

    openEditItem(id) {
      const item = state.menuItems.find(i => String(i.id) === String(id));
      if (!item) return;
      state.currentItem = item;
      state.itemMode = 'edit';
      document.getElementById('menuItemModalTitle').textContent = 'Edit Menu Item';
      document.getElementById('menuItemLabel').value = item.label || '';
      document.getElementById('menuItemUrl').value = item.url || '';
      document.getElementById('menuItemIcon').value = item.icon || '';
      document.getElementById('menuItemSort').value = item.sort_order ?? 0;
      document.getElementById('menuItemTarget').value = item.target || '_self';
      MenusModule._populateParentItemSelect(id);
      document.getElementById('menuItemParentId').value = item.parent_id || '';
      showModal('menuItemModal');
    },

    async saveItem() {
      const label = document.getElementById('menuItemLabel').value.trim();
      const url = document.getElementById('menuItemUrl').value.trim();
      if (!label) { toast('Label is required.', 'error'); return; }
      if (!url) { toast('URL is required.', 'error'); return; }
      const payload = {
        menu_id: state.selectedMenuId,
        label,
        url,
        icon: document.getElementById('menuItemIcon').value.trim() || null,
        parent_id: document.getElementById('menuItemParentId').value || null,
        sort_order: parseInt(document.getElementById('menuItemSort').value, 10) || 0,
        target: document.getElementById('menuItemTarget').value,
      };
      const btn = document.getElementById('menuItemSaveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
      try {
        if (state.itemMode === 'edit' && state.currentItem) {
          await apiFetch(itemApiUrl(`/${state.currentItem.id}`), { method: 'PATCH', body: JSON.stringify(payload) });
          toast('Item updated.');
        } else {
          await apiFetch(itemApiUrl(), { method: 'POST', body: JSON.stringify(payload) });
          toast('Item added.');
        }
        hideModal('menuItemModal');
        MenusModule.loadItems(state.selectedMenuId);
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
      }
    },

    async removeItem(id) {
      if (!confirm('Delete this menu item?')) return;
      try {
        await apiFetch(itemApiUrl(`/${id}`), { method: 'DELETE' });
        toast('Item deleted.');
        MenusModule.loadItems(state.selectedMenuId);
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    async moveItem(id, direction) {
      const items = state.menuItems;
      const idx = items.findIndex(i => String(i.id) === String(id));
      if (idx === -1) return;
      const item = items[idx];
      const siblings = items.filter(i => String(i.parent_id || '') === String(item.parent_id || '')).sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
      const sibIdx = siblings.findIndex(i => String(i.id) === String(id));
      const swapWith = siblings[sibIdx + direction];
      if (!swapWith) return;
      const newSort = swapWith.sort_order ?? 0;
      const oldSort = item.sort_order ?? 0;
      try {
        await Promise.all([
          apiFetch(itemApiUrl(`/${item.id}`), { method: 'PATCH', body: JSON.stringify({ sort_order: newSort }) }),
          apiFetch(itemApiUrl(`/${swapWith.id}`), { method: 'PATCH', body: JSON.stringify({ sort_order: oldSort }) }),
        ]);
        item.sort_order = newSort;
        swapWith.sort_order = oldSort;
        MenusModule.renderItems();
      } catch (err) {
        toast(err.message, 'error');
      }
    },
  };

  window.MenusModule = MenusModule;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => MenusModule.init());
  else MenusModule.init();
})();
