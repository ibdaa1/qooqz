(function () {
    'use strict';

    /**
     * admin/assets/js/pages/pos.js
     * POS Cashier System – Main Logic
     */

    // ─────────────────────────────────────────────
    // Config & State
    // ─────────────────────────────────────────────
    const CFG = window.POS_CONFIG || {};

    const API = {
        pos:      '/api/pos_sessions',
        products: '/api/products',
        entities: '/api/entities',
    };

    const state = {
        tenantId:    CFG.TENANT_ID || 1,
        entityId:    CFG.ENTITY_ID || null,
        lang:        CFG.LANG || 'ar',
        dir:         CFG.DIR || 'ltr',
        csrf:        CFG.CSRF || '',
        session:     null,       // current open pos_session
        products:    [],         // all loaded products
        categories:  [],         // category names
        activeCategory: 'all',   // selected category
        cart:        [],         // { product_id, product_name, sku, unit_price, sale_price, tax_rate, qty, image }
        paymentMethod: 'cash',
        searchQuery: '',
        loading:     false,
    };

    let translations = {};

    // ─────────────────────────────────────────────
    // Translations
    // ─────────────────────────────────────────────
    async function loadTranslations() {
        try {
            const url = `/languages/POS/${encodeURIComponent(state.lang)}.json`;
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('not found');
            translations = await res.json();
        } catch {
            translations = {};
        }
    }

    function t(key, fallback) {
        const parts = key.split('.');
        let cur = translations;
        for (const p of parts) {
            if (cur == null || typeof cur !== 'object') return fallback ?? key;
            cur = cur[p];
        }
        return (typeof cur === 'string') ? cur : (fallback ?? key);
    }

    // ─────────────────────────────────────────────
    // API Helpers
    // ─────────────────────────────────────────────
    async function apiGet(url, params = {}) {
        const u = new URL(url, location.origin);
        u.searchParams.set('tenant_id', state.tenantId);
        for (const [k, v] of Object.entries(params)) {
            if (v !== null && v !== undefined) u.searchParams.set(k, v);
        }
        const res = await fetch(u.toString(), { credentials: 'same-origin' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
    }

    async function apiPost(url, body = {}) {
        body.tenant_id = state.tenantId;
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': state.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.message || `HTTP ${res.status}`);
        return data;
    }

    // ─────────────────────────────────────────────
    // DOM References
    // ─────────────────────────────────────────────
    let container, alertsEl, openSessionView, mainLayout;
    let searchInput, categoryTabs, productsGrid;
    let cartItemsList, cartCount, cartEmpty;
    let subtotalEl, taxEl, discountInput, totalEl, grandTotalEl;
    let amountPaidInput, changeDisplay, checkoutBtn, clearBtn;
    let payMethodBtns;
    let modalBackdrop;

    function cacheElements() {
        container      = document.getElementById('posPageContainer');
        alertsEl       = document.getElementById('posAlerts');
        openSessionView = document.getElementById('posOpenSession');
        mainLayout     = document.getElementById('posMainLayout');
        searchInput    = document.getElementById('posSearch');
        categoryTabs   = document.getElementById('posCategoryTabs');
        productsGrid   = document.getElementById('posProductsGrid');
        cartItemsList  = document.getElementById('posCartItems');
        cartCount      = document.getElementById('posCartCount');
        cartEmpty      = document.getElementById('posCartEmpty');
        subtotalEl     = document.getElementById('posSubtotal');
        taxEl          = document.getElementById('posTax');
        discountInput  = document.getElementById('posDiscount');
        totalEl        = document.getElementById('posTotal');
        grandTotalEl   = document.getElementById('posGrandTotal');
        amountPaidInput = document.getElementById('posAmountPaid');
        changeDisplay  = document.getElementById('posChange');
        checkoutBtn    = document.getElementById('posCheckoutBtn');
        clearBtn       = document.getElementById('posClearBtn');
        payMethodBtns  = document.querySelectorAll('.pos-pay-method-btn');
        modalBackdrop  = document.getElementById('posModalBackdrop');
    }

    // ─────────────────────────────────────────────
    // Alerts
    // ─────────────────────────────────────────────
    function showAlert(msg, type = 'success', duration = 4000) {
        if (!alertsEl) return;
        const div = document.createElement('div');
        div.className = `pos-alert pos-alert-${type}`;
        div.innerHTML = `<span>${type === 'success' ? '✓' : '✕'}</span> ${escHtml(msg)}`;
        alertsEl.prepend(div);
        setTimeout(() => div.remove(), duration);
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ─────────────────────────────────────────────
    // Session Management
    // ─────────────────────────────────────────────
    async function loadCurrentSession() {
        try {
            const params = {};
            if (state.entityId) params.entity_id = state.entityId;
            const res = await apiGet(API.pos, { ...params, action: 'current' });
            state.session = res.session || null;
        } catch {
            state.session = null;
        }
    }

    function updateSessionBar() {
        const bar = document.getElementById('posSessionBar');
        if (!bar) return;
        const s = state.session;
        if (s) {
            bar.innerHTML = `
                <div class="session-info">
                    <span class="session-badge open">● ${t('pos.session.open','Open')}</span>
                    <span>🏪 ${escHtml(s.store_name || '')}</span>
                    <span>👤 ${escHtml(s.cashier_name || '')}</span>
                    <span>🕐 ${new Date(s.opened_at).toLocaleTimeString(state.lang === 'ar' ? 'ar-SA' : 'en')}</span>
                    <span>💵 ${t('pos.session.cash','Cash')}: ${formatCurrency(s.total_cash)}</span>
                    <span>💳 ${t('pos.session.card','Card')}: ${formatCurrency(s.total_card)}</span>
                </div>
                <div>
                    <button class="btn btn-danger btn-sm" id="btnCloseSession">
                        🔒 ${t('pos.session.close','Close Session')}
                    </button>
                </div>`;
            document.getElementById('btnCloseSession')?.addEventListener('click', showCloseSessionModal);
        } else {
            bar.innerHTML = `<span class="session-badge closed">● ${t('pos.session.closed','No Active Session')}</span>`;
        }
    }

    function showOpenSessionView() {
        openSessionView && (openSessionView.style.display = 'flex');
        mainLayout && (mainLayout.style.display = 'none');
    }

    function showMainLayout() {
        openSessionView && (openSessionView.style.display = 'none');
        mainLayout && (mainLayout.style.display = 'grid');
    }

    async function openSession(entityId, openingBalance, cashierUserId) {
        try {
            const res = await apiPost(API.pos, {
                action: 'open',
                entity_id: entityId,
                opening_balance: openingBalance,
                cashier_user_id: cashierUserId,
            });
            state.session = res.session;
            state.entityId = entityId;
            updateSessionBar();
            showMainLayout();
            await loadProducts();
            showAlert(t('pos.session.opened_msg', 'Session opened successfully'), 'success');
        } catch (err) {
            showAlert(err.message, 'error');
        }
    }

    function showCloseSessionModal() {
        if (!state.session) return;
        showModal(`
            <h3>🔒 ${t('pos.session.close','Close Session')}</h3>
            <p style="color:var(--text-secondary,#94a3b8);font-size:.88rem;margin:0 0 16px">
                ${t('pos.session.close_confirm','Are you sure you want to close this session?')}
            </p>
            <div class="pos-close-session-form">
                <div class="form-group">
                    <label>${t('pos.session.closing_balance','Closing Cash Balance')}</label>
                    <input type="number" id="closingBalance" class="form-control" step="0.01" min="0"
                           value="${state.session.total_cash || 0}">
                </div>
                <div class="btn-group">
                    <button class="btn btn-outline" onclick="posCloseModal()">
                        ${t('common.cancel','Cancel')}
                    </button>
                    <button class="btn btn-danger" onclick="posConfirmCloseSession()">
                        🔒 ${t('pos.session.close','Close Session')}
                    </button>
                </div>
            </div>
        `);
    }

    window.posConfirmCloseSession = async function () {
        const balance = parseFloat(document.getElementById('closingBalance')?.value ?? 0);
        try {
            await apiPost(API.pos, {
                action: 'close',
                session_id: state.session.id,
                closing_balance: balance,
            });
            state.session = null;
            closeModal();
            updateSessionBar();
            showOpenSessionView();
            state.cart = [];
            renderCart();
            showAlert(t('pos.session.closed_msg', 'Session closed'), 'success');
        } catch (err) {
            showAlert(err.message, 'error');
        }
    };

    // ─────────────────────────────────────────────
    // Products
    // ─────────────────────────────────────────────
    async function loadProducts() {
        if (!productsGrid) return;
        productsGrid.innerHTML = `<div class="pos-loading" style="grid-column:1/-1"><div class="pos-spinner"></div></div>`;
        try {
            const params = { limit: 200, page: 1, is_active: 1, lang: state.lang };
            if (state.entityId) params.entity_id = state.entityId;
            const res = await apiGet(API.products, params);
            state.products = res.items || res.data || [];
            // Build category list
            const cats = new Set();
            state.products.forEach(p => {
                if (p.category_name) cats.add(p.category_name);
            });
            state.categories = Array.from(cats);
            renderCategoryTabs();
            renderProducts();
        } catch (err) {
            productsGrid.innerHTML = `<p style="grid-column:1/-1;color:var(--danger-color,#ef4444);padding:20px">${escHtml(err.message)}</p>`;
        }
    }

    function renderCategoryTabs() {
        if (!categoryTabs) return;
        const allLabel = t('pos.products.all', 'All');
        let html = `<button class="pos-cat-tab ${state.activeCategory === 'all' ? 'active' : ''}" data-cat="all">${allLabel}</button>`;
        state.categories.forEach(cat => {
            const active = state.activeCategory === cat;
            html += `<button class="pos-cat-tab ${active ? 'active' : ''}" data-cat="${escHtml(cat)}">${escHtml(cat)}</button>`;
        });
        categoryTabs.innerHTML = html;
        categoryTabs.querySelectorAll('.pos-cat-tab').forEach(btn => {
            btn.addEventListener('click', () => {
                state.activeCategory = btn.dataset.cat;
                renderCategoryTabs();
                renderProducts();
            });
        });
    }

    function filteredProducts() {
        let prods = state.products;
        if (state.activeCategory !== 'all') {
            prods = prods.filter(p => p.category_name === state.activeCategory);
        }
        const q = state.searchQuery.toLowerCase().trim();
        if (q) {
            prods = prods.filter(p =>
                (p.name || '').toLowerCase().includes(q) ||
                (p.sku || '').toLowerCase().includes(q) ||
                (p.barcode || '').toLowerCase().includes(q)
            );
        }
        return prods;
    }

    function renderProducts() {
        if (!productsGrid) return;
        const prods = filteredProducts();
        if (!prods.length) {
            productsGrid.innerHTML = `<p style="grid-column:1/-1;color:var(--text-muted,#64748b);padding:30px;text-align:center">${t('pos.products.empty','No products found')}</p>`;
            return;
        }
        productsGrid.innerHTML = prods.map(p => {
            const price = parseFloat(p.sale_price || p.price || 0);
            const img = p.image_url || p.image_thumb_url || '';
            return `
            <div class="pos-product-card" data-id="${p.id}" title="${escHtml(p.name || '')}">
                ${img
                    ? `<img src="${escHtml(img)}" alt="${escHtml(p.name || '')}" loading="lazy">`
                    : `<div class="pos-product-img-placeholder">📦</div>`
                }
                <div class="pos-product-name">${escHtml(p.name || p.slug || '')}</div>
                <div class="pos-product-price">${formatCurrency(price)}</div>
                ${p.sku ? `<div class="pos-product-sku">${escHtml(p.sku)}</div>` : ''}
            </div>`;
        }).join('');

        productsGrid.querySelectorAll('.pos-product-card').forEach(card => {
            card.addEventListener('click', () => {
                const prod = state.products.find(p => p.id == card.dataset.id);
                if (prod) addToCart(prod);
            });
        });
    }

    // ─────────────────────────────────────────────
    // Cart
    // ─────────────────────────────────────────────
    function addToCart(prod) {
        const existing = state.cart.find(i => i.product_id === prod.id);
        if (existing) {
            existing.qty++;
        } else {
            const price = parseFloat(prod.sale_price || prod.price || 0);
            state.cart.push({
                product_id:   prod.id,
                product_name: prod.name || prod.slug || '',
                sku:          prod.sku || '',
                unit_price:   price,
                sale_price:   price,
                tax_rate:     parseFloat(prod.tax_rate || 0),
                qty:          1,
                image:        prod.image_thumb_url || prod.image_url || '',
            });
        }
        renderCart();
    }

    function removeFromCart(productId) {
        state.cart = state.cart.filter(i => i.product_id !== productId);
        renderCart();
    }

    function updateQty(productId, delta) {
        const item = state.cart.find(i => i.product_id === productId);
        if (!item) return;
        item.qty = Math.max(1, item.qty + delta);
        renderCart();
    }

    function clearCart() {
        state.cart = [];
        renderCart();
    }

    function cartTotals() {
        let sub = 0, tax = 0;
        state.cart.forEach(i => {
            const lineTotal = i.sale_price * i.qty;
            const lineTax   = lineTotal * (i.tax_rate / 100);
            sub += lineTotal;
            tax += lineTax;
        });
        const discount   = parseFloat(discountInput?.value ?? 0) || 0;
        const total      = sub + tax;
        const grandTotal = Math.max(0, total - discount);
        return { sub, tax, discount, total, grandTotal };
    }

    function renderCart() {
        const items = state.cart;

        // Count badge
        const totalQty = items.reduce((s, i) => s + i.qty, 0);
        if (cartCount) cartCount.textContent = totalQty;

        // Empty state
        if (cartEmpty) cartEmpty.style.display = items.length ? 'none' : 'block';

        // Items
        if (cartItemsList) {
            cartItemsList.innerHTML = items.map(item => `
                <div class="pos-cart-item">
                    <div style="flex:1;min-width:0">
                        <div class="pos-item-name">${escHtml(item.product_name)}</div>
                        <div class="pos-item-price">${formatCurrency(item.sale_price)} × ${item.qty}</div>
                        <div class="pos-qty-controls">
                            <button class="pos-qty-btn remove" data-action="dec" data-id="${item.product_id}">−</button>
                            <span class="pos-qty-display">${item.qty}</span>
                            <button class="pos-qty-btn" data-action="inc" data-id="${item.product_id}">+</button>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px">
                        <div class="pos-item-total">${formatCurrency(item.sale_price * item.qty)}</div>
                        <button class="pos-qty-btn remove" style="width:22px;height:22px;font-size:.75rem" data-action="remove" data-id="${item.product_id}">✕</button>
                    </div>
                </div>
            `).join('');

            cartItemsList.querySelectorAll('[data-action]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const id  = parseInt(btn.dataset.id, 10);
                    const act = btn.dataset.action;
                    if (act === 'inc') updateQty(id, 1);
                    else if (act === 'dec') updateQty(id, -1);
                    else if (act === 'remove') removeFromCart(id);
                });
            });
        }

        updateTotals();
    }

    function updateTotals() {
        const { sub, tax, discount, total, grandTotal } = cartTotals();
        if (subtotalEl) subtotalEl.textContent  = formatCurrency(sub);
        if (taxEl)      taxEl.textContent       = formatCurrency(tax);
        if (totalEl)    totalEl.textContent     = formatCurrency(total);
        if (grandTotalEl) grandTotalEl.textContent = formatCurrency(grandTotal);

        updateChange();
        const hasItems = state.cart.length > 0 && !!state.session;
        if (checkoutBtn) checkoutBtn.disabled = !hasItems;
    }

    function updateChange() {
        const { grandTotal } = cartTotals();
        const paid = parseFloat(amountPaidInput?.value ?? 0) || 0;
        const change = paid - grandTotal;
        if (changeDisplay) {
            if (paid > 0) {
                changeDisplay.textContent = change >= 0
                    ? `${t('pos.change','Change')}: ${formatCurrency(change)}`
                    : `${t('pos.remaining','Remaining')}: ${formatCurrency(Math.abs(change))}`;
                changeDisplay.style.color = change >= 0
                    ? 'var(--success-color, #10b981)'
                    : 'var(--danger-color, #ef4444)';
            } else {
                changeDisplay.textContent = '';
            }
        }
    }

    // ─────────────────────────────────────────────
    // Checkout
    // ─────────────────────────────────────────────
    async function checkout() {
        if (!state.session || !state.cart.length) return;
        const { grandTotal } = cartTotals();
        const paid = parseFloat(amountPaidInput?.value ?? 0) || 0;
        const discount = parseFloat(discountInput?.value ?? 0) || 0;

        if (state.paymentMethod === 'cash' && paid < grandTotal) {
            showAlert(t('pos.error.insufficient_payment', 'Amount paid is less than the total'), 'error');
            return;
        }

        checkoutBtn && (checkoutBtn.disabled = true);
        try {
            const res = await apiPost(API.pos, {
                action:            'create_order',
                session_id:        state.session.id,
                entity_id:         state.session.entity_id,
                payment_method:    state.paymentMethod,
                amount_paid:       paid,
                discount_amount:   discount,
                cashier_user_id:   state.session.cashier_user_id,
                items: state.cart.map(i => ({
                    product_id:   i.product_id,
                    product_name: i.product_name,
                    sku:          i.sku,
                    quantity:     i.qty,
                    unit_price:   i.unit_price,
                    sale_price:   i.sale_price,
                    tax_rate:     i.tax_rate,
                })),
            });

            showReceiptModal(res, paid);
            // Refresh session totals silently
            loadCurrentSession().then(updateSessionBar);

        } catch (err) {
            showAlert(err.message, 'error');
            if (checkoutBtn) checkoutBtn.disabled = false;
        }
    }

    // ─────────────────────────────────────────────
    // Receipt Modal
    // ─────────────────────────────────────────────
    function showReceiptModal(orderRes, paid) {
        const { grandTotal } = cartTotals();
        const change = Math.max(0, paid - grandTotal);
        const now = new Date().toLocaleString(state.lang === 'ar' ? 'ar-SA' : 'en');

        let itemsHtml = state.cart.map(i => `
            <div class="receipt-row">
                <span>${escHtml(i.product_name)} ×${i.qty}</span>
                <span>${formatCurrency(i.sale_price * i.qty)}</span>
            </div>`).join('');

        showModal(`
            <h3>🧾 ${t('pos.receipt.title','Receipt')}</h3>
            <div class="pos-receipt">
                <div class="receipt-center"><strong>${escHtml(state.session?.store_name || '')}</strong></div>
                <div class="receipt-center" style="font-size:.75rem;color:var(--text-muted,#64748b)">${escHtml(now)}</div>
                <div class="receipt-center" style="font-size:.75rem">Order: ${escHtml(orderRes.order_number || '')}</div>
                <hr class="receipt-divider">
                ${itemsHtml}
                <hr class="receipt-divider">
                <div class="receipt-row"><span>${t('pos.subtotal','Subtotal')}</span><span>${formatCurrency(cartTotals().sub)}</span></div>
                ${cartTotals().tax > 0 ? `<div class="receipt-row"><span>${t('pos.tax','Tax')}</span><span>${formatCurrency(cartTotals().tax)}</span></div>` : ''}
                ${cartTotals().discount > 0 ? `<div class="receipt-row"><span>${t('pos.discount','Discount')}</span><span>-${formatCurrency(cartTotals().discount)}</span></div>` : ''}
                <hr class="receipt-divider">
                <div class="receipt-row total-row"><span>${t('pos.total','Total')}</span><span>${formatCurrency(grandTotal)}</span></div>
                <div class="receipt-row"><span>${t('pos.paid','Paid')}</span><span>${formatCurrency(paid)}</span></div>
                ${change > 0 ? `<div class="receipt-row"><span>${t('pos.change','Change')}</span><span>${formatCurrency(change)}</span></div>` : ''}
                <hr class="receipt-divider">
                <div class="receipt-center" style="color:var(--success-color,#10b981)">✓ ${t('pos.receipt.thank_you','Thank you!')}</div>
            </div>
            <div class="btn-group">
                <button class="btn btn-outline" onclick="window.print()">🖨 ${t('pos.receipt.print','Print')}</button>
                <button class="btn btn-primary" onclick="posNewSale()">+ ${t('pos.new_sale','New Sale')}</button>
            </div>
        `);
    }

    window.posNewSale = function () {
        clearCart();
        closeModal();
        if (discountInput) discountInput.value = '';
        if (amountPaidInput) amountPaidInput.value = '';
        if (changeDisplay) changeDisplay.textContent = '';
        if (checkoutBtn) checkoutBtn.disabled = true;
    };

    // ─────────────────────────────────────────────
    // Modal
    // ─────────────────────────────────────────────
    function showModal(html) {
        if (!modalBackdrop) return;
        const box = modalBackdrop.querySelector('.pos-modal');
        if (box) box.innerHTML = html;
        modalBackdrop.style.display = 'flex';
    }

    function closeModal() {
        if (!modalBackdrop) return;
        modalBackdrop.style.display = 'none';
    }

    window.posCloseModal = closeModal;

    // ─────────────────────────────────────────────
    // Open Session Form
    // ─────────────────────────────────────────────
    function bindOpenSessionForm() {
        const form = document.getElementById('posOpenSessionForm');
        if (!form) return;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const entityId     = parseInt(form.querySelector('[name=entity_id]')?.value ?? 0, 10);
            const balance      = parseFloat(form.querySelector('[name=opening_balance]')?.value ?? 0) || 0;
            const cashierUserId = parseInt(form.querySelector('[name=cashier_user_id]')?.value ?? 0, 10) || null;
            if (!entityId) {
                showAlert(t('pos.error.entity_required', 'Please select an entity/branch'), 'error');
                return;
            }
            await openSession(entityId, balance, cashierUserId);
        });
    }

    // ─────────────────────────────────────────────
    // Entities select (load in open session form)
    // ─────────────────────────────────────────────
    async function loadEntitiesSelect() {
        const wrapper = document.getElementById('posEntitySelectWrapper');
        const sel     = document.getElementById('posEntitySelect');

        // Non-super-admin with a preset entity_id: skip the entity selector entirely
        if (!CFG.IS_SUPER_ADMIN && CFG.ENTITY_ID) {
            state.entityId = CFG.ENTITY_ID;
            if (wrapper) wrapper.style.display = 'none';
            // Ensure the select still carries the value so the form submit reads it correctly
            if (sel) {
                sel.innerHTML = `<option value="${CFG.ENTITY_ID}" selected></option>`;
                sel.removeAttribute('required');
            }
            return;
        }

        if (!sel) return;

        try {
            const res = await apiGet(API.entities, { limit: 200 });
            // API returns { success, data: { items: [...], meta: {...} } }
            let entities = [];
            if (res.data && Array.isArray(res.data.items)) {
                entities = res.data.items;
            } else if (Array.isArray(res.items)) {
                entities = res.items;
            }

            sel.innerHTML = `<option value="">${t('pos.select_entity','Select Entity/Branch')}</option>` +
                entities.map(e => `<option value="${e.id}">${escHtml(e.store_name || '')}</option>`).join('');

            // Pre-select if only one entity available
            if (entities.length === 1) {
                sel.value = entities[0].id;
                state.entityId = entities[0].id;
            } else if (CFG.ENTITY_ID) {
                sel.value = CFG.ENTITY_ID;
            }

            // Add search/filter for super admin when there are many entities
            if (CFG.IS_SUPER_ADMIN && entities.length > 5) {
                addEntitySearch(sel, entities);
            }
        } catch {
            // leave empty
        }
    }

    function addEntitySearch(sel, entities) {
        const wrapper = sel.parentElement;
        if (!wrapper || wrapper.querySelector('.pos-entity-search')) return;

        const searchBox = document.createElement('input');
        searchBox.type = 'text';
        searchBox.className = 'form-control pos-entity-search';
        searchBox.placeholder = t('pos.search_entity', 'Search entity…');
        searchBox.style.cssText = 'margin-bottom:6px;';
        wrapper.insertBefore(searchBox, sel);

        searchBox.addEventListener('input', () => {
            const q = searchBox.value.toLowerCase();
            Array.from(sel.options).forEach(opt => {
                if (!opt.value) return; // keep placeholder
                opt.hidden = !opt.text.toLowerCase().includes(q);
            });
        });
    }

    // ─────────────────────────────────────────────
    // Utilities
    // ─────────────────────────────────────────────
    function formatCurrency(val) {
        const n = parseFloat(val) || 0;
        return n.toFixed(2) + ' ' + (CFG.CURRENCY || 'SAR');
    }

    // ─────────────────────────────────────────────
    // Event Bindings
    // ─────────────────────────────────────────────
    function bindEvents() {
        // Search
        searchInput?.addEventListener('input', () => {
            state.searchQuery = searchInput.value;
            renderProducts();
        });

        // Payment methods
        payMethodBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                state.paymentMethod = btn.dataset.method;
                payMethodBtns.forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');
                updateChange();
            });
        });

        // Amount paid → update change
        amountPaidInput?.addEventListener('input', updateChange);

        // Discount → update totals
        discountInput?.addEventListener('input', updateTotals);

        // Checkout
        checkoutBtn?.addEventListener('click', checkout);

        // Clear cart
        clearBtn?.addEventListener('click', () => {
            if (state.cart.length && confirm(t('pos.clear_confirm', 'Clear the cart?'))) {
                clearCart();
            }
        });

        // Close modal on backdrop click
        modalBackdrop?.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) closeModal();
        });
    }

    // ─────────────────────────────────────────────
    // Init
    // ─────────────────────────────────────────────
    async function init() {
        cacheElements();
        await loadTranslations();
        bindEvents();
        bindOpenSessionForm();

        // Load entities for the open session form
        await loadEntitiesSelect();

        // Check if a session is already open
        await loadCurrentSession();
        updateSessionBar();

        if (state.session) {
            state.entityId = state.session.entity_id;
            showMainLayout();
            await loadProducts();
        } else {
            showOpenSessionView();
        }

        // Set default payment method
        const defaultBtn = document.querySelector('.pos-pay-method-btn[data-method="cash"]');
        defaultBtn?.classList.add('selected');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
