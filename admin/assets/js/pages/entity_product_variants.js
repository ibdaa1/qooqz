/**
 * Entity Product Variants – Standalone Module
 * Manages entity products and their variants independently
 */
(function () {
    'use strict';

    const API = {
        entities:              '/api/entities',
        entityProducts:        '/api/entity_products',
        entityProductVariants: '/api/entity_product_variants',
        products:              '/api/products',
        productVariants:       '/api/product_variants'
    };

    const state = {
        language:       'en',
        tenantId:       0,
        entityId:       0,
        isSuperAdmin:   false,
        canManage:      false,
        entityProducts: [],
        entityVariants: [],
        allEntities:    [],
        activeTab:      'epv-products'
    };

    let el = {};
    let translations = {};

    // ════════════════════════════════════════
    // INIT
    // ════════════════════════════════════════
    function init() {
        cacheElements();
        state.language     = (el.lang && el.lang.value) || 'en';
        state.tenantId     = parseInt(el.tenantId?.value) || 0;
        state.entityId     = parseInt(el.entityId?.value) || 0;
        state.isSuperAdmin = el.isSuperAdmin?.value === '1';
        state.canManage    = el.canManage?.value === '1';

        loadTranslations(state.language).then(() => {
            initTabs();
            initEventListeners();
            loadEntities();
            if (state.entityId > 0) {
                showEntityContent();
                loadEntityProducts();
            }
        });
    }

    function cacheElements() {
        el = {
            container:            document.getElementById('epvPageContainer'),
            lang:                 document.getElementById('epvLang'),
            tenantId:             document.getElementById('epvTenantId'),
            entityId:             document.getElementById('epvEntityId'),
            canManage:            document.getElementById('epvCanManage'),
            isSuperAdmin:         document.getElementById('epvIsSuperAdmin'),
            csrf:                 document.getElementById('epvCsrfToken'),

            // Entity filter
            entityFilter:         document.getElementById('epvEntityFilter'),
            tenantIdInput:        document.getElementById('epvTenantIdInput'),
            btnVerifyTenant:      document.getElementById('epvBtnVerifyTenant'),
            tenantNameDisplay:    document.getElementById('epvTenantNameDisplay'),

            // Tabs
            tabs:                 document.getElementById('epvTabs'),
            tabProducts:          document.getElementById('tab-epv-products'),
            tabVariants:          document.getElementById('tab-epv-variants'),

            // Products
            productSearch:        document.getElementById('epvProductSearch'),
            btnAddProduct:        document.getElementById('epvBtnAddProduct'),
            productsList:         document.getElementById('epvProductsList'),
            productsEmpty:        document.getElementById('epvProductsEmpty'),
            productsFooter:       document.getElementById('epvProductsFooter'),
            btnSaveProducts:      document.getElementById('epvBtnSaveProducts'),

            // Products Modal
            productsModal:         document.getElementById('epvProductsModal'),
            closeProductsModal:    document.getElementById('epvCloseProductsModal'),
            modalProductSearch:    document.getElementById('epvModalProductSearch'),
            selectAllProducts:     document.getElementById('epvSelectAllProducts'),
            deselectAllProducts:   document.getElementById('epvDeselectAllProducts'),
            productSelectedCount:  document.getElementById('epvProductSelectedCount'),
            modalProductsList:     document.getElementById('epvModalProductsList'),
            confirmProductSel:     document.getElementById('epvConfirmProductSelection'),
            cancelProductSel:      document.getElementById('epvCancelProductSelection'),

            // Variants
            variantProductFilter:  document.getElementById('epvVariantProductFilter'),
            variantSearch:         document.getElementById('epvVariantSearch'),
            btnAddVariant:         document.getElementById('epvBtnAddVariant'),
            variantsList:          document.getElementById('epvVariantsList'),
            variantsEmpty:         document.getElementById('epvVariantsEmpty'),
            variantsFooter:        document.getElementById('epvVariantsFooter'),
            btnSaveVariants:       document.getElementById('epvBtnSaveVariants'),

            // Variants Modal
            variantsModal:          document.getElementById('epvVariantsModal'),
            closeVariantsModal:     document.getElementById('epvCloseVariantsModal'),
            modalVarProductFilter:  document.getElementById('epvModalVariantProductFilter'),
            selectAllVariants:      document.getElementById('epvSelectAllVariants'),
            deselectAllVariants:    document.getElementById('epvDeselectAllVariants'),
            variantSelectedCount:   document.getElementById('epvVariantSelectedCount'),
            modalVariantsList:      document.getElementById('epvModalVariantsList'),
            confirmVariantSel:      document.getElementById('epvConfirmVariantSelection'),
            cancelVariantSel:       document.getElementById('epvCancelVariantSelection')
        };
    }

    // ════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════
    async function loadTranslations(lang) {
        try {
            const res = await fetch('/languages/EntityProductVariants/' + encodeURIComponent(lang) + '.json');
            if (res.ok) {
                const json = await res.json();
                translations = json.strings || {};
            }
        } catch (e) {
            console.warn('Failed to load translations:', e);
        }
    }

    function t(key, fallback) {
        const keys = key.split('.');
        let val = translations;
        for (const k of keys) {
            if (val && typeof val === 'object' && k in val) {
                val = val[k];
            } else {
                return fallback || key;
            }
        }
        return typeof val === 'string' ? val : (fallback || key);
    }

    // ════════════════════════════════════════
    // API HELPERS
    // ════════════════════════════════════════
    async function apiCall(url, options = {}) {
        const csrf = el.csrf?.value || '';
        const headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
        if (csrf) headers['X-CSRF-Token'] = csrf;
        options.headers = { ...headers, ...(options.headers || {}) };
        if (options.body && typeof options.body === 'object') {
            options.body = JSON.stringify(options.body);
        }
        const res = await fetch(url, options);
        return res.json();
    }

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + (type || 'success');
        toast.textContent = message;
        if (el.container) el.container.appendChild(toast);
        setTimeout(() => toast.remove(), 3500);
    }

    // ════════════════════════════════════════
    // TABS
    // ════════════════════════════════════════
    function initTabs() {
        const tabBtns = el.container?.querySelectorAll('.tab-btn');
        if (!tabBtns) return;
        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.dataset.tab;
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                if (el.tabProducts) el.tabProducts.style.display = tabId === 'epv-products' ? '' : 'none';
                if (el.tabVariants) el.tabVariants.style.display = tabId === 'epv-variants' ? '' : 'none';
                state.activeTab = tabId;
                if (tabId === 'epv-variants' && state.entityId) {
                    loadEntityVariants();
                }
            });
        });
    }

    function showEntityContent() {
        if (el.tabs) el.tabs.style.display = '';
        if (el.tabProducts) el.tabProducts.style.display = state.activeTab === 'epv-products' ? '' : 'none';
        if (el.tabVariants) el.tabVariants.style.display = state.activeTab === 'epv-variants' ? '' : 'none';
    }

    function hideEntityContent() {
        if (el.tabs) el.tabs.style.display = 'none';
        if (el.tabProducts) el.tabProducts.style.display = 'none';
        if (el.tabVariants) el.tabVariants.style.display = 'none';
    }

    // ════════════════════════════════════════
    // EVENT LISTENERS
    // ════════════════════════════════════════
    function initEventListeners() {
        // Entity filter
        if (el.entityFilter) {
            el.entityFilter.addEventListener('change', function () {
                state.entityId = parseInt(this.value) || 0;
                if (state.entityId > 0) {
                    showEntityContent();
                    loadEntityProducts();
                    state.entityVariants = [];
                    renderEntityVariants();
                } else {
                    hideEntityContent();
                    state.entityProducts = [];
                    state.entityVariants = [];
                }
            });
        }

        // Super admin tenant filter
        if (el.btnVerifyTenant) {
            el.btnVerifyTenant.addEventListener('click', verifyTenant);
        }

        // Product CRUD
        if (el.btnAddProduct) el.btnAddProduct.addEventListener('click', openProductsModal);
        if (el.btnSaveProducts) el.btnSaveProducts.addEventListener('click', saveEntityProducts);

        // Products Modal
        if (el.closeProductsModal) el.closeProductsModal.addEventListener('click', closeProductsModal);
        if (el.cancelProductSel) el.cancelProductSel.addEventListener('click', closeProductsModal);
        if (el.confirmProductSel) el.confirmProductSel.addEventListener('click', confirmProductSelection);
        if (el.selectAllProducts) el.selectAllProducts.addEventListener('click', () => toggleAllModalProducts(true));
        if (el.deselectAllProducts) el.deselectAllProducts.addEventListener('click', () => toggleAllModalProducts(false));
        if (el.modalProductSearch) el.modalProductSearch.addEventListener('input', filterModalProducts);

        // Variant CRUD
        if (el.btnAddVariant) el.btnAddVariant.addEventListener('click', openVariantsModal);
        if (el.btnSaveVariants) el.btnSaveVariants.addEventListener('click', saveEntityVariants);

        // Variants Modal
        if (el.closeVariantsModal) el.closeVariantsModal.addEventListener('click', closeVariantsModal);
        if (el.cancelVariantSel) el.cancelVariantSel.addEventListener('click', closeVariantsModal);
        if (el.confirmVariantSel) el.confirmVariantSel.addEventListener('click', confirmVariantSelection);
        if (el.selectAllVariants) el.selectAllVariants.addEventListener('click', () => toggleAllModalVariants(true));
        if (el.deselectAllVariants) el.deselectAllVariants.addEventListener('click', () => toggleAllModalVariants(false));
        if (el.modalVarProductFilter) el.modalVarProductFilter.addEventListener('change', loadModalVariants);

        // Variant product filter (main)
        if (el.variantProductFilter) el.variantProductFilter.addEventListener('change', filterVariantsByProduct);

        // Search
        if (el.productSearch) el.productSearch.addEventListener('input', renderEntityProducts);
        if (el.variantSearch) el.variantSearch.addEventListener('input', renderEntityVariants);
    }

    // ════════════════════════════════════════
    // ENTITY LOADING
    // ════════════════════════════════════════
    async function loadEntities() {
        try {
            let url = API.entities + '?limit=500';
            if (state.tenantId > 0) url += '&tenant_id=' + state.tenantId;
            const res = await apiCall(url);
            const items = res?.data?.items || res?.data || [];
            state.allEntities = items;
            populateEntityDropdown(items);
        } catch (e) {
            console.error('Failed to load entities:', e);
        }
    }

    function populateEntityDropdown(entities) {
        if (!el.entityFilter) return;
        const currentVal = el.entityFilter.value;
        el.entityFilter.innerHTML = '<option value="">' + t('filter.select_entity', 'Select Entity...') + '</option>';
        entities.forEach(e => {
            const name = e.name || e.store_name || ('Entity #' + e.id);
            const opt = document.createElement('option');
            opt.value = e.id;
            opt.textContent = name + (e.branch_code ? ' (' + e.branch_code + ')' : '');
            el.entityFilter.appendChild(opt);
        });
        if (state.entityId > 0) {
            el.entityFilter.value = state.entityId;
        }
    }

    async function verifyTenant() {
        const tid = parseInt(el.tenantIdInput?.value) || 0;
        if (tid <= 0) return;
        state.tenantId = tid;
        if (el.tenantNameDisplay) {
            el.tenantNameDisplay.style.display = 'block';
            el.tenantNameDisplay.textContent = 'Tenant #' + tid;
        }
        state.entityId = 0;
        hideEntityContent();
        await loadEntities();
    }

    // ════════════════════════════════════════
    // ENTITY PRODUCTS
    // ════════════════════════════════════════
    async function loadEntityProducts() {
        if (!state.entityId) return;
        try {
            const res = await apiCall(API.entityProducts + '?action=entity&entity_id=' + state.entityId);
            state.entityProducts = res?.data || [];
            renderEntityProducts();
            populateVariantProductFilters();
        } catch (e) {
            console.error('Failed to load entity products:', e);
            showToast(t('messages.load_failed', 'Failed to load'), 'error');
        }
    }

    function renderEntityProducts() {
        if (!el.productsList) return;
        const searchVal = (el.productSearch?.value || '').toLowerCase();
        let items = state.entityProducts;
        if (searchVal) {
            items = items.filter(p =>
                (p.product_name || '').toLowerCase().includes(searchVal) ||
                (p.sku || '').toLowerCase().includes(searchVal)
            );
        }

        if (items.length === 0) {
            el.productsList.innerHTML = '';
            if (el.productsEmpty) el.productsEmpty.style.display = '';
            if (el.productsFooter) el.productsFooter.style.display = 'none';
            return;
        }

        if (el.productsEmpty) el.productsEmpty.style.display = 'none';
        if (el.productsFooter) el.productsFooter.style.display = '';

        el.productsList.innerHTML = items.map((p, i) => {
            const name = p.product_name || p.name || ('Product #' + p.product_id);
            const safeIdx = parseInt(i);
            const pid = parseInt(p.product_id);
            return '<div class="item-card" data-product-id="' + pid + '">' +
                '<div class="item-info">' +
                    '<div class="item-name">' + escHtml(name) + '</div>' +
                    '<div class="item-meta">' +
                        (p.sku ? '<span>SKU: ' + escHtml(p.sku) + '</span>' : '') +
                        '<span>' + t('products.stock_quantity', 'Stock') + ': ' + (p.stock_quantity ?? 0) + '</span>' +
                        '<span class="badge ' + (p.is_active == 1 ? 'badge-success' : 'badge-danger') + '">' +
                            (p.is_active == 1 ? t('filter.active', 'Active') : t('filter.inactive', 'Inactive')) +
                        '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="item-fields">' +
                    '<div class="item-field">' +
                        '<label>' + t('products.stock_quantity', 'Stock') + '</label>' +
                        '<input type="number" value="' + (p.stock_quantity ?? 0) + '" min="0"' +
                            ' onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'stock_quantity\',this.value)">' +
                    '</div>' +
                    '<div class="item-field">' +
                        '<label>' + t('products.low_stock_threshold', 'Low Stock') + '</label>' +
                        '<input type="number" value="' + (p.low_stock_threshold ?? 5) + '" min="0"' +
                            ' onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'low_stock_threshold\',this.value)">' +
                    '</div>' +
                    '<div class="item-field">' +
                        '<label>' + t('products.is_active', 'Active') + '</label>' +
                        '<input type="checkbox"' + (p.is_active == 1 ? ' checked' : '') +
                            ' onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'is_active\',this.checked?1:0)">' +
                    '</div>' +
                    '<div class="item-field">' +
                        '<label>' + t('products.is_featured', 'Featured') + '</label>' +
                        '<input type="checkbox"' + (p.is_featured == 1 ? ' checked' : '') +
                            ' onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'is_featured\',this.checked?1:0)">' +
                    '</div>' +
                '</div>' +
                (state.canManage ? '<div class="item-actions">' +
                    '<button class="btn-remove" onclick="EntityProductVariants._removeProduct(' + safeIdx + ')">' +
                        t('products.remove', 'Remove') +
                    '</button>' +
                '</div>' : '') +
            '</div>';
        }).join('');
    }

    function updateProduct(index, field, value) {
        if (state.entityProducts[index]) {
            state.entityProducts[index][field] = value;
        }
    }

    function removeProduct(index) {
        if (!confirm(t('products.confirm_remove', 'Remove this product?'))) return;
        const product = state.entityProducts[index];
        state.entityProducts.splice(index, 1);
        // Also remove all variants for this product
        if (product) {
            state.entityVariants = state.entityVariants.filter(v =>
                parseInt(v.product_id) !== parseInt(product.product_id)
            );
        }
        renderEntityProducts();
        populateVariantProductFilters();
        showToast(t('products.product_removed', 'Product removed'), 'success');
    }

    async function saveEntityProducts() {
        if (!state.entityId || !state.tenantId) {
            showToast(t('messages.select_entity_first', 'Select entity first'), 'error');
            return;
        }
        try {
            const payload = state.entityProducts.map(p => ({
                product_id:          parseInt(p.product_id),
                stock_quantity:      parseInt(p.stock_quantity) || 0,
                low_stock_threshold: parseInt(p.low_stock_threshold) || 5,
                is_active:           p.is_active == 1 ? 1 : 0,
                is_featured:         p.is_featured == 1 ? 1 : 0
            }));

            const url = API.entityProducts + '?action=bulk&entity_id=' + state.entityId + '&tenant_id=' + state.tenantId;
            await apiCall(url, { method: 'POST', body: payload });
            showToast(t('messages.products_saved', 'Products saved'), 'success');
            await loadEntityProducts();
        } catch (e) {
            console.error('Save products failed:', e);
            showToast(t('messages.save_failed', 'Save failed'), 'error');
        }
    }

    // ════════════════════════════════════════
    // PRODUCTS MODAL
    // ════════════════════════════════════════
    async function openProductsModal() {
        if (!state.entityId) {
            showToast(t('messages.select_entity_first', 'Select entity first'), 'error');
            return;
        }
        if (el.productsModal) el.productsModal.style.display = '';
        if (el.modalProductSearch) el.modalProductSearch.value = '';
        if (el.modalProductsList) el.modalProductsList.innerHTML = '<div class="loading-text">' + t('products.loading_products', 'Loading...') + '</div>';

        try {
            let url = API.products + '?limit=1000';
            if (state.tenantId) url += '&tenant_id=' + state.tenantId;
            const res = await apiCall(url);
            const products = res?.data?.items || res?.data || [];
            const existingIds = state.entityProducts.map(p => parseInt(p.product_id));

            if (products.length === 0) {
                el.modalProductsList.innerHTML = '<div class="loading-text">' + t('products.no_products_found', 'No products found') + '</div>';
                return;
            }

            el.modalProductsList.innerHTML = products.map(p => {
                const pid = parseInt(p.id);
                const isAdded = existingIds.includes(pid);
                const name = p.name || p.product_name || ('Product #' + pid);
                return '<div class="modal-item' + (isAdded ? ' disabled' : '') + '" data-id="' + pid + '">' +
                    '<input type="checkbox"' + (isAdded ? ' disabled checked' : '') + '>' +
                    '<div class="modal-item-info">' +
                        '<div class="modal-item-name">' + escHtml(name) + '</div>' +
                        '<div class="modal-item-meta">' + (p.sku ? 'SKU: ' + escHtml(p.sku) : '') + '</div>' +
                    '</div>' +
                    (isAdded ? '<span class="modal-item-badge">' + t('products.already_added', 'Added') + '</span>' : '') +
                '</div>';
            }).join('');

            // Click handler
            el.modalProductsList.querySelectorAll('.modal-item:not(.disabled)').forEach(item => {
                item.addEventListener('click', function (e) {
                    if (e.target.tagName === 'INPUT') return;
                    const cb = this.querySelector('input[type="checkbox"]');
                    if (cb) cb.checked = !cb.checked;
                    this.classList.toggle('selected', cb.checked);
                    updateProductSelectedCount();
                });
                const cb = item.querySelector('input[type="checkbox"]');
                if (cb) cb.addEventListener('change', function () {
                    item.classList.toggle('selected', this.checked);
                    updateProductSelectedCount();
                });
            });
        } catch (e) {
            el.modalProductsList.innerHTML = '<div class="loading-text">' + t('messages.load_failed', 'Failed to load') + '</div>';
        }
    }

    function closeProductsModal() {
        if (el.productsModal) el.productsModal.style.display = 'none';
    }

    function filterModalProducts() {
        const q = (el.modalProductSearch?.value || '').toLowerCase();
        el.modalProductsList?.querySelectorAll('.modal-item').forEach(item => {
            const name = (item.querySelector('.modal-item-name')?.textContent || '').toLowerCase();
            const meta = (item.querySelector('.modal-item-meta')?.textContent || '').toLowerCase();
            item.style.display = (name.includes(q) || meta.includes(q)) ? '' : 'none';
        });
    }

    function toggleAllModalProducts(checked) {
        el.modalProductsList?.querySelectorAll('.modal-item:not(.disabled) input[type="checkbox"]').forEach(cb => {
            cb.checked = checked;
            cb.closest('.modal-item').classList.toggle('selected', checked);
        });
        updateProductSelectedCount();
    }

    function updateProductSelectedCount() {
        const count = el.modalProductsList?.querySelectorAll('.modal-item:not(.disabled) input:checked').length || 0;
        if (el.productSelectedCount) el.productSelectedCount.textContent = count + ' ' + t('products.selected_count', 'selected').replace('{count}', count);
    }

    function confirmProductSelection() {
        const selected = [];
        el.modalProductsList?.querySelectorAll('.modal-item:not(.disabled) input:checked').forEach(cb => {
            const item = cb.closest('.modal-item');
            const pid = parseInt(item.dataset.id);
            const name = item.querySelector('.modal-item-name')?.textContent || '';
            const meta = item.querySelector('.modal-item-meta')?.textContent || '';
            selected.push({
                product_id: pid,
                product_name: name,
                sku: meta.replace('SKU: ', ''),
                stock_quantity: 0,
                low_stock_threshold: 5,
                is_active: 1,
                is_featured: 0
            });
        });
        state.entityProducts = state.entityProducts.concat(selected);
        closeProductsModal();
        renderEntityProducts();
        populateVariantProductFilters();
        if (selected.length > 0) {
            showToast(selected.length + ' ' + t('products.add_selected', 'products added'), 'success');
        }
    }

    // ════════════════════════════════════════
    // ENTITY VARIANTS
    // ════════════════════════════════════════
    async function loadEntityVariants() {
        if (!state.entityId) return;
        try {
            const res = await apiCall(API.entityProductVariants + '?action=entity&entity_id=' + state.entityId);
            state.entityVariants = res?.data || [];
            renderEntityVariants();
        } catch (e) {
            console.error('Failed to load entity variants:', e);
        }
    }

    function renderEntityVariants() {
        if (!el.variantsList) return;
        const searchVal = (el.variantSearch?.value || '').toLowerCase();
        const filterProduct = el.variantProductFilter?.value || '';
        let items = state.entityVariants;

        if (filterProduct) {
            items = items.filter(v => parseInt(v.product_id) === parseInt(filterProduct));
        }
        if (searchVal) {
            items = items.filter(v =>
                (v.product_name || '').toLowerCase().includes(searchVal) ||
                (v.variant_sku || v.sku || '').toLowerCase().includes(searchVal)
            );
        }

        if (items.length === 0) {
            el.variantsList.innerHTML = '';
            if (el.variantsEmpty) el.variantsEmpty.style.display = '';
            if (el.variantsFooter) el.variantsFooter.style.display = 'none';
            return;
        }

        if (el.variantsEmpty) el.variantsEmpty.style.display = 'none';
        if (el.variantsFooter) el.variantsFooter.style.display = '';

        // Group variants by product
        const groups = {};
        items.forEach((v, origIdx) => {
            // Find the original index in state.entityVariants
            const realIdx = state.entityVariants.indexOf(v);
            const pid = parseInt(v.product_id);
            if (!groups[pid]) groups[pid] = { name: v.product_name || ('Product #' + pid), variants: [] };
            groups[pid].variants.push({ ...v, _idx: realIdx });
        });

        let html = '';
        Object.keys(groups).forEach(pid => {
            const g = groups[pid];
            html += '<div class="variant-group-header">' +
                '<span>' + escHtml(g.name) + '</span>' +
                '<span class="variant-count">' + g.variants.length + ' ' + t('variants.title', 'variants') + '</span>' +
            '</div>';
            g.variants.forEach(v => {
                const safeIdx = parseInt(v._idx);
                const stockStatusOpts = ['in_stock', 'out_of_stock', 'unlimited'];
                html += '<div class="item-card" data-variant-id="' + parseInt(v.variant_id) + '">' +
                    '<div class="item-info">' +
                        '<div class="item-name">' + escHtml(v.variant_sku || v.sku || 'Variant #' + v.variant_id) + '</div>' +
                        '<div class="item-meta">' +
                            (v.variant_barcode || v.barcode ? '<span>Barcode: ' + escHtml(v.variant_barcode || v.barcode) + '</span>' : '') +
                        '</div>' +
                    '</div>' +
                    '<div class="item-fields">' +
                        '<div class="item-field">' +
                            '<label>' + t('variants.stock_quantity', 'Stock') + '</label>' +
                            '<input type="number" value="' + (v.stock_quantity ?? 0) + '" min="0"' +
                                ' onchange="EntityProductVariants._updateVariant(' + safeIdx + ',\'stock_quantity\',this.value)">' +
                        '</div>' +
                        '<div class="item-field">' +
                            '<label>' + t('variants.low_stock_threshold', 'Low Stock') + '</label>' +
                            '<input type="number" value="' + (v.low_stock_threshold ?? 5) + '" min="0"' +
                                ' onchange="EntityProductVariants._updateVariant(' + safeIdx + ',\'low_stock_threshold\',this.value)">' +
                        '</div>' +
                        '<div class="item-field">' +
                            '<label>' + t('variants.stock_status', 'Status') + '</label>' +
                            '<select onchange="EntityProductVariants._updateVariant(' + safeIdx + ',\'stock_status\',this.value)">' +
                                stockStatusOpts.map(s =>
                                    '<option value="' + s + '"' + (v.stock_status === s ? ' selected' : '') + '>' + t('variants.' + s, s) + '</option>'
                                ).join('') +
                            '</select>' +
                        '</div>' +
                        '<div class="item-field">' +
                            '<label>' + t('variants.manage_stock', 'Manage') + '</label>' +
                            '<input type="checkbox"' + (v.manage_stock == 1 ? ' checked' : '') +
                                ' onchange="EntityProductVariants._updateVariant(' + safeIdx + ',\'manage_stock\',this.checked?1:0)">' +
                        '</div>' +
                        '<div class="item-field">' +
                            '<label>' + t('variants.is_active', 'Active') + '</label>' +
                            '<input type="checkbox"' + (v.is_active == 1 ? ' checked' : '') +
                                ' onchange="EntityProductVariants._updateVariant(' + safeIdx + ',\'is_active\',this.checked?1:0)">' +
                        '</div>' +
                    '</div>' +
                    (state.canManage ? '<div class="item-actions">' +
                        '<button class="btn-remove" onclick="EntityProductVariants._removeVariant(' + safeIdx + ')">' +
                            t('variants.remove', 'Remove') +
                        '</button>' +
                    '</div>' : '') +
                '</div>';
            });
        });

        el.variantsList.innerHTML = html;
    }

    function updateVariant(index, field, value) {
        if (state.entityVariants[index]) {
            state.entityVariants[index][field] = value;
        }
    }

    function removeVariant(index) {
        if (!confirm(t('variants.confirm_remove', 'Remove this variant?'))) return;
        state.entityVariants.splice(index, 1);
        renderEntityVariants();
        showToast(t('variants.variant_removed', 'Variant removed'), 'success');
    }

    async function saveEntityVariants() {
        if (!state.entityId || !state.tenantId) {
            showToast(t('messages.select_entity_first', 'Select entity first'), 'error');
            return;
        }
        try {
            // Delete existing then bulk save
            await apiCall(API.entityProductVariants + '?action=entity&entity_id=' + state.entityId, { method: 'DELETE' });

            if (state.entityVariants.length > 0) {
                const payload = state.entityVariants.map(v => ({
                    product_id:          parseInt(v.product_id),
                    variant_id:          parseInt(v.variant_id),
                    stock_quantity:      parseInt(v.stock_quantity) || 0,
                    low_stock_threshold: parseInt(v.low_stock_threshold) || 5,
                    manage_stock:        v.manage_stock == 1 ? 1 : 0,
                    stock_status:        v.stock_status || 'in_stock',
                    is_active:           v.is_active == 1 ? 1 : 0,
                    is_featured:         v.is_featured == 1 ? 1 : 0
                }));

                const url = API.entityProductVariants + '?action=bulk&entity_id=' + state.entityId + '&tenant_id=' + state.tenantId;
                await apiCall(url, { method: 'POST', body: payload });
            }

            showToast(t('messages.variants_saved', 'Variants saved'), 'success');
            await loadEntityVariants();
        } catch (e) {
            console.error('Save variants failed:', e);
            showToast(t('messages.save_failed', 'Save failed'), 'error');
        }
    }

    // ════════════════════════════════════════
    // VARIANT PRODUCT FILTERS
    // ════════════════════════════════════════
    function populateVariantProductFilters() {
        const dropdowns = [el.variantProductFilter, el.modalVarProductFilter].filter(Boolean);
        dropdowns.forEach(dd => {
            const val = dd.value;
            dd.innerHTML = '<option value="">' + (dd === el.modalVarProductFilter
                ? t('variants.select_product_first', 'Select product...')
                : t('filter.all_products', 'All Products')) + '</option>';
            state.entityProducts.forEach(p => {
                const name = p.product_name || p.name || ('Product #' + p.product_id);
                const opt = document.createElement('option');
                opt.value = p.product_id;
                opt.textContent = name;
                dd.appendChild(opt);
            });
            if (val) dd.value = val;
        });
    }

    function filterVariantsByProduct() {
        renderEntityVariants();
    }

    // ════════════════════════════════════════
    // VARIANTS MODAL
    // ════════════════════════════════════════
    function openVariantsModal() {
        if (!state.entityId) {
            showToast(t('messages.select_entity_first', 'Select entity first'), 'error');
            return;
        }
        if (state.entityProducts.length === 0) {
            showToast(t('variants.select_product_first', 'Add products first'), 'error');
            return;
        }
        if (el.variantsModal) el.variantsModal.style.display = '';
        populateVariantProductFilters();
        if (el.modalVarProductFilter) el.modalVarProductFilter.value = '';
        if (el.modalVariantsList) {
            el.modalVariantsList.innerHTML = '<div class="loading-text">' + t('variants.select_product_to_see_variants', 'Select a product to see variants') + '</div>';
        }
    }

    function closeVariantsModal() {
        if (el.variantsModal) el.variantsModal.style.display = 'none';
    }

    async function loadModalVariants() {
        const productId = parseInt(el.modalVarProductFilter?.value) || 0;
        if (!productId) {
            if (el.modalVariantsList) {
                el.modalVariantsList.innerHTML = '<div class="loading-text">' + t('variants.select_product_to_see_variants', 'Select a product') + '</div>';
            }
            return;
        }

        if (el.modalVariantsList) {
            el.modalVariantsList.innerHTML = '<div class="loading-text">' + t('variants.loading_variants', 'Loading...') + '</div>';
        }

        try {
            const res = await apiCall(API.productVariants + '?product_id=' + productId + '&limit=500');
            const variants = res?.data?.items || res?.data || [];
            const existingIds = state.entityVariants.map(v => parseInt(v.variant_id));

            if (variants.length === 0) {
                el.modalVariantsList.innerHTML = '<div class="loading-text">' + t('variants.no_variants_found', 'No variants found') + '</div>';
                return;
            }

            el.modalVariantsList.innerHTML = variants.map(v => {
                const vid = parseInt(v.id);
                const isAdded = existingIds.includes(vid);
                const label = v.sku || v.barcode || ('Variant #' + vid);
                return '<div class="modal-item' + (isAdded ? ' disabled' : '') + '" data-id="' + vid + '" data-product-id="' + productId + '">' +
                    '<input type="checkbox"' + (isAdded ? ' disabled checked' : '') + '>' +
                    '<div class="modal-item-info">' +
                        '<div class="modal-item-name">' + escHtml(label) + '</div>' +
                        '<div class="modal-item-meta">' +
                            (v.barcode ? 'Barcode: ' + escHtml(v.barcode) : '') +
                            (v.is_default == 1 ? ' (Default)' : '') +
                        '</div>' +
                    '</div>' +
                    (isAdded ? '<span class="modal-item-badge">' + t('variants.already_added', 'Added') + '</span>' : '') +
                '</div>';
            }).join('');

            // Click handlers
            el.modalVariantsList.querySelectorAll('.modal-item:not(.disabled)').forEach(item => {
                item.addEventListener('click', function (e) {
                    if (e.target.tagName === 'INPUT') return;
                    const cb = this.querySelector('input[type="checkbox"]');
                    if (cb) cb.checked = !cb.checked;
                    this.classList.toggle('selected', cb.checked);
                    updateVariantSelectedCount();
                });
                const cb = item.querySelector('input[type="checkbox"]');
                if (cb) cb.addEventListener('change', function () {
                    item.classList.toggle('selected', this.checked);
                    updateVariantSelectedCount();
                });
            });

            updateVariantSelectedCount();
        } catch (e) {
            if (el.modalVariantsList) {
                el.modalVariantsList.innerHTML = '<div class="loading-text">' + t('messages.load_failed', 'Failed to load') + '</div>';
            }
        }
    }

    function toggleAllModalVariants(checked) {
        el.modalVariantsList?.querySelectorAll('.modal-item:not(.disabled) input[type="checkbox"]').forEach(cb => {
            cb.checked = checked;
            cb.closest('.modal-item').classList.toggle('selected', checked);
        });
        updateVariantSelectedCount();
    }

    function updateVariantSelectedCount() {
        const count = el.modalVariantsList?.querySelectorAll('.modal-item:not(.disabled) input:checked').length || 0;
        if (el.variantSelectedCount) el.variantSelectedCount.textContent = count + ' ' + t('variants.selected_count', 'selected').replace('{count}', count);
    }

    function confirmVariantSelection() {
        const productId = parseInt(el.modalVarProductFilter?.value) || 0;
        if (!productId) return;

        // Find the product name
        const product = state.entityProducts.find(p => parseInt(p.product_id) === productId);
        const productName = product ? (product.product_name || product.name || '') : '';

        const selected = [];
        el.modalVariantsList?.querySelectorAll('.modal-item:not(.disabled) input:checked').forEach(cb => {
            const item = cb.closest('.modal-item');
            const vid = parseInt(item.dataset.id);
            const name = item.querySelector('.modal-item-name')?.textContent || '';
            const meta = item.querySelector('.modal-item-meta')?.textContent || '';
            selected.push({
                product_id:          productId,
                product_name:        productName,
                variant_id:          vid,
                variant_sku:         name,
                variant_barcode:     meta.replace('Barcode: ', '').replace(' (Default)', ''),
                stock_quantity:      0,
                low_stock_threshold: 5,
                manage_stock:        1,
                stock_status:        'in_stock',
                is_active:           1,
                is_featured:         0
            });
        });

        state.entityVariants = state.entityVariants.concat(selected);
        closeVariantsModal();
        renderEntityVariants();
        if (selected.length > 0) {
            showToast(selected.length + ' ' + t('variants.add_selected', 'variants added'), 'success');
        }
    }

    // ════════════════════════════════════════
    // UTILS
    // ════════════════════════════════════════
    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    // ════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════
    window.EntityProductVariants = {
        init:             init,
        _updateProduct:   updateProduct,
        _removeProduct:   removeProduct,
        _updateVariant:   updateVariant,
        _removeVariant:   removeVariant
    };

})();
