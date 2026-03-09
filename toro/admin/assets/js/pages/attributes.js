(function () {
  'use strict';

  const CONFIG = window.ATTRIBUTES_CONFIG || {};

  const state = {
    groups: [],
    expandedGroups: new Set(),
    groupValues: {},
    currentGroup: null,
    currentValue: null,
    groupMode: 'create',
    valueMode: 'create',
    valueGroupId: null,
  };

  const GROUP_TYPES = ['select', 'checkbox', 'radio', 'color', 'size'];

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
  function groupApiUrl(path) {
    return (CONFIG.apiUrl || '/api/attribute_groups') + (path || '');
  }
  function valueApiUrl(path) {
    return (CONFIG.valuesApiUrl || '/api/attribute_values') + (path || '');
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
  function showModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.add('is-active'); m.style.display = 'flex'; }
  }
  function hideModal(id) {
    const m = document.getElementById(id);
    if (m) { m.classList.remove('is-active'); m.style.display = 'none'; }
  }

  /* ── Module ─────────────────────────────────────────────── */
  const AttributesModule = {
    init() {
      AttributesModule._injectHTML();
      AttributesModule._bindEvents();
      AttributesModule.load();
    },

    _injectHTML() {
      const container = document.getElementById('attributesContainer');
      if (!container) return;
      const typeOptions = GROUP_TYPES.map(t => `<option value="${t}">${t.charAt(0).toUpperCase() + t.slice(1)}</option>`).join('');
      container.innerHTML = `
        <div class="page-header">
          <h2>Attributes</h2>
          <button id="attrGroupCreateBtn" class="btn btn-primary">+ Add Attribute Group</button>
        </div>
        <div id="attrGroupsTable" class="attr-groups-table"></div>

        <!-- Group Modal -->
        <div id="attrGroupModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="attrGroupModalBackdrop"></div>
          <div class="modal-box">
            <div class="modal-header">
              <h3 id="attrGroupModalTitle">Add Attribute Group</h3>
              <button class="modal-close" id="attrGroupModalClose">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Name <span class="required">*</span></label>
                <input type="text" id="attrGroupName" class="form-control" placeholder="e.g. Color, Size">
              </div>
              <div class="form-group">
                <label>Slug</label>
                <input type="text" id="attrGroupSlug" class="form-control" placeholder="auto-generated">
              </div>
              <div class="form-group">
                <label>Type</label>
                <select id="attrGroupType" class="form-control">${typeOptions}</select>
              </div>
              <div class="form-group">
                <label>Sort Order</label>
                <input type="number" id="attrGroupSort" class="form-control" value="0" min="0">
              </div>
            </div>
            <div class="modal-footer">
              <button id="attrGroupSaveBtn" class="btn btn-primary">Save</button>
              <button id="attrGroupCancelBtn" class="btn btn-secondary">Cancel</button>
            </div>
          </div>
        </div>

        <!-- Value Modal -->
        <div id="attrValueModal" class="modal" style="display:none">
          <div class="modal-backdrop" id="attrValueModalBackdrop"></div>
          <div class="modal-box">
            <div class="modal-header">
              <h3 id="attrValueModalTitle">Add Attribute Value</h3>
              <button class="modal-close" id="attrValueModalClose">&times;</button>
            </div>
            <div class="modal-body">
              <div class="form-group">
                <label>Value <span class="required">*</span></label>
                <input type="text" id="attrValueValue" class="form-control" placeholder="e.g. Red, XL">
              </div>
              <div class="form-group">
                <label>Label (display)</label>
                <input type="text" id="attrValueLabel" class="form-control" placeholder="Human-readable label">
              </div>
              <div class="form-group" id="attrValueColorGroup">
                <label>Color Hex</label>
                <div style="display:flex;gap:8px;align-items:center">
                  <input type="color" id="attrValueColorPicker" value="#000000" style="width:40px;height:36px;padding:2px">
                  <input type="text" id="attrValueColorHex" class="form-control" placeholder="#000000" style="max-width:120px">
                </div>
              </div>
              <div class="form-group">
                <label>Sort Order</label>
                <input type="number" id="attrValueSort" class="form-control" value="0" min="0">
              </div>
            </div>
            <div class="modal-footer">
              <button id="attrValueSaveBtn" class="btn btn-primary">Save</button>
              <button id="attrValueCancelBtn" class="btn btn-secondary">Cancel</button>
            </div>
          </div>
        </div>`;
    },

    _bindEvents() {
      document.addEventListener('click', function (e) {
        // Group modal
        if (e.target.id === 'attrGroupCreateBtn') AttributesModule.openCreateGroup();
        if (e.target.id === 'attrGroupSaveBtn') AttributesModule.saveGroup();
        if (e.target.id === 'attrGroupCancelBtn' || e.target.id === 'attrGroupModalClose' || e.target.id === 'attrGroupModalBackdrop') hideModal('attrGroupModal');
        // Value modal
        if (e.target.id === 'attrValueSaveBtn') AttributesModule.saveValue();
        if (e.target.id === 'attrValueCancelBtn' || e.target.id === 'attrValueModalClose' || e.target.id === 'attrValueModalBackdrop') hideModal('attrValueModal');
        // Row actions
        if (e.target.matches('.attr-group-edit-btn')) AttributesModule.openEditGroup(e.target.dataset.id);
        if (e.target.matches('.attr-group-delete-btn')) AttributesModule.removeGroup(e.target.dataset.id);
        if (e.target.matches('.attr-group-toggle-btn')) AttributesModule.toggleGroup(e.target.dataset.id);
        if (e.target.matches('.attr-value-add-btn')) AttributesModule.openCreateValue(e.target.dataset.groupId);
        if (e.target.matches('.attr-value-edit-btn')) AttributesModule.openEditValue(e.target.dataset.id, e.target.dataset.groupId);
        if (e.target.matches('.attr-value-delete-btn')) AttributesModule.removeValue(e.target.dataset.id, e.target.dataset.groupId);
      });

      document.addEventListener('input', function (e) {
        if (e.target.id === 'attrGroupName') {
          const s = document.getElementById('attrGroupSlug');
          if (s && !state.currentGroup) s.value = slugify(e.target.value);
        }
        if (e.target.id === 'attrValueColorHex') {
          const picker = document.getElementById('attrValueColorPicker');
          if (picker && /^#[0-9a-fA-F]{6}$/.test(e.target.value)) picker.value = e.target.value;
        }
        if (e.target.id === 'attrValueColorPicker') {
          const hex = document.getElementById('attrValueColorHex');
          if (hex) hex.value = e.target.value;
        }
      });
    },

    async load() {
      const container = document.getElementById('attrGroupsTable');
      if (container) container.innerHTML = '<p>Loading…</p>';
      try {
        const data = await apiFetch(`${groupApiUrl()}?per_page=200`);
        state.groups = data.items || data.data || data || [];
        AttributesModule.renderGroups();
      } catch (err) {
        toast(err.message, 'error');
        if (container) container.innerHTML = `<p class="text-danger">${err.message}</p>`;
      }
    },

    renderGroups() {
      const container = document.getElementById('attrGroupsTable');
      if (!container) return;
      if (!state.groups.length) {
        container.innerHTML = '<p class="text-center">No attribute groups found.</p>';
        return;
      }
      container.innerHTML = `
        <table class="admin-table">
          <thead><tr><th></th><th>ID</th><th>Name</th><th>Slug</th><th>Type</th><th>Sort</th><th>Actions</th></tr></thead>
          <tbody id="attrGroupsTbody">${state.groups.map(g => AttributesModule._groupRow(g)).join('')}</tbody>
        </table>`;
      state.expandedGroups.forEach(gid => AttributesModule._renderInlineValues(gid));
    },

    _groupRow(g) {
      const isExpanded = state.expandedGroups.has(String(g.id));
      const toggleIcon = isExpanded ? '▾' : '▸';
      return `<tr class="attr-group-row" data-group-id="${g.id}">
        <td><button class="btn btn-xs attr-group-toggle-btn" data-id="${g.id}">${toggleIcon}</button></td>
        <td>${g.id}</td>
        <td><strong>${escHtml(g.name || '')}</strong></td>
        <td><code>${escHtml(g.slug || '')}</code></td>
        <td><span class="badge badge-info">${escHtml(g.type || '')}</span></td>
        <td>${g.sort_order ?? 0}</td>
        <td class="actions">
          <button class="btn btn-sm btn-outline attr-group-edit-btn" data-id="${g.id}">Edit</button>
          <button class="btn btn-sm btn-danger attr-group-delete-btn" data-id="${g.id}">Delete</button>
        </td>
      </tr>
      <tr class="attr-values-row" id="attrValuesRow_${g.id}" style="display:${isExpanded ? 'table-row' : 'none'}">
        <td colspan="7" style="padding:0 0 0 2rem;background:#fafafa">
          <div id="attrValuesContainer_${g.id}"></div>
        </td>
      </tr>`;
    },

    async toggleGroup(id) {
      const sid = String(id);
      const row = document.getElementById(`attrValuesRow_${id}`);
      const btn = document.querySelector(`.attr-group-toggle-btn[data-id="${id}"]`);
      if (state.expandedGroups.has(sid)) {
        state.expandedGroups.delete(sid);
        if (row) row.style.display = 'none';
        if (btn) btn.textContent = '▸';
      } else {
        state.expandedGroups.add(sid);
        if (row) row.style.display = 'table-row';
        if (btn) btn.textContent = '▾';
        await AttributesModule._loadValues(id);
        AttributesModule._renderInlineValues(id);
      }
    },

    async _loadValues(groupId) {
      try {
        const data = await apiFetch(`${valueApiUrl()}?group_id=${groupId}&per_page=200`);
        state.groupValues[groupId] = data.items || data.data || data || [];
      } catch (err) {
        toast(`Load values: ${err.message}`, 'error');
        state.groupValues[groupId] = [];
      }
    },

    _renderInlineValues(groupId) {
      const container = document.getElementById(`attrValuesContainer_${groupId}`);
      if (!container) return;
      const values = state.groupValues[groupId] || [];
      const group = state.groups.find(g => String(g.id) === String(groupId));
      const isColor = group && group.type === 'color';
      const rows = values.map(v => {
        const swatch = isColor && v.color_hex
          ? `<span style="display:inline-block;width:16px;height:16px;background:${escHtml(v.color_hex)};border:1px solid #ccc;border-radius:2px;vertical-align:middle"></span>`
          : '';
        return `<tr>
          <td>${v.id}</td>
          <td>${escHtml(v.value || '')}</td>
          <td>${escHtml(v.label || v.value || '')}</td>
          <td>${swatch} ${isColor ? `<code>${escHtml(v.color_hex || '')}</code>` : ''}</td>
          <td>${v.sort_order ?? 0}</td>
          <td>
            <button class="btn btn-xs btn-outline attr-value-edit-btn" data-id="${v.id}" data-group-id="${groupId}">Edit</button>
            <button class="btn btn-xs btn-danger attr-value-delete-btn" data-id="${v.id}" data-group-id="${groupId}">×</button>
          </td>
        </tr>`;
      }).join('');
      container.innerHTML = `
        <div style="padding:8px 0">
          <table class="admin-table" style="font-size:0.9em">
            <thead><tr><th>ID</th><th>Value</th><th>Label</th><th>Color</th><th>Sort</th><th>Actions</th></tr></thead>
            <tbody>${rows || '<tr><td colspan="6" class="text-center">No values yet.</td></tr>'}</tbody>
          </table>
          <button class="btn btn-sm btn-success attr-value-add-btn" data-group-id="${groupId}" style="margin:8px">+ Add Value</button>
        </div>`;
    },

    openCreateGroup() {
      state.currentGroup = null;
      state.groupMode = 'create';
      document.getElementById('attrGroupModalTitle').textContent = 'Add Attribute Group';
      document.getElementById('attrGroupName').value = '';
      document.getElementById('attrGroupSlug').value = '';
      document.getElementById('attrGroupType').value = 'select';
      document.getElementById('attrGroupSort').value = '0';
      showModal('attrGroupModal');
    },

    async openEditGroup(id) {
      try {
        const g = await apiFetch(groupApiUrl(`/${id}`));
        state.currentGroup = g;
        state.groupMode = 'edit';
        document.getElementById('attrGroupModalTitle').textContent = 'Edit Attribute Group';
        document.getElementById('attrGroupName').value = g.name || '';
        document.getElementById('attrGroupSlug').value = g.slug || '';
        document.getElementById('attrGroupType').value = g.type || 'select';
        document.getElementById('attrGroupSort').value = g.sort_order ?? 0;
        showModal('attrGroupModal');
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    async saveGroup() {
      const name = document.getElementById('attrGroupName').value.trim();
      if (!name) { toast('Name is required.', 'error'); return; }
      const payload = {
        name,
        slug: document.getElementById('attrGroupSlug').value.trim() || slugify(name),
        type: document.getElementById('attrGroupType').value,
        sort_order: parseInt(document.getElementById('attrGroupSort').value, 10) || 0,
      };
      const btn = document.getElementById('attrGroupSaveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
      try {
        if (state.groupMode === 'edit' && state.currentGroup) {
          await apiFetch(groupApiUrl(`/${state.currentGroup.id}`), { method: 'PATCH', body: JSON.stringify(payload) });
          toast('Group updated.');
        } else {
          await apiFetch(groupApiUrl(), { method: 'POST', body: JSON.stringify(payload) });
          toast('Group created.');
        }
        hideModal('attrGroupModal');
        AttributesModule.load();
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
      }
    },

    async removeGroup(id) {
      if (!confirm('Delete this attribute group and all its values?')) return;
      try {
        await apiFetch(groupApiUrl(`/${id}`), { method: 'DELETE' });
        toast('Group deleted.');
        state.expandedGroups.delete(String(id));
        delete state.groupValues[id];
        AttributesModule.load();
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    openCreateValue(groupId) {
      state.currentValue = null;
      state.valueMode = 'create';
      state.valueGroupId = groupId;
      document.getElementById('attrValueModalTitle').textContent = 'Add Attribute Value';
      document.getElementById('attrValueValue').value = '';
      document.getElementById('attrValueLabel').value = '';
      document.getElementById('attrValueColorHex').value = '';
      document.getElementById('attrValueColorPicker').value = '#000000';
      document.getElementById('attrValueSort').value = '0';
      const group = state.groups.find(g => String(g.id) === String(groupId));
      const colorGroup = document.getElementById('attrValueColorGroup');
      if (colorGroup) colorGroup.style.display = (group && group.type === 'color') ? 'block' : 'none';
      showModal('attrValueModal');
    },

    async openEditValue(id, groupId) {
      try {
        const v = await apiFetch(valueApiUrl(`/${id}`));
        state.currentValue = v;
        state.valueMode = 'edit';
        state.valueGroupId = groupId;
        document.getElementById('attrValueModalTitle').textContent = 'Edit Attribute Value';
        document.getElementById('attrValueValue').value = v.value || '';
        document.getElementById('attrValueLabel').value = v.label || '';
        document.getElementById('attrValueColorHex').value = v.color_hex || '';
        document.getElementById('attrValueColorPicker').value = v.color_hex || '#000000';
        document.getElementById('attrValueSort').value = v.sort_order ?? 0;
        const group = state.groups.find(g => String(g.id) === String(groupId));
        const colorGroup = document.getElementById('attrValueColorGroup');
        if (colorGroup) colorGroup.style.display = (group && group.type === 'color') ? 'block' : 'none';
        showModal('attrValueModal');
      } catch (err) {
        toast(err.message, 'error');
      }
    },

    async saveValue() {
      const value = document.getElementById('attrValueValue').value.trim();
      if (!value) { toast('Value is required.', 'error'); return; }
      const payload = {
        group_id: state.valueGroupId,
        value,
        label: document.getElementById('attrValueLabel').value.trim() || value,
        color_hex: document.getElementById('attrValueColorHex').value.trim() || null,
        sort_order: parseInt(document.getElementById('attrValueSort').value, 10) || 0,
      };
      const btn = document.getElementById('attrValueSaveBtn');
      if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
      try {
        if (state.valueMode === 'edit' && state.currentValue) {
          await apiFetch(valueApiUrl(`/${state.currentValue.id}`), { method: 'PATCH', body: JSON.stringify(payload) });
          toast('Value updated.');
        } else {
          await apiFetch(valueApiUrl(), { method: 'POST', body: JSON.stringify(payload) });
          toast('Value added.');
        }
        hideModal('attrValueModal');
        await AttributesModule._loadValues(state.valueGroupId);
        AttributesModule._renderInlineValues(state.valueGroupId);
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
      }
    },

    async removeValue(id, groupId) {
      if (!confirm('Delete this attribute value?')) return;
      try {
        await apiFetch(valueApiUrl(`/${id}`), { method: 'DELETE' });
        toast('Value deleted.');
        await AttributesModule._loadValues(groupId);
        AttributesModule._renderInlineValues(groupId);
      } catch (err) {
        toast(err.message, 'error');
      }
    },
  };

  window.AttributesModule = AttributesModule;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => AttributesModule.init());
  else AttributesModule.init();
})();
