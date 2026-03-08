/**
 * admin/assets/js/pages/banners.js
 * Banners management — rebuilt following brands.js / products.js pattern
 *
 * - Uses window.BANNERS_CONFIG, window.PAGE_PERMISSIONS, window.ADMIN_UI
 * - Images stored in unified images table (image_type_id = 9)
 * - Translations: EN required, AR optional (stored in banner_translations)
 * - Fully i18n-aware via window.BANNERS_TRANSLATIONS
 */
(function () {
    'use strict';

    // ═══════════════════════════════════════════════════════
    // CONFIG
    // ═══════════════════════════════════════════════════════
    const cfg = window.BANNERS_CONFIG || {};
    const API_URL      = cfg.apiUrl      || '/api/banners';
    const IMAGES_API   = cfg.imagesApi   || '/api/images';
    const IMAGE_TYPE_ID = cfg.imageTypeId || 9;
    const CSRF_TOKEN   = cfg.csrfToken   || window.CSRF_TOKEN || '';
    const LANG         = cfg.lang        || window.USER_LANGUAGE || 'en';
    const ITEMS_PER_PAGE = cfg.itemsPerPage || 25;

    const perms = window.PAGE_PERMISSIONS || {};
    const CAN_CREATE = !!perms.canCreate;
    const CAN_EDIT   = !!perms.canEdit;
    const CAN_DELETE = !!perms.canDelete;
    const IS_SUPER   = !!perms.isSuperAdmin;

    // ═══════════════════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════════════════
    let state = {
        items:           [],
        currentPage:     1,
        totalItems:      0,
        editingId:       null,
        selectedImageId: null,
        filters: {
            search:   '',
            position: '',
            status:   ''
        }
    };

    // ═══════════════════════════════════════════════════════
    // i18n
    // ═══════════════════════════════════════════════════════
    function t(key, fallback) {
        const tr = window.BANNERS_TRANSLATIONS || {};
        const val = key.split('.').reduce((o, k) => (o && o[k] !== undefined) ? o[k] : null, tr);
        if (val !== null && val !== undefined) return String(val);
        return fallback || key.split('.').pop().replace(/_/g, ' ');
    }

    // ═══════════════════════════════════════════════════════
    // DOM HELPERS
    // ═══════════════════════════════════════════════════════
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    // ═══════════════════════════════════════════════════════
    // FETCH HELPERS
    // ═══════════════════════════════════════════════════════
    async function apiFetch(url, opts = {}) {
        opts.credentials = opts.credentials || 'same-origin';
        if (!opts.headers) opts.headers = {};
        opts.headers['Accept'] = 'application/json';
        if (opts.method && opts.method !== 'GET') {
            opts.headers['X-CSRF-Token'] = CSRF_TOKEN;
        }
        const res = await fetch(url, opts);
        const text = await res.text();
        let json;
        try { json = JSON.parse(text); } catch (e) { throw new Error('Invalid JSON: ' + text.slice(0, 200)); }
        if (!res.ok) {
            const msg = (json && (json.message || json.error)) || `HTTP ${res.status}`;
            throw Object.assign(new Error(msg), { status: res.status, data: json });
        }
        return json;
    }

    async function apiGet(url) {
        return apiFetch(url);
    }

    async function apiPost(url, body) {
        return apiFetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
    }

    async function apiPut(url, body) {
        return apiFetch(url, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
    }

    async function apiDelete(url, body) {
        return apiFetch(url, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
    }

    // ═══════════════════════════════════════════════════════
    // TOAST
    // ═══════════════════════════════════════════════════════
    function showToast(msg, type = 'success') {
        const el = $('bannersToast');
        if (!el) return;
        el.innerHTML = `<div class="toast toast-${esc(type)}" style="
            padding:12px 20px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,.25);
            background:${type === 'success' ? 'var(--success-color,#10b981)' : 'var(--danger-color,#ef4444)'};
            color:#fff; font-size:0.9rem;">
            ${esc(msg)}
        </div>`;
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3500);
    }

    // ═══════════════════════════════════════════════════════
    // LOAD BANNERS
    // ═══════════════════════════════════════════════════════
    async function loadBanners() {
        const tbody = $('bannersTbody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:24px;">${esc(t('banners.loading', 'Loading...'))}</td></tr>`;
        }

        try {
            const params = new URLSearchParams();
            params.set('lang', LANG);
            params.set('all_translations', '1');
            if (state.filters.position) params.set('position', state.filters.position);
            if (state.filters.status !== '')  params.set('is_active', state.filters.status);

            const data = await apiGet(`${API_URL}?${params.toString()}`);
            // Support both array response and {data:[...]} wrapper
            const items = Array.isArray(data) ? data : (data.data || data.items || []);
            state.items      = items;
            state.totalItems = items.length;
            renderTable();
        } catch (err) {
            console.error('[Banners] loadBanners failed:', err);
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--danger-color,#ef4444);">${esc(t('messages.error.load_failed', 'Failed to load data'))}: ${esc(err.message)}</td></tr>`;
            }
        }
    }

    // ═══════════════════════════════════════════════════════
    // RENDER TABLE
    // ═══════════════════════════════════════════════════════
    async function renderTable() {
        const tbody = $('bannersTbody');
        if (!tbody) return;

        let items = state.items;

        // Client-side search filter
        const search = state.filters.search.trim().toLowerCase();
        if (search) {
            items = items.filter(b =>
                (b.title || '').toLowerCase().includes(search) ||
                (b.subtitle || '').toLowerCase().includes(search)
            );
        }

        if (!items.length) {
            tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:32px;">
                <div style="color:var(--text-secondary,#94a3b8);">
                    <i class="fas fa-image" style="font-size:2rem;margin-bottom:8px;display:block;"></i>
                    ${esc(t('table.empty.title', 'No Banners Found'))}
                </div>
            </td></tr>`;
            return;
        }

        const rows = await Promise.all(items.map(async (banner) => {
            // Fetch image for this banner
            let imgHtml = `<span style="color:var(--text-secondary,#94a3b8);">—</span>`;
            try {
                const imgData = await apiGet(`${IMAGES_API}/by_owner?owner_id=${banner.id}&image_type_id=${IMAGE_TYPE_ID}`);
                const images  = Array.isArray(imgData) ? imgData : (imgData.data || []);
                if (images.length) {
                    const img = images[0];
                    const url = img.url || img.thumb_url || '';
                    if (url) {
                        imgHtml = `<img src="${esc(url)}" alt="" style="width:80px;height:32px;object-fit:cover;border-radius:4px;">`;
                    }
                }
            } catch (_) { /* no image */ }

            const activeClass = banner.is_active ? 'badge-success' : 'badge-warning';
            const activeText  = banner.is_active
                ? t('table.status.active',   'Active')
                : t('table.status.inactive', 'Inactive');

            const dateStr = [banner.start_date, banner.end_date]
                .filter(Boolean)
                .map(d => d.slice(0, 10))
                .join(' → ') || '—';

            const editBtn   = CAN_EDIT   ? `<button class="btn btn-sm btn-outline btn-edit"   data-id="${banner.id}"><i class="fas fa-edit"></i></button>`   : '';
            const deleteBtn = CAN_DELETE ? `<button class="btn btn-sm btn-danger btn-delete"  data-id="${banner.id}"><i class="fas fa-trash"></i></button>` : '';

            return `<tr data-id="${banner.id}">
                <td style="padding:10px 12px;">${esc(banner.id)}</td>
                <td style="padding:10px 12px;">${imgHtml}</td>
                <td style="padding:10px 12px;">
                    <strong>${esc(banner.title || '')}</strong>
                    ${banner.subtitle ? `<br><small style="color:var(--text-secondary,#94a3b8);">${esc(banner.subtitle)}</small>` : ''}
                </td>
                <td style="padding:10px 12px;">${esc(banner.position || '')}</td>
                <td style="padding:10px 12px;">${esc(banner.sort_order ?? 0)}</td>
                <td style="padding:10px 12px;"><span class="badge ${activeClass}">${esc(activeText)}</span></td>
                <td style="padding:10px 12px; font-size:0.82rem;">${esc(dateStr)}</td>
                <td style="padding:10px 12px;">
                    <div style="display:flex;gap:4px;">
                        ${editBtn}
                        ${deleteBtn}
                    </div>
                </td>
            </tr>`;
        }));

        tbody.innerHTML = rows.join('');
        bindTableActions();
    }

    function bindTableActions() {
        document.querySelectorAll('#bannersTable .btn-edit').forEach(btn => {
            btn.addEventListener('click', () => openEditForm(parseInt(btn.dataset.id, 10)));
        });
        document.querySelectorAll('#bannersTable .btn-delete').forEach(btn => {
            btn.addEventListener('click', () => confirmDelete(parseInt(btn.dataset.id, 10)));
        });
    }

    // ═══════════════════════════════════════════════════════
    // FORM OPEN / CLOSE
    // ═══════════════════════════════════════════════════════
    function openAddForm() {
        resetForm();
        state.editingId      = null;
        state.selectedImageId = null;
        const titleEl = $('bannerFormTitle');
        if (titleEl) titleEl.textContent = t('form.add_title', 'Add Banner');
        const formContainer = $('bannerFormContainer');
        if (formContainer) {
            formContainer.style.display = 'block';
            formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    async function openEditForm(id) {
        resetForm();
        state.editingId = id;
        const titleEl = $('bannerFormTitle');
        if (titleEl) titleEl.textContent = t('form.edit_title', 'Edit Banner');
        const formContainer = $('bannerFormContainer');
        if (formContainer) formContainer.style.display = 'block';

        try {
            const data = await apiGet(`${API_URL}/${id}?all_translations=1`);
            const banner = data.data || data;
            populateForm(banner);
            if (formContainer) formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (err) {
            showToast(t('messages.error.load_failed', 'Failed to load data') + ': ' + err.message, 'error');
            if (formContainer) formContainer.style.display = 'none';
        }
    }

    function closeForm() {
        const formContainer = $('bannerFormContainer');
        if (formContainer) formContainer.style.display = 'none';
        resetForm();
    }

    function resetForm() {
        const form = $('bannerForm');
        if (form) form.reset();
        const idEl = $('formId');
        if (idEl) idEl.value = '';
        const imgId = $('bannerImageId');
        if (imgId) imgId.value = '';
        state.selectedImageId  = null;
        state.editingId        = null;
        // reset image preview
        const preview = $('bannerImagePreview');
        if (preview) preview.src = '/assets/images/no-image.png';
        const links = $('bannerImageLinks');
        if (links) links.innerHTML = '';
        // reset color inputs
        const bg = $('bannerBgColor');
        if (bg) bg.value = '#FFFFFF';
        const tc = $('bannerTextColor');
        if (tc) tc.value = '#000000';
        // reset translation fields
        ['en', 'ar'].forEach(l => {
            ['title', 'subtitle', 'link_text'].forEach(f => {
                const el = $(`trans_${l}_${f}`);
                if (el) el.value = '';
            });
        });
        // clear validation
        document.querySelectorAll('#bannerForm .is-invalid').forEach(el => el.classList.remove('is-invalid'));
    }

    function populateForm(banner) {
        const set = (id, val) => { const el = $(id); if (el) el.value = val == null ? '' : val; };

        set('formId',          banner.id);
        set('bannerTitle',     banner.title || '');
        set('bannerSubtitle',  banner.subtitle || '');
        set('bannerLinkUrl',   banner.link_url || '');
        set('bannerLinkText',  banner.link_text || '');
        set('bannerSortOrder', banner.sort_order ?? 0);
        set('bannerBgColor',   banner.background_color || '#FFFFFF');
        set('bannerTextColor', banner.text_color || '#000000');
        set('bannerButtonStyle', banner.button_style || '');

        // Position
        const posEl = $('bannerPosition');
        if (posEl) posEl.value = banner.position || 'homepage_main';

        // Status
        const statusEl = $('bannerIsActive');
        if (statusEl) statusEl.value = String(banner.is_active ?? 1);

        // Dates (convert to datetime-local format)
        if (banner.start_date) {
            const startEl = $('bannerStartDate');
            if (startEl) startEl.value = banner.start_date.slice(0, 16);
        }
        if (banner.end_date) {
            const endEl = $('bannerEndDate');
            if (endEl) endEl.value = banner.end_date.slice(0, 16);
        }

        // Translations
        const translations = banner.translations || {};
        ['en', 'ar'].forEach(l => {
            const lt = translations[l] || {};
            ['title', 'subtitle', 'link_text'].forEach(f => {
                const el = $(`trans_${l}_${f}`);
                if (el) el.value = lt[f] || '';
            });
        });

        // Load image
        loadBannerImage(banner.id);
    }

    async function loadBannerImage(bannerId) {
        try {
            const data = await apiGet(`${IMAGES_API}/by_owner?owner_id=${bannerId}&image_type_id=${IMAGE_TYPE_ID}`);
            const images = Array.isArray(data) ? data : (data.data || []);
            if (images.length) {
                const img = images[0];
                state.selectedImageId = img.id;
                const imgIdEl = $('bannerImageId');
                if (imgIdEl) imgIdEl.value = img.id;
                const preview = $('bannerImagePreview');
                if (preview && img.url) preview.src = img.url;
                const links = $('bannerImageLinks');
                if (links && img.url) {
                    links.innerHTML = `<a href="${esc(img.url)}" target="_blank" style="font-size:0.8rem;color:var(--primary-color,#3b82f6);">View</a>`;
                }
            }
        } catch (_) { /* no image found, that's ok */ }
    }

    // ═══════════════════════════════════════════════════════
    // FORM SUBMIT
    // ═══════════════════════════════════════════════════════
    async function handleFormSubmit(e) {
        e.preventDefault();

        // Validate EN title
        const enTitleEl = $('trans_en_title');
        if (!enTitleEl || !enTitleEl.value.trim()) {
            if (enTitleEl) enTitleEl.classList.add('is-invalid');
            showToast(t('messages.error.en_required', 'English title is required'), 'error');
            enTitleEl && enTitleEl.focus();
            return;
        }
        if (enTitleEl) enTitleEl.classList.remove('is-invalid');

        const saveBtn  = $('bannerSaveBtn');
        const saveTxt  = $('bannerSaveBtnText');
        if (saveBtn)  saveBtn.disabled = true;
        if (saveTxt)  saveTxt.textContent = t('form.buttons.saving', 'Saving...');

        try {
            const payload = buildPayload();

            let result;
            if (state.editingId) {
                result = await apiPut(`${API_URL}/${state.editingId}`, payload);
            } else {
                result = await apiPost(API_URL, payload);
            }

            const savedBanner = result.data || result;
            showToast(
                state.editingId
                    ? t('messages.success.updated', 'Banner updated successfully')
                    : t('messages.success.created', 'Banner created successfully'),
                'success'
            );
            closeForm();
            await loadBanners();
        } catch (err) {
            console.error('[Banners] save failed:', err);
            showToast(t('messages.error.save_failed', 'Failed to save data') + ': ' + err.message, 'error');
        } finally {
            if (saveBtn)  saveBtn.disabled = false;
            if (saveTxt)  saveTxt.textContent = t('form.buttons.save', 'Save');
        }
    }

    function buildPayload() {
        const get = (id) => { const el = $(id); return el ? el.value.trim() : ''; };

        // Build translations object
        const translations = {};
        ['en', 'ar'].forEach(l => {
            const title    = get(`trans_${l}_title`);
            const subtitle = get(`trans_${l}_subtitle`);
            const linkText = get(`trans_${l}_link_text`);
            if (title || subtitle || linkText) {
                translations[l] = { title, subtitle, link_text: linkText };
            }
        });

        // Use EN title as main title fallback
        const enTitle = (translations.en && translations.en.title) || get('bannerTitle');

        return {
            title:            enTitle,
            subtitle:         (translations.en && translations.en.subtitle) || get('bannerSubtitle'),
            link_url:         get('bannerLinkUrl'),
            link_text:        (translations.en && translations.en.link_text) || get('bannerLinkText'),
            position:         get('bannerPosition') || 'homepage_main',
            background_color: get('bannerBgColor') || '#FFFFFF',
            text_color:       get('bannerTextColor') || '#000000',
            button_style:     get('bannerButtonStyle'),
            sort_order:       parseInt(get('bannerSortOrder'), 10) || 0,
            is_active:        parseInt(get('bannerIsActive'), 10) ?? 1,
            start_date:       get('bannerStartDate') || null,
            end_date:         get('bannerEndDate') || null,
            image_id:         state.selectedImageId || null,
            translations:     translations
        };
    }

    // ═══════════════════════════════════════════════════════
    // DELETE
    // ═══════════════════════════════════════════════════════
    async function confirmDelete(id) {
        const confirmed = window.confirm(t('table.actions.confirm_delete', 'Are you sure you want to delete this banner?'));
        if (!confirmed) return;

        try {
            await apiDelete(`${API_URL}/${id}`);
            showToast(t('messages.success.deleted', 'Banner deleted successfully'), 'success');
            await loadBanners();
        } catch (err) {
            console.error('[Banners] delete failed:', err);
            showToast(t('messages.error.delete_failed', 'Failed to delete data') + ': ' + err.message, 'error');
        }
    }

    // ═══════════════════════════════════════════════════════
    // IMAGE SELECT (Media Studio)
    // ═══════════════════════════════════════════════════════
    function openMediaStudio() {
        const tenantId  = (window.APP_CONFIG && window.APP_CONFIG.TENANT_ID) || 1;
        const ownerId   = state.editingId || 0;
        const src       = `/admin/fragments/media_studio.php?embedded=1&tenant_id=${tenantId}&owner_id=${ownerId}&image_type_id=${IMAGE_TYPE_ID}&mode=select`;
        const popup     = window.open(src, 'mediaStudio', 'width=900,height=650,scrollbars=yes,resizable=yes');
        if (popup) popup.focus();
    }

    function removeImage() {
        state.selectedImageId = null;
        const imgIdEl  = $('bannerImageId');
        if (imgIdEl)  imgIdEl.value = '';
        const preview = $('bannerImagePreview');
        if (preview)  preview.src = '/assets/images/no-image.png';
        const links   = $('bannerImageLinks');
        if (links)    links.innerHTML = '';
    }

    // Listen for media studio postMessage
    window.addEventListener('message', function (ev) {
        if (!ev.data || ev.data.type !== 'imageSelected') return;
        const img = ev.data.image;
        if (!img) return;
        state.selectedImageId = img.id;
        const imgIdEl = $('bannerImageId');
        if (imgIdEl) imgIdEl.value = img.id;
        const preview = $('bannerImagePreview');
        if (preview && img.url) preview.src = img.url;
        const links = $('bannerImageLinks');
        if (links && img.url) {
            links.innerHTML = `<a href="${esc(img.url)}" target="_blank" style="font-size:0.8rem;color:var(--primary-color,#3b82f6);">View</a>`;
        }
    });

    // ═══════════════════════════════════════════════════════
    // FILTER / SEARCH
    // ═══════════════════════════════════════════════════════
    function bindFilters() {
        const searchEl   = $('bannerSearch');
        const posEl      = $('bannerFilterPosition');
        const statusEl   = $('bannerFilterStatus');
        const refreshBtn = $('btnRefresh');

        if (searchEl) {
            searchEl.addEventListener('input', () => {
                state.filters.search = searchEl.value;
                renderTable();
            });
        }
        if (posEl) {
            posEl.addEventListener('change', () => {
                state.filters.position = posEl.value;
                loadBanners();
            });
        }
        if (statusEl) {
            statusEl.addEventListener('change', () => {
                state.filters.status = statusEl.value;
                loadBanners();
            });
        }
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => loadBanners());
        }
    }

    // ═══════════════════════════════════════════════════════
    // BIND UI EVENTS
    // ═══════════════════════════════════════════════════════
    function bindEvents() {
        const addBtn      = $('btnAddBanner');
        const closeBtn    = $('btnCloseForm');
        const cancelBtn   = $('btnCancelForm');
        const form        = $('bannerForm');
        const selectImgBtn = $('bannerSelectImageBtn');
        const removeImgBtn = $('bannerRemoveImageBtn');

        if (addBtn)       addBtn.addEventListener('click', openAddForm);
        if (closeBtn)     closeBtn.addEventListener('click', closeForm);
        if (cancelBtn)    cancelBtn.addEventListener('click', closeForm);
        if (form)         form.addEventListener('submit', handleFormSubmit);
        if (selectImgBtn) selectImgBtn.addEventListener('click', openMediaStudio);
        if (removeImgBtn) removeImgBtn.addEventListener('click', removeImage);

        bindFilters();
    }

    // ═══════════════════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════════════════
    async function init() {
        bindEvents();
        await loadBanners();
        console.log('[Banners] ✓ Initialized');
    }

    // Expose module
    window.Banners = { init };

    // Auto-init if DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // give translations a chance to load first
        setTimeout(init, 100);
    }

})();
