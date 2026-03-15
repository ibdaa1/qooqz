(function () {
    'use strict';

    const CONFIG = window.TICKETS_CONFIG || {};
    const AF = window.AdminFramework || {};
    const PERMS = window.PAGE_PERMISSIONS || {};

    const API = {
        tickets: CONFIG.apiUrl || '/api/support_tickets',
        categories: CONFIG.categoriesApi || '/api/ticket_categories',
        messages: CONFIG.messagesApi || '/api/ticket_messages',
        history: CONFIG.historyApi || '/api/ticket_status_history',
        users: CONFIG.usersApi || '/api/users',
        orders: CONFIG.ordersApi || '/api/orders',
        entities: CONFIG.entitiesApi || '/api/entities'
    };

    const state = {
        page: 1, perPage: CONFIG.itemsPerPage || 20, total: 0,
        tickets: [], categories: [], users: [],
        currentTicket: null, messages: [], history: [],
        filters: {}, permissions: PERMS,
        lang: CONFIG.lang || window.USER_LANGUAGE || 'en', csrfToken: window.APP_CONFIG?.CSRF_TOKEN || '',
        tenantId: CONFIG.tenantId || window.APP_CONFIG?.TENANT_ID || 1
    };

    let el = {};

    // Translation helper – delegates to admin i18n when available
    function t(key, fb = '') {
        if (window._admin && typeof window._admin.t === 'function') {
            const val = window._admin.t(key);
            if (val && val !== key) return val;
        }
        return fb || key;
    }
    function esc(text) { if (!text) return ''; const d = document.createElement('div'); d.textContent = text; return d.innerHTML; }

    // API Helper
    async function apiCall(url, opts = {}) {
        const defaults = { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } };
        if (opts.method && opts.method !== 'GET') defaults.headers['X-CSRF-Token'] = state.csrfToken;
        const config = { ...defaults, ...opts };
        if (config.headers && opts.headers) config.headers = { ...defaults.headers, ...opts.headers };
        
        const res = await fetch(url, config);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
        return data;
    }

    // Load List
    async function loadTickets(page = 1) {
        try {
            showLoading();
            state.page = page;
            const params = new URLSearchParams({
                page, limit: state.perPage, tenant_id: state.tenantId, lang: state.lang
            });
            Object.entries(state.filters).forEach(([k, v]) => { if (v) params.set(k, v); });

            const result = await apiCall(`${API.tickets}?${params}`);
            if (result.success) {
                state.tickets = result.data.items || result.data || [];
                state.total = result.data.meta?.total || state.tickets.length;
                renderTable(state.tickets);
                updatePagination(state.total);
                showTable();
            } else throw new Error(result.message);
        } catch (err) {
            showError(err.message);
        }
    }

    // Load Dropdowns
    async function loadDropdowns() {
        try {
            // Categories
            const catRes = await apiCall(`${API.categories}?tenant_id=${state.tenantId}&lang=${state.lang}`);
            if (catRes.success) {
                state.categories = catRes.data.items || catRes.data || [];
                populateSelect(el.category, state.categories, 'id', 'name', t('form.fields.category.select', 'Select Category'));
            }
            
            // Users
            const userRes = await apiCall(`${API.users}?limit=100`); 
            if (userRes.success) {
                state.users = userRes.data.items || userRes.data || [];
                populateSelect(el.user, state.users, 'id', 'email', t('form.fields.user.select', 'Select Customer'));
                populateSelect(el.assigned, state.users, 'id', 'email', t('form.fields.assigned_to.unassigned', 'Unassigned'), true);
            }

            // Initialize empty order/entity dropdowns
            populateSelect(el.order, [], 'id', 'order_number', t('form.fields.order.select', 'Select order (optional)'));
            populateSelect(el.entity, [], 'id', 'store_name', t('form.fields.entity.select', 'Select entity'));
        } catch (e) { console.warn("Failed to load dropdowns", e); }
    }

    // Load orders and entities for a specific user
    async function loadUserOrdersAndEntities(userId) {
        if (!userId) {
            populateSelect(el.order, [], 'id', 'order_number', t('form.fields.order.select', 'Select order (optional)'));
            populateSelect(el.entity, [], 'id', 'store_name', t('form.fields.entity.select', 'Select entity'));
            return;
        }
        try {
            const orderRes = await apiCall(`${API.orders}?user_id=${userId}&tenant_id=${state.tenantId}&limit=100&lang=${state.lang}`);
            if (orderRes.success) {
                const orders = orderRes.data.items || orderRes.data || [];
                populateSelect(el.order, orders, 'id', 'order_number', t('form.fields.order.select', 'Select order (optional)'));
            }
        } catch (e) { console.warn('Failed to load orders for user', e); }
        try {
            const entityRes = await apiCall(`${API.entities}?user_id=${userId}&tenant_id=${state.tenantId}&limit=100&lang=${state.lang}`);
            if (entityRes.success) {
                const entities = entityRes.data.items || entityRes.data || [];
                populateSelect(el.entity, entities, 'id', 'store_name', t('form.fields.entity.select', 'Select entity'));
                if (entities.length === 1 && el.entity) el.entity.value = entities[0].id;
            }
        } catch (e) { console.warn('Failed to load entities for user', e); }
    }

    function populateSelect(sel, items, valKey, txtKey, placeholder, includeEmpty = false) {
        if (!sel) return;
        sel.innerHTML = '';
        if (placeholder) {
            const opt = document.createElement('option');
            opt.value = ''; opt.textContent = placeholder; sel.appendChild(opt);
        }
        if (includeEmpty) {
             const opt = document.createElement('option');
            opt.value = '0'; opt.textContent = '--- Unassigned ---'; sel.appendChild(opt);
        }
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valKey];
            opt.textContent = item[txtKey] || `ID: ${item[valKey]}`;
            sel.appendChild(opt);
        });
    }

    // Render Table
    function renderTable(items) {
        if (!el.tbody) return;
        if (!items.length) { showEmpty(); return; }

        el.tbody.innerHTML = items.map(t => {
            const statusClass = `badge-${t.status === 'open' ? 'active' : t.status === 'closed' ? 'inactive' : 'secondary'}`;
            const priorityClass = t.priority === 'urgent' ? 'badge-danger' : t.priority === 'high' ? 'badge-warning' : '';
            
            return `
            <tr data-id="${t.id}">
                <td>#${t.id}</td>
                <td>
                    <strong>${esc(t.subject)}</strong><br>
                    <small style="color:var(--text-secondary)">${esc(t.ticket_number)}</small>
                </td>
                <td>${esc(t.user_email || 'Guest')}</td>
                <td>${esc(t.category_name || '-')}</td>
                <td><span class="badge ${priorityClass}">${esc(t.priority)}</span></td>
                <td><span class="badge ${statusClass}">${esc(t.status)}</span></td>
                <td>${new Date(t.updated_at).toLocaleDateString()}</td>
                <td>
                    <div class="table-actions">
                        <button class="btn btn-sm btn-secondary" onclick="Tickets.edit(${t.id})"><i class="fas fa-edit"></i></button>
                        ${state.permissions.canDelete ? `<button class="btn btn-sm btn-danger" onclick="Tickets.remove(${t.id})"><i class="fas fa-trash"></i></button>` : ''}
                    </div>
                </td>
            </tr>`;
        }).join('');
    }

    // Form Handling
    async function showForm(data = null) {
        state.currentTicket = data;
        state.messages = [];
        state.history = [];
        
        el.form.reset();
        el.formContainer.style.display = 'block';
        el.formContainer.scrollIntoView({ behavior: 'smooth' });
        
        // Reset tabs
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
        document.querySelector('.tab-btn[data-tab="details"]').classList.add('active');
        document.getElementById('tab-details').style.display = 'block';

        if (data) {
            el.formTitle.textContent = `${t('form.edit_title', 'Edit Ticket')} #${data.id}`;
            el.formId.value = data.id;
            el.subject.value = data.subject;
            el.description.value = data.description;
            el.status.value = data.status;
            el.priority.value = data.priority;
            if (data.category_id) el.category.value = data.category_id;
            if (data.user_id) {
                el.user.value = data.user_id;
                // Load orders and entities for this user, then set values
                await loadUserOrdersAndEntities(data.user_id);
                if (data.order_id && el.order) el.order.value = data.order_id;
                if (data.entity_id && el.entity) el.entity.value = data.entity_id;
            }
            if (data.assigned_to) el.assigned.value = data.assigned_to;
            
            el.btnDelete.style.display = 'block';
            loadTicketData(data.id);
        } else {
            el.formTitle.textContent = t('form.add_title', 'New Ticket');
            el.formId.value = '';
            el.btnDelete.style.display = 'none';
            // Reset order/entity dropdowns
            populateSelect(el.order, [], 'id', 'order_number', t('form.fields.order.select', 'Select order (optional)'));
            populateSelect(el.entity, [], 'id', 'store_name', t('form.fields.entity.select', 'Select entity'));
        }
    }

    function hideForm() {
        el.formContainer.style.display = 'none';
        state.currentTicket = null;
    }

    async function loadTicketData(id) {
        // Load Messages
        try {
            const res = await apiCall(`${API.messages}?ticket_id=${id}&tenant_id=${state.tenantId}&lang=${state.lang}`);
            if (res.success) {
                state.messages = res.data.items || res.data || [];
                renderMessages();
            }
        } catch (e) {}

        // Load History
        try {
            const res = await apiCall(`${API.history}?ticket_id=${id}&tenant_id=${state.tenantId}&lang=${state.lang}`);
            if (res.success) {
                state.history = res.data.items || res.data || [];
                renderHistory();
            }
        } catch (e) {}
    }

    function renderMessages() {
        if (!el.messagesList) return;
        el.messagesList.innerHTML = state.messages.map(m => `
            <div class="message-item ${m.is_internal ? 'internal' : ''}" style="margin-bottom:15px; padding:12px; border-radius:8px; background:${m.is_internal ? 'rgba(255,200,0,0.05)' : 'rgba(255,255,255,0.03)'}; border-left:4px solid ${m.is_internal ? '#f59e0b' : 'var(--primary-color)'};">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px; font-size:0.85rem;">
                    <strong>${esc(m.sender_email || 'System')}</strong>
                    <span style="color:var(--text-secondary)">${new Date(m.created_at).toLocaleString()}</span>
                </div>
                <div style="color:var(--text-primary)">${esc(m.message)}</div>
            </div>
        `).join('');
    }

    function renderHistory() {
        if (!el.historyList) return;
        el.historyList.innerHTML = state.history.map(h => `
            <div style="padding:8px; border-bottom:1px solid var(--border-color);">
                <span class="badge badge-secondary">${esc(h.old_status || 'New')}</span>
                <i class="fas fa-arrow-right" style="margin:0 8px; color:var(--text-secondary)"></i>
                <span class="badge badge-active">${esc(h.new_status)}</span>
                <span style="float:right; font-size:0.8rem; color:var(--text-secondary)">${new Date(h.created_at).toLocaleString()}</span>
            </div>
        `).join('');
    }

    async function saveTicket(e) {
        e.preventDefault();
        const formData = new FormData(el.form);
        const id = formData.get('id');
        const data = {
            tenant_id: state.tenantId,
            subject: formData.get('subject'),
            description: formData.get('description'),
            category_id: formData.get('category_id') || null,
            user_id: formData.get('user_id') || null,
            order_id: formData.get('order_id') || null,
            entity_id: formData.get('entity_id') || null,
            status: formData.get('status'),
            priority: formData.get('priority'),
            assigned_to: formData.get('assigned_to') || null
        };

        try {
            const url = API.tickets;
            const method = id ? 'PUT' : 'POST';
            if (id) data.id = id;

            const res = await apiCall(url, {
                method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (res.success) {
                showNotification(id ? 'Ticket Updated' : 'Ticket Created', 'success');
                hideForm();
                loadTickets(state.page);
            } else throw new Error(res.message);
        } catch (err) {
            showNotification(err.message, 'error');
        }
    }

    async function sendReply() {
        if (!state.currentTicket || !el.replyText.value.trim()) return;
        
        try {
            const res = await apiCall(API.messages, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    tenant_id: state.tenantId,
                    ticket_id: state.currentTicket.id,
                    sender_user_id: window.APP_CONFIG.USER_ID, // Assuming admin user ID
                    message: el.replyText.value,
                    is_internal: el.replyInternal.checked ? 1 : 0
                })
            });
            
            if (res.success) {
                el.replyText.value = '';
                el.replyInternal.checked = false;
                loadTicketData(state.currentTicket.id);
            } else throw new Error(res.message);
        } catch (err) {
            showNotification(err.message, 'error');
        }
    }

    async function deleteTicket(id) {
        if (!confirm('Are you sure?')) return;
        try {
            const res = await apiCall(API.tickets, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, tenant_id: state.tenantId })
            });
            if (res.success) {
                showNotification('Deleted', 'success');
                hideForm();
                loadTickets(state.page);
            }
        } catch (err) {
            showNotification(err.message, 'error');
        }
    }

    // UI Helpers
    function showLoading() { el.loading.style.display = 'flex'; el.container.style.display = 'none'; if (el.empty) el.empty.style.display = 'none'; }
    function showTable() { el.loading.style.display = 'none'; el.container.style.display = 'block'; if (el.empty) el.empty.style.display = 'none'; }
    function showEmpty() { el.loading.style.display = 'none'; el.container.style.display = 'none'; el.empty.style.display = 'flex'; }
    function showError(msg) { el.loading.style.display = 'none'; el.empty.style.display = 'none'; el.container.style.display = 'none'; console.error(msg); }
    function showNotification(msg, type) { alert(msg); } // Replace with framework notify
    
    function updatePagination(total) {
        const pages = Math.ceil(total / state.perPage);
        let html = '';
        for (let i = 1; i <= pages; i++) {
            html += `<button class="pagination-btn ${i === state.page ? 'active' : ''}" onclick="Tickets.load(${i})">${i}</button>`;
        }
        el.pagination.innerHTML = html;
        el.paginationInfo.textContent = `${((state.page-1)*state.perPage)+1}-${Math.min(state.page*state.perPage, total)} of ${total}`;
    }

    // Init
    async function init() {
        console.log('[Tickets] Initializing...');
        
        el = {
            container: document.getElementById('tableContainer'),
            loading: document.getElementById('tableLoading'),
            empty: document.getElementById('emptyState'),
            tbody: document.getElementById('tableBody'),
            pagination: document.getElementById('pagination'),
            paginationInfo: document.getElementById('paginationInfo'),
            formContainer: document.getElementById('ticketFormContainer'),
            form: document.getElementById('ticketForm'),
            formTitle: document.getElementById('formTitle'),
            formId: document.getElementById('formId'),
            subject: document.getElementById('ticketSubject'),
            description: document.getElementById('ticketDescription'),
            status: document.getElementById('ticketStatus'),
            priority: document.getElementById('ticketPriority'),
            category: document.getElementById('ticketCategory'),
            user: document.getElementById('ticketUser'),
            order: document.getElementById('ticketOrder'),
            entity: document.getElementById('ticketEntity'),
            assigned: document.getElementById('ticketAssigned'),
            messagesList: document.getElementById('ticketMessagesList'),
            historyList: document.getElementById('ticketHistoryList'),
            replyText: document.getElementById('ticketReply'),
            replyInternal: document.getElementById('replyInternal'),
            btnDelete: document.getElementById('btnDeleteTicket')
        };

        // Event Listeners
        document.getElementById('btnAddTicket')?.addEventListener('click', () => showForm());
        document.getElementById('btnCloseForm')?.addEventListener('click', hideForm);
        document.getElementById('btnCancelForm')?.addEventListener('click', hideForm);
        el.form?.addEventListener('submit', saveTicket);
        el.btnDelete?.addEventListener('click', () => deleteTicket(state.currentTicket?.id));
        document.getElementById('btnSendReply')?.addEventListener('click', sendReply);

        // When user changes, load their orders and entities
        el.user?.addEventListener('change', () => {
            const userId = el.user.value;
            loadUserOrdersAndEntities(userId);
        });

        document.getElementById('btnApplyFilters')?.addEventListener('click', () => {
            state.filters = {
                search: document.getElementById('searchInput').value,
                status: document.getElementById('statusFilter').value,
                priority: document.getElementById('priorityFilter').value
            };
            loadTickets(1);
        });
        document.getElementById('btnResetFilters')?.addEventListener('click', () => {
            state.filters = {};
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('priorityFilter').value = '';
            loadTickets(1);
        });

        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
                btn.classList.add('active');
                document.getElementById(`tab-${btn.dataset.tab}`).style.display = 'block';
            });
        });

        await loadDropdowns();
        await loadTickets(1);
    }

    window.Tickets = {
        init,
        load: loadTickets,
        edit: async (id) => {
            try {
                const res = await apiCall(`${API.tickets}?id=${id}&tenant_id=${state.tenantId}`);
                if (res.success) await showForm(res.data);
            } catch (e) { console.error(e); }
        },
        remove: deleteTicket
    };

    // Initialization is driven by the fragment's inline script which waits
    // for the admin:i18n:applied event so translations are ready first.
    // Do NOT self-invoke here to avoid running before translations are loaded.
})();