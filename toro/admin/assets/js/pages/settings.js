(function () {
  'use strict';

  const CONFIG = window.SETTINGS_CONFIG || {};

  const state = {
    settings: [],
    groups: [],
    dirty: new Set(),
    saving: new Set(),
    filters: { search: '', group: '' },
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
    return (CONFIG.apiUrl || '/api/settings') + (path || '');
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

  /* ── Module ─────────────────────────────────────────────── */
  const SettingsModule = {
    init() {
      SettingsModule._injectHTML();
      SettingsModule._bindEvents();
      SettingsModule.load();
    },

    _injectHTML() {
      const container = document.getElementById('settingsContainer');
      if (!container) return;
      container.innerHTML = `
        <div class="page-header">
          <h2>Settings</h2>
          <button id="settingsSaveAllBtn" class="btn btn-primary">Save All Changes</button>
        </div>
        <div class="filters-bar">
          <input type="text" id="settingsSearch" class="form-control" placeholder="Search by key or description…" style="max-width:280px">
          <select id="settingsGroupFilter" class="form-control" style="max-width:200px">
            <option value="">All Groups</option>
          </select>
          <button id="settingsSearchBtn" class="btn btn-secondary">Filter</button>
        </div>
        <div id="settingsBody" style="margin-top:16px">
          <p>Loading…</p>
        </div>`;
    },

    _bindEvents() {
      document.addEventListener('click', function (e) {
        if (e.target.id === 'settingsSaveAllBtn') SettingsModule.saveAll();
        if (e.target.id === 'settingsSearchBtn') SettingsModule._applyFilters();
        if (e.target.matches('.setting-save-btn')) SettingsModule.saveSingle(e.target.dataset.key);
      });
      document.addEventListener('input', function (e) {
        if (e.target.matches('.setting-input')) {
          const key = e.target.dataset.key;
          if (key) {
            state.dirty.add(key);
            SettingsModule._markDirty(key);
          }
        }
      });
      document.addEventListener('change', function (e) {
        if (e.target.matches('.setting-input')) {
          const key = e.target.dataset.key;
          if (key) {
            state.dirty.add(key);
            SettingsModule._markDirty(key);
          }
        }
        if (e.target.matches('.setting-color-picker')) {
          const key = e.target.dataset.key;
          const textInput = document.querySelector(`.setting-input[data-key="${CSS.escape(key)}"]`);
          if (textInput) {
            textInput.value = e.target.value;
            state.dirty.add(key);
            SettingsModule._markDirty(key);
          }
        }
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && e.target.id === 'settingsSearch') SettingsModule._applyFilters();
      });
    },

    _applyFilters() {
      state.filters.search = (document.getElementById('settingsSearch') || {}).value || '';
      state.filters.group = (document.getElementById('settingsGroupFilter') || {}).value || '';
      SettingsModule.renderSettings();
    },

    async load() {
      const body = document.getElementById('settingsBody');
      if (body) body.innerHTML = '<p>Loading…</p>';
      try {
        const data = await apiFetch(`${apiUrl()}?per_page=500`);
        state.settings = data.items || data.data || data || [];
        state.groups = [...new Set(state.settings.map(s => s.group).filter(Boolean))].sort();
        SettingsModule._populateGroupFilter();
        SettingsModule.renderSettings();
      } catch (err) {
        toast(err.message, 'error');
        if (body) body.innerHTML = `<p class="text-danger">${err.message}</p>`;
      }
    },

    _populateGroupFilter() {
      const el = document.getElementById('settingsGroupFilter');
      if (!el) return;
      el.innerHTML = '<option value="">All Groups</option>' +
        state.groups.map(g => `<option value="${escHtml(g)}">${escHtml(g)}</option>`).join('');
    },

    _filteredSettings() {
      const { search, group } = state.filters;
      return state.settings.filter(s => {
        const matchGroup = !group || s.group === group;
        const matchSearch = !search ||
          (s.key || '').toLowerCase().includes(search.toLowerCase()) ||
          (s.description || '').toLowerCase().includes(search.toLowerCase());
        return matchGroup && matchSearch;
      });
    },

    renderSettings() {
      const body = document.getElementById('settingsBody');
      if (!body) return;
      const filtered = SettingsModule._filteredSettings();
      if (!filtered.length) {
        body.innerHTML = '<p class="text-center text-muted">No settings found.</p>';
        return;
      }
      const byGroup = {};
      filtered.forEach(s => {
        const g = s.group || 'General';
        if (!byGroup[g]) byGroup[g] = [];
        byGroup[g].push(s);
      });
      body.innerHTML = Object.entries(byGroup).map(([group, settings]) => `
        <div class="settings-group" style="margin-bottom:24px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden">
          <div class="settings-group-header" style="padding:12px 16px;background:#f8fafc;border-bottom:1px solid #e2e8f0">
            <h4 style="margin:0;font-size:1rem;text-transform:capitalize">${escHtml(group)}</h4>
          </div>
          <div class="settings-group-body">
            ${settings.map(s => SettingsModule._renderSettingRow(s)).join('')}
          </div>
        </div>`).join('');
    },

    _renderSettingRow(s) {
      const inputHtml = SettingsModule._renderInput(s);
      const isDirty = state.dirty.has(s.key);
      return `<div class="setting-row" id="settingRow_${CSS.escape(s.key)}"
        style="display:flex;align-items:flex-start;gap:16px;padding:14px 16px;border-bottom:1px solid #f1f5f9">
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:0.9rem"><code>${escHtml(s.key)}</code></div>
          ${s.description ? `<div style="color:#64748b;font-size:0.82rem;margin-top:2px">${escHtml(s.description)}</div>` : ''}
          <div style="font-size:0.75rem;color:#94a3b8;margin-top:2px">Type: ${escHtml(s.type || 'text')}</div>
        </div>
        <div style="flex:1.5;display:flex;align-items:center;gap:8px">
          ${inputHtml}
        </div>
        <div style="display:flex;align-items:center;gap:4px">
          <button class="btn btn-sm btn-primary setting-save-btn" data-key="${escHtml(s.key)}" title="Save this setting">Save</button>
          ${isDirty ? '<span class="badge badge-warning" style="font-size:0.7rem">Unsaved</span>' : ''}
        </div>
      </div>`;
    },

    _renderInput(s) {
      const valEsc = escHtml(s.value ?? '');
      const key = escHtml(s.key);
      switch (s.type) {
        case 'boolean':
          return `<label style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" class="setting-input" data-key="${key}" ${s.value === 'true' || s.value === true || s.value === '1' ? 'checked' : ''} style="width:18px;height:18px">
            <span>${s.value === 'true' || s.value === true || s.value === '1' ? 'Enabled' : 'Disabled'}</span>
          </label>`;
        case 'number':
          return `<input type="number" class="setting-input form-control" data-key="${key}" value="${valEsc}" style="max-width:200px">`;
        case 'color':
          return `<input type="color" class="setting-color-picker" data-key="${key}" value="${valEsc || '#000000'}" style="width:40px;height:36px;padding:2px;border:1px solid #ddd;border-radius:4px">
            <input type="text" class="setting-input form-control" data-key="${key}" value="${valEsc}" placeholder="#000000" style="max-width:120px">`;
        case 'json':
          return `<textarea class="setting-input form-control" data-key="${key}" rows="4" style="font-family:monospace;font-size:0.82em;min-width:320px">${valEsc}</textarea>`;
        case 'image':
          return `<div style="width:100%">
            <input type="text" class="setting-input form-control" data-key="${key}" value="${valEsc}" placeholder="https://…/image.jpg" style="margin-bottom:4px">
            ${s.value ? `<img src="${valEsc}" alt="" style="height:40px;border-radius:4px;border:1px solid #e2e8f0">` : ''}
          </div>`;
        default:
          return `<input type="text" class="setting-input form-control" data-key="${key}" value="${valEsc}" style="min-width:280px">`;
      }
    },

    _markDirty(key) {
      const row = document.getElementById(`settingRow_${CSS.escape(key)}`);
      if (!row) return;
      const existing = row.querySelector('.badge-warning');
      if (!existing) {
        const btn = row.querySelector('.setting-save-btn');
        if (btn) {
          const badge = document.createElement('span');
          badge.className = 'badge badge-warning';
          badge.style.fontSize = '0.7rem';
          badge.textContent = 'Unsaved';
          btn.insertAdjacentElement('afterend', badge);
        }
      }
    },

    _getInputValue(key) {
      const setting = state.settings.find(s => s.key === key);
      if (!setting) return null;
      const input = document.querySelector(`.setting-input[data-key="${CSS.escape(key)}"]`);
      if (!input) return null;
      if (setting.type === 'boolean') return input.checked ? 'true' : 'false';
      return input.value;
    },

    async saveSingle(key) {
      const value = SettingsModule._getInputValue(key);
      if (value === null) return;

      const s = state.settings.find(s => s.key === key);
      if (s && s.type === 'json') {
        try { JSON.parse(value); } catch (_) {
          toast(`Invalid JSON for "${key}".`, 'error'); return;
        }
      }

      const btn = document.querySelector(`.setting-save-btn[data-key="${CSS.escape(key)}"]`);
      if (btn) { btn.disabled = true; btn.textContent = '…'; }
      state.saving.add(key);
      try {
        await apiFetch(apiUrl(`/${encodeURIComponent(key)}`), {
          method: 'PATCH',
          body: JSON.stringify({ value }),
        });
        const s = state.settings.find(s => s.key === key);
        if (s) s.value = value;
        state.dirty.delete(key);
        const row = document.getElementById(`settingRow_${CSS.escape(key)}`);
        if (row) { const badge = row.querySelector('.badge-warning'); if (badge) badge.remove(); }
        toast(`"${key}" saved.`);
      } catch (err) {
        toast(err.message, 'error');
      } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Save'; }
        state.saving.delete(key);
      }
    },

    async saveAll() {
      if (!state.dirty.size) { toast('No unsaved changes.', 'info'); return; }
      const keys = [...state.dirty];
      const btn = document.getElementById('settingsSaveAllBtn');
      if (btn) { btn.disabled = true; btn.textContent = `Saving ${keys.length}…`; }
      let successCount = 0;
      const errors = [];
      await Promise.all(keys.map(async key => {
        const value = SettingsModule._getInputValue(key);
        if (value === null) return;
        const s = state.settings.find(s => s.key === key);
        if (s && s.type === 'json') {
          try { JSON.parse(value); } catch (_) {
            errors.push(`"${key}": invalid JSON`); return;
          }
        }
        try {
          await apiFetch(apiUrl(`/${encodeURIComponent(key)}`), {
            method: 'PATCH',
            body: JSON.stringify({ value }),
          });
          if (s) s.value = value;
          state.dirty.delete(key);
          const row = document.getElementById(`settingRow_${CSS.escape(key)}`);
          if (row) { const badge = row.querySelector('.badge-warning'); if (badge) badge.remove(); }
          successCount++;
        } catch (err) {
          errors.push(`"${key}": ${err.message}`);
        }
      }));
      if (btn) { btn.disabled = false; btn.textContent = 'Save All Changes'; }
      if (errors.length) {
        toast(`${successCount} saved. Errors: ${errors.join('; ')}`, 'error');
      } else {
        toast(`${successCount} setting${successCount !== 1 ? 's' : ''} saved.`);
      }
    },
  };

  window.SettingsModule = SettingsModule;
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => SettingsModule.init());
  else SettingsModule.init();
})();
