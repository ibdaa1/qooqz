/**
 * Entity Product Variants – Standalone Module (Unified View)
 * Manages entity products and their variants together in a single view.
 * Each product card shows stock/pricing fields and collapsible variants section.
 * One "Save All" button saves both products (with pricing) and variants.
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
        entityProducts: [],   // product-level records
        entityVariants: [],   // variant-level records
        allEntities:    [],
        expandedProducts: {}  // track which product variant sections are expanded
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
            initEventListeners();
            loadEntities();
            if (state.entityId > 0) {
                showEntityContent();
                loadEntityData();
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

            // Unified content
            unifiedContent:       document.getElementById('epvUnifiedContent'),
            productSearch:        document.getElementById('epvProductSearch'),
            btnAddProduct:        document.getElementById('epvBtnAddProduct'),
            unifiedList:          document.getElementById('epvUnifiedList'),
            unifiedEmpty:         document.getElementById('epvUnifiedEmpty'),
            unifiedFooter:        document.getElementById('epvUnifiedFooter'),
            btnSaveAll:           document.getElementById('epvBtnSaveAll'),

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
    // SHOW / HIDE CONTENT
    // ════════════════════════════════════════
    function showEntityContent() {
        if (el.unifiedContent) el.unifiedContent.style.display = '';
    }

    function hideEntityContent() {
        if (el.unifiedContent) el.unifiedContent.style.display = 'none';
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
                    loadEntityData();
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

        // Add products
        if (el.btnAddProduct) el.btnAddProduct.addEventListener('click', openProductsModal);

        // Save All
        if (el.btnSaveAll) el.btnSaveAll.addEventListener('click', saveAll);

        // Products Modal
        if (el.closeProductsModal) el.closeProductsModal.addEventListener('click', closeProductsModal);
        if (el.cancelProductSel) el.cancelProductSel.addEventListener('click', closeProductsModal);
        if (el.confirmProductSel) el.confirmProductSel.addEventListener('click', confirmProductSelection);
        if (el.selectAllProducts) el.selectAllProducts.addEventListener('click', () => toggleAllModalProducts(true));
        if (el.deselectAllProducts) el.deselectAllProducts.addEventListener('click', () => toggleAllModalProducts(false));
        if (el.modalProductSearch) el.modalProductSearch.addEventListener('input', filterModalProducts);

        // Variants Modal
        if (el.closeVariantsModal) el.closeVariantsModal.addEventListener('click', closeVariantsModal);
        if (el.cancelVariantSel) el.cancelVariantSel.addEventListener('click', closeVariantsModal);
        if (el.confirmVariantSel) el.confirmVariantSel.addEventListener('click', confirmVariantSelection);
        if (el.selectAllVariants) el.selectAllVariants.addEventListener('click', () => toggleAllModalVariants(true));
        if (el.deselectAllVariants) el.deselectAllVariants.addEventListener('click', () => toggleAllModalVariants(false));
        if (el.modalVarProductFilter) el.modalVarProductFilter.addEventListener('change', loadModalVariants);

        // Search
        if (el.productSearch) el.productSearch.addEventListener('input', renderUnifiedList);
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
        el.entityFilter.innerHTML = '<option value="">' + t('filter.select_entity', 'Select Entity...') + '</option>';
        entities.forEach(function (ent) {
            var name = ent.name || ent.store_name || ('Entity #' + ent.id);
            var opt = document.createElement('option');
            opt.value = ent.id;
            opt.textContent = name + (ent.branch_code ? ' (' + ent.branch_code + ')' : '');
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
    // LOAD ENTITY DATA (Products + Variants)
    // ════════════════════════════════════════
    async function loadEntityData() {
        if (!state.entityId) return;
        try {
            // Load products and variants in parallel
            const [prodRes, varRes] = await Promise.all([
                apiCall(API.entityProducts + '?action=entity&entity_id=' + state.entityId),
                apiCall(API.entityProductVariants + '?action=entity&entity_id=' + state.entityId)
            ]);
            state.entityProducts = prodRes?.data || [];
            state.entityVariants = varRes?.data || [];
            renderUnifiedList();
        } catch (e) {
            console.error('Failed to load entity data:', e);
            showToast(t('messages.load_failed', 'Failed to load data'), 'error');
        }
    }

    // ════════════════════════════════════════
    // RENDER UNIFIED LIST
    // ════════════════════════════════════════
    function renderUnifiedList() {
        if (!el.unifiedList) return;
        var searchVal = (el.productSearch?.value || '').toLowerCase();
        var items = state.entityProducts;

        if (searchVal) {
            items = items.filter(function (p) {
                var nameMatch = (p.product_name || '').toLowerCase().indexOf(searchVal) >= 0;
                var skuMatch = (p.sku || '').toLowerCase().indexOf(searchVal) >= 0;
                // Also match variants
                var pid = parseInt(p.product_id);
                var variantMatch = state.entityVariants.some(function (v) {
                    return parseInt(v.product_id) === pid &&
                        ((v.variant_sku || v.sku || '').toLowerCase().indexOf(searchVal) >= 0 ||
                         (v.variant_barcode || v.barcode || '').toLowerCase().indexOf(searchVal) >= 0);
                });
                return nameMatch || skuMatch || variantMatch;
            });
        }

        if (items.length === 0) {
            el.unifiedList.innerHTML = '';
            if (el.unifiedEmpty) el.unifiedEmpty.style.display = '';
            if (el.unifiedFooter) el.unifiedFooter.style.display = 'none';
            return;
        }

        if (el.unifiedEmpty) el.unifiedEmpty.style.display = 'none';
        if (el.unifiedFooter) el.unifiedFooter.style.display = '';

        var html = '';
        items.forEach(function (p, i) {
            var name = p.product_name || p.name || ('Product #' + p.product_id);
            var pid = parseInt(p.product_id);
            var safeIdx = parseInt(i);

            // Get variants for this product
            var productVariants = state.entityVariants.filter(function (v) {
                return parseInt(v.product_id) === pid;
            });
            var variantCount = productVariants.length;
            var isExpanded = !!state.expandedProducts[pid];

            // Pricing data
            var price = p.price || '';
            var comparePrice = p.compare_at_price || '';
            var costPrice = p.cost_price || '';
            var currencyCode = p.currency_code || '';
            var taxRate = p.tax_rate || '';

            html += '<div class="product-card" data-product-id="' + pid + '">';

            // Product header
            html += '<div class="product-card-header">' +
                '<div class="product-card-title">' +
                    '<div class="item-name">' + escHtml(name) + '</div>' +
                    '<div class="item-meta">' +
                        (p.sku ? '<span>SKU: ' + escHtml(p.sku) + '</span>' : '') +
                        '<span class="badge ' + (p.is_active == 1 ? 'badge-success' : 'badge-danger') + '">' +
                            (p.is_active == 1 ? t('filter.active', 'Active') : t('filter.inactive', 'Inactive')) +
                        '</span>' +
                    '</div>' +
                '</div>' +
                (state.canManage ? '<button class="btn-remove" onclick="EntityProductVariants._removeProduct(' + safeIdx + ')">' +
                    t('products.remove', 'Remove') + '</button>' : '') +
            '</div>';

            // Product fields (stock)
            html += '<div class="product-card-fields">' +
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
            '</div>';

            // Pricing fields
            html += '<div class="product-card-pricing">' +
                '<div class="pricing-label">' + t('products.pricing', 'Pricing') + '</div>' +
                '<div class="pricing-fields">' +
                    '<div class="item-field">' +
                        '<label>' + t('products.price', 'Price') + '</label>' +
                        '<input type="number" step="0.01" value="' + escHtml(price) + '" min="0"' +
                            ' onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'price\',this.value)">' +
                    '</div>' +
                    '<div class="item-field">' +
                        '<label>' + t('products.compare_at_price', 'Compare Price') + '</label>' +
                        '<input type="number" step="0.01" value="' + escHtml(comparePrice) + '" min="0"' +
                            ' onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'compare_at_price\',this.value)">' +
                    '</div>' +
                    '<div class="item-field">' +
                        '<label>' + t('products.cost_price', 'Cost Price') + '</label>' +
                        '<input type="number" step="0.01" value="' + escHtml(costPrice) + '" min="0"' +
                            ' onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'cost_price\',this.value)">' +
                    '</div>' +
                    '<div class="item-field">' +
                        '<label>' + t('products.currency_code', 'Currency') + '</label>' +
                        '<input type="text" maxlength="3" value="' + escHtml(currencyCode) + '"' +
                            ' onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'currency_code\',this.value)">' +
                    '</div>' +
                    '<div class="item-field">' +
                        '<label>' + t('products.tax_rate', 'Tax %') + '</label>' +
                        '<input type="number" step="0.01" value="' + escHtml(taxRate) + '" min="0" max="100"' +
                            ' onchange="EntityProductVariants._updateProduct(' + safeIdx + ',\'tax_rate\',this.value)">' +
                    '</div>' +
                '</div>' +
            '</div>';

            // Variants section (collapsible)
            html += '<div class="product-card-variants">' +
                '<div class="variants-toggle" onclick="EntityProductVariants._toggleVariants(' + pid + ')">' +
                    '<span>' + t('toggle_variants', 'Variants') +
                        ' <span class="variant-count-badge">' + variantCount + '</span>' +
                    '</span>' +
                    '<span class="toggle-arrow">' + (isExpanded ? '▼' : '▶') + '</span>' +
                '</div>';

            if (isExpanded) {
                html += '<div class="variants-content">';

                if (state.canManage) {
                    html += '<div class="variants-actions">' +
                        '<button class="btn btn-sm btn-secondary" onclick="EntityProductVariants._openVariantsForProduct(' + pid + ')">' +
                            t('variants.add_variant', 'Add Variants') +
                        '</button>' +
                    '</div>';
                }

                if (variantCount === 0) {
                    html += '<div class="variants-empty">' + t('variants.no_variants', 'No variants for this product yet.') + '</div>';
                } else {
                    productVariants.forEach(function (v) {
                        var realIdx = state.entityVariants.indexOf(v);
                        var safeVIdx = parseInt(realIdx);
                        var stockStatusOpts = ['in_stock', 'out_of_stock', 'unlimited'];

                        html += '<div class="variant-item" data-variant-id="' + parseInt(v.variant_id) + '">' +
                            '<div class="variant-item-header">' +
                                '<span class="variant-item-name">' + escHtml(v.variant_sku || v.sku || 'Variant #' + v.variant_id) + '</span>' +
                                (v.variant_barcode || v.barcode ? '<span class="variant-item-meta">Barcode: ' + escHtml(v.variant_barcode || v.barcode) + '</span>' : '') +
                                (state.canManage ? '<button class="btn-remove-sm" onclick="EntityProductVariants._removeVariant(' + safeVIdx + ')">&times;</button>' : '') +
                            '</div>' +
                            '<div class="variant-item-fields">' +
                                '<div class="item-field">' +
                                    '<label>' + t('variants.stock_quantity', 'Stock') + '</label>' +
                                    '<input type="number" value="' + (v.stock_quantity ?? 0) + '" min="0"' +
                                        ' onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'stock_quantity\',this.value)">' +
                                '</div>' +
                                '<div class="item-field">' +
                                    '<label>' + t('variants.low_stock_threshold', 'Low Stock') + '</label>' +
                                    '<input type="number" value="' + (v.low_stock_threshold ?? 5) + '" min="0"' +
                                        ' onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'low_stock_threshold\',this.value)">' +
                                '</div>' +
                                '<div class="item-field">' +
                                    '<label>' + t('variants.stock_status', 'Status') + '</label>' +
                                    '<select onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'stock_status\',this.value)">' +
                                        stockStatusOpts.map(function (s) {
                                            return '<option value="' + s + '"' + (v.stock_status === s ? ' selected' : '') + '>' + t('variants.' + s, s) + '</option>';
                                        }).join('') +
                                    '</select>' +
                                '</div>' +
                                '<div class="item-field">' +
                                    '<label>' + t('variants.manage_stock', 'Manage') + '</label>' +
                                    '<input type="checkbox"' + (v.manage_stock == 1 ? ' checked' : '') +
                                        ' onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'manage_stock\',this.checked?1:0)">' +
                                '</div>' +
                                '<div class="item-field">' +
                                    '<label>' + t('variants.is_active', 'Active') + '</label>' +
                                    '<input type="checkbox"' + (v.is_active == 1 ? ' checked' : '') +
                                        ' onchange="EntityProductVariants._updateVariant(' + safeVIdx + ',\'is_active\',this.checked?1:0)">' +
                                '</div>' +
                            '</div>' +
                        '</div>';
                    });
                }
                html += '</div>'; // variants-content
            }

            html += '</div>'; // product-card-variants
            html += '</div>'; // product-card
        });

        el.unifiedList.innerHTML = html;
    }

    // ════════════════════════════════════════
    // PRODUCT CRUD
    // ════════════════════════════════════════
    function updateProduct(index, field, value) {
        if (state.entityProducts[index]) {
            state.entityProducts[index][field] = value;
        }
    }

    function removeProduct(index) {
        if (!confirm(t('products.confirm_remove', 'Remove this product and all its variants?'))) return;
        var product = state.entityProducts[index];
        state.entityProducts.splice(index, 1);
        if (product) {
            var pid = parseInt(product.product_id);
            state.entityVariants = state.entityVariants.filter(function (v) {
                return parseInt(v.product_id) !== pid;
            });
            delete state.expandedProducts[pid];
        }
        renderUnifiedList();
        showToast(t('products.product_removed', 'Product removed'), 'success');
    }

    // ════════════════════════════════════════
    // VARIANT CRUD
    // ════════════════════════════════════════
    function updateVariant(index, field, value) {
        if (state.entityVariants[index]) {
            state.entityVariants[index][field] = value;
        }
    }

    function removeVariant(index) {
        if (!confirm(t('variants.confirm_remove', 'Remove this variant?'))) return;
        state.entityVariants.splice(index, 1);
        renderUnifiedList();
        showToast(t('variants.variant_removed', 'Variant removed'), 'success');
    }

    function toggleVariants(productId) {
        state.expandedProducts[productId] = !state.expandedProducts[productId];
        renderUnifiedList();
    }

    // ════════════════════════════════════════
    // SAVE ALL (Products + Pricing + Variants)
    // ════════════════════════════════════════
    async function saveAll() {
        if (!state.entityId || !state.tenantId) {
            showToast(t('messages.select_entity_first', 'Select entity first'), 'error');
            return;
        }

        try {
            // 1. Save products with pricing
            var productPayload = state.entityProducts.map(function (p) {
                return {
                    product_id:          parseInt(p.product_id),
                    stock_quantity:      parseInt(p.stock_quantity) || 0,
                    low_stock_threshold: parseInt(p.low_stock_threshold) || 5,
                    is_active:           p.is_active == 1 ? 1 : 0,
                    is_featured:         p.is_featured == 1 ? 1 : 0,
                    price:               p.price || null,
                    compare_at_price:    p.compare_at_price || null,
                    cost_price:          p.cost_price || null,
                    currency_code:       p.currency_code || null,
                    tax_rate:            p.tax_rate || null
                };
            });

            var prodUrl = API.entityProducts + '?action=bulk&entity_id=' + state.entityId + '&tenant_id=' + state.tenantId;
            await apiCall(prodUrl, { method: 'POST', body: productPayload });

            // 2. Save variants (delete existing then bulk save)
            await apiCall(API.entityProductVariants + '?action=entity&entity_id=' + state.entityId, { method: 'DELETE' });

            if (state.entityVariants.length > 0) {
                var variantPayload = state.entityVariants.map(function (v) {
                    return {
                        product_id:          parseInt(v.product_id),
                        variant_id:          parseInt(v.variant_id),
                        stock_quantity:      parseInt(v.stock_quantity) || 0,
                        low_stock_threshold: parseInt(v.low_stock_threshold) || 5,
                        manage_stock:        v.manage_stock == 1 ? 1 : 0,
                        stock_status:        v.stock_status || 'in_stock',
                        is_active:           v.is_active == 1 ? 1 : 0,
                        is_featured:         v.is_featured == 1 ? 1 : 0
                    };
                });

                var varUrl = API.entityProductVariants + '?action=bulk&entity_id=' + state.entityId + '&tenant_id=' + state.tenantId;
                await apiCall(varUrl, { method: 'POST', body: variantPayload });
            }

            showToast(t('messages.all_saved', 'All changes saved successfully'), 'success');
            await loadEntityData();
        } catch (e) {
            console.error('Save all failed:', e);
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
            var url = API.products + '?limit=1000';
            if (state.tenantId) url += '&tenant_id=' + state.tenantId;
            var res = await apiCall(url);
            var products = res?.data?.items || res?.data || [];
            var existingIds = state.entityProducts.map(function (p) { return parseInt(p.product_id); });

            if (products.length === 0) {
                el.modalProductsList.innerHTML = '<div class="loading-text">' + t('products.no_products_found', 'No products found') + '</div>';
                return;
            }

            el.modalProductsList.innerHTML = products.map(function (p) {
                var pid = parseInt(p.id);
                var isAdded = existingIds.indexOf(pid) >= 0;
                var name = p.name || p.product_name || ('Product #' + pid);
                return '<div class="modal-item' + (isAdded ? ' disabled' : '') + '" data-id="' + pid + '">' +
                    '<input type="checkbox"' + (isAdded ? ' disabled checked' : '') + '>' +
                    '<div class="modal-item-info">' +
                        '<div class="modal-item-name">' + escHtml(name) + '</div>' +
                        '<div class="modal-item-meta">' + (p.sku ? 'SKU: ' + escHtml(p.sku) : '') + '</div>' +
                    '</div>' +
                    (isAdded ? '<span class="modal-item-badge">' + t('products.already_added', 'Added') + '</span>' : '') +
                '</div>';
            }).join('');

            el.modalProductsList.querySelectorAll('.modal-item:not(.disabled)').forEach(function (item) {
                item.addEventListener('click', function (e) {
                    if (e.target.tagName === 'INPUT') return;
                    var cb = this.querySelector('input[type="checkbox"]');
                    if (cb) cb.checked = !cb.checked;
                    this.classList.toggle('selected', cb.checked);
                    updateProductSelectedCount();
                });
                var cb = item.querySelector('input[type="checkbox"]');
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
        var q = (el.modalProductSearch?.value || '').toLowerCase();
        el.modalProductsList?.querySelectorAll('.modal-item').forEach(function (item) {
            var name = (item.querySelector('.modal-item-name')?.textContent || '').toLowerCase();
            var meta = (item.querySelector('.modal-item-meta')?.textContent || '').toLowerCase();
            item.style.display = (name.indexOf(q) >= 0 || meta.indexOf(q) >= 0) ? '' : 'none';
        });
    }

    function toggleAllModalProducts(checked) {
        el.modalProductsList?.querySelectorAll('.modal-item:not(.disabled) input[type="checkbox"]').forEach(function (cb) {
            cb.checked = checked;
            cb.closest('.modal-item').classList.toggle('selected', checked);
        });
        updateProductSelectedCount();
    }

    function updateProductSelectedCount() {
        var count = el.modalProductsList?.querySelectorAll('.modal-item:not(.disabled) input:checked').length || 0;
        if (el.productSelectedCount) el.productSelectedCount.textContent = count + ' ' + t('products.selected_count', 'selected');
    }

    function confirmProductSelection() {
        var selected = [];
        el.modalProductsList?.querySelectorAll('.modal-item:not(.disabled) input:checked').forEach(function (cb) {
            var item = cb.closest('.modal-item');
            var pid = parseInt(item.dataset.id);
            var name = item.querySelector('.modal-item-name')?.textContent || '';
            var meta = item.querySelector('.modal-item-meta')?.textContent || '';
            selected.push({
                product_id: pid,
                product_name: name,
                sku: meta.replace('SKU: ', ''),
                stock_quantity: 0,
                low_stock_threshold: 5,
                is_active: 1,
                is_featured: 0,
                price: '',
                compare_at_price: '',
                cost_price: '',
                currency_code: '',
                tax_rate: ''
            });
        });
        state.entityProducts = state.entityProducts.concat(selected);
        closeProductsModal();
        renderUnifiedList();
        if (selected.length > 0) {
            showToast(selected.length + ' ' + t('products.add_selected', 'products added'), 'success');
        }
    }

    // ════════════════════════════════════════
    // VARIANTS MODAL
    // ════════════════════════════════════════
    function openVariantsForProduct(productId) {
        if (!state.entityId) {
            showToast(t('messages.select_entity_first', 'Select entity first'), 'error');
            return;
        }
        if (el.variantsModal) el.variantsModal.style.display = '';
        populateVariantProductFilter();
        if (el.modalVarProductFilter) el.modalVarProductFilter.value = String(productId);
        loadModalVariants();
    }

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
        populateVariantProductFilter();
        if (el.modalVarProductFilter) el.modalVarProductFilter.value = '';
        if (el.modalVariantsList) {
            el.modalVariantsList.innerHTML = '<div class="loading-text">' + t('variants.select_product_to_see_variants', 'Select a product to see variants') + '</div>';
        }
    }

    function closeVariantsModal() {
        if (el.variantsModal) el.variantsModal.style.display = 'none';
    }

    function populateVariantProductFilter() {
        var dd = el.modalVarProductFilter;
        if (!dd) return;
        var val = dd.value;
        dd.innerHTML = '<option value="">' + t('variants.select_product_first', 'Select product...') + '</option>';
        state.entityProducts.forEach(function (p) {
            var name = p.product_name || p.name || ('Product #' + p.product_id);
            var opt = document.createElement('option');
            opt.value = p.product_id;
            opt.textContent = name;
            dd.appendChild(opt);
        });
        if (val) dd.value = val;
    }

    async function loadModalVariants() {
        var productId = parseInt(el.modalVarProductFilter?.value) || 0;
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
            var res = await apiCall(API.productVariants + '?product_id=' + productId + '&limit=500');
            var variants = res?.data?.items || res?.data || [];
            var existingIds = state.entityVariants.map(function (v) { return parseInt(v.variant_id); });

            if (variants.length === 0) {
                el.modalVariantsList.innerHTML = '<div class="loading-text">' + t('variants.no_variants_found', 'No variants found') + '</div>';
                return;
            }

            el.modalVariantsList.innerHTML = variants.map(function (v) {
                var vid = parseInt(v.id);
                var isAdded = existingIds.indexOf(vid) >= 0;
                var label = v.sku || v.barcode || ('Variant #' + vid);
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

            el.modalVariantsList.querySelectorAll('.modal-item:not(.disabled)').forEach(function (item) {
                item.addEventListener('click', function (e) {
                    if (e.target.tagName === 'INPUT') return;
                    var cb = this.querySelector('input[type="checkbox"]');
                    if (cb) cb.checked = !cb.checked;
                    this.classList.toggle('selected', cb.checked);
                    updateVariantSelectedCount();
                });
                var cb = item.querySelector('input[type="checkbox"]');
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
        el.modalVariantsList?.querySelectorAll('.modal-item:not(.disabled) input[type="checkbox"]').forEach(function (cb) {
            cb.checked = checked;
            cb.closest('.modal-item').classList.toggle('selected', checked);
        });
        updateVariantSelectedCount();
    }

    function updateVariantSelectedCount() {
        var count = el.modalVariantsList?.querySelectorAll('.modal-item:not(.disabled) input:checked').length || 0;
        if (el.variantSelectedCount) el.variantSelectedCount.textContent = count + ' ' + t('variants.selected_count', 'selected');
    }

    function confirmVariantSelection() {
        var productId = parseInt(el.modalVarProductFilter?.value) || 0;
        if (!productId) return;

        var product = state.entityProducts.find(function (p) { return parseInt(p.product_id) === productId; });
        var productName = product ? (product.product_name || product.name || '') : '';

        var selected = [];
        el.modalVariantsList?.querySelectorAll('.modal-item:not(.disabled) input:checked').forEach(function (cb) {
            var item = cb.closest('.modal-item');
            var vid = parseInt(item.dataset.id);
            var name = item.querySelector('.modal-item-name')?.textContent || '';
            var meta = item.querySelector('.modal-item-meta')?.textContent || '';
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
        // Auto-expand the product's variants section
        state.expandedProducts[productId] = true;
        closeVariantsModal();
        renderUnifiedList();
        if (selected.length > 0) {
            showToast(selected.length + ' ' + t('variants.add_selected', 'variants added'), 'success');
        }
    }

    // ════════════════════════════════════════
    // UTILS
    // ════════════════════════════════════════
    function escHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    // ════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════
    window.EntityProductVariants = {
        init:                    init,
        _updateProduct:          updateProduct,
        _removeProduct:          removeProduct,
        _updateVariant:          updateVariant,
        _removeVariant:          removeVariant,
        _toggleVariants:         toggleVariants,
        _openVariantsForProduct: openVariantsForProduct
    };

})();
