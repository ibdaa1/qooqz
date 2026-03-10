(function() {
    'use strict';

    // ============================================================
    // CONFIGURATION & STATE
    // ============================================================
    const CONFIG = window.WORKSPACE_CONFIG || {};
    const PERMS = window.PAGE_PERMISSIONS || {};

    const API = {
        jobs: CONFIG.jobsApi || '/api/jobs',
        applications: CONFIG.applicationsApi || '/api/job_applications',
        interviews: CONFIG.interviewsApi || '/api/job_interviews',
        alerts: CONFIG.alertsApi || '/api/job_alerts',
        questions: CONFIG.questionsApi || '/api/job_application_questions',
        answers: CONFIG.answersApi || '/api/job_application_answers',
        skills: CONFIG.skillsApi || '/api/job_skills',
        languages: CONFIG.languagesApi || '/api/languages',
        categories: CONFIG.categoriesApi || '/api/job_categories',
        countries: CONFIG.countriesApi || '/api/countries',
        cities: CONFIG.citiesApi || '/api/cities',
        currencies: CONFIG.currenciesApi || '/api/currencies'
    };

    const state = {
        activeTab: 'jobs',
        language: window.USER_LANGUAGE || CONFIG.lang || 'en',
        direction: window.USER_DIRECTION || 'ltr',
        csrfToken: window.CSRF_TOKEN || CONFIG.csrfToken || '',
        tenantId: window.APP_CONFIG?.TENANT_ID || 1,
        userId: window.APP_CONFIG?.USER_ID || null,
        isFragment: CONFIG.isFragment || false,
        permissions: PERMS
    };

    // Global DOM elements
    let el = {};

    // Translations storage
    let translations = {};

    // ============================================================
    // TRANSLATION HELPERS
    // ============================================================
    async function loadTranslations(lang) {
        try {
            const url = `/languages/Jobs/${encodeURIComponent(lang || state.language)}.json`;
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error(`Failed to load translations: ${res.status}`);
            const raw = await res.json();
            const s = raw.strings || raw;
            translations = buildTranslationsMap(s);
            if (raw.direction) setDirectionForLang(raw.direction === 'rtl' ? 'ar' : 'en');
            applyTranslations();
        } catch (err) {
            console.warn('[Workspace] Translation load failed:', err);
            translations = {};
        }
    }

    function buildTranslationsMap(s) {
        // Extend with new keys for applications, interviews, etc.
        return {
            jobs: {
                title: s.jobs?.title || 'Jobs',
                add_new: s.jobs?.add_new || 'Add Job',
                loading: s.loading || 'Loading...'
            },
            applications: {
                title: s.applications?.title || 'Applications',
                add: s.applications?.add || 'Add Application',
                status: s.applications?.status || 'Status',
                rating: s.applications?.rating || 'Rating'
            },
            interviews: {
                title: s.interviews?.title || 'Interviews',
                add: s.interviews?.add || 'Add Interview'
            },
            alerts: {
                title: s.alerts?.title || 'Alerts',
                add: s.alerts?.add || 'Add Alert'
            },
            questions: {
                title: s.questions?.title || 'Questions',
                add: s.questions?.add || 'Add Question'
            },
            workspace: {
                tabs: {
                    jobs: s.workspace?.tabs?.jobs || 'Jobs',
                    applications: s.workspace?.tabs?.applications || 'Applications',
                    interviews: s.workspace?.tabs?.interviews || 'Interviews',
                    alerts: s.workspace?.tabs?.alerts || 'Alerts',
                    questions: s.workspace?.tabs?.questions || 'Questions'
                }
            },
            filters: {
                search: s.filters?.search || 'Search',
                status: s.filters?.status || 'Status',
                apply: s.filters?.apply || 'Apply',
                reset: s.filters?.reset || 'Reset'
            },
            table: {
                headers: {
                    id: 'ID',
                    job_title: s.table?.headers?.job_title || 'Job Title',
                    actions: s.actions || 'Actions'
                },
                empty: {
                    title: s.table?.empty?.title || 'No items found',
                    message: s.table?.empty?.message || 'Start by adding your first item',
                    add_first: s.table?.empty?.add_first || 'Add First'
                }
            },
            pagination: {
                showing: s.pagination?.showing || 'Showing'
            },
            export: {
                excel: s.export?.excel || 'Export to Excel'
            },
            strings: {
                delete_confirm: s.delete_confirm || 'Are you sure you want to delete this item?',
                delete_success: s.delete_success || 'Item deleted successfully',
                save_success: s.save_success || 'Saved successfully',
                update_success: s.update_success || 'Updated successfully'
            }
        };
    }

    function t(key, fallback = '') {
        const keys = key.split('.');
        let val = translations;
        for (const k of keys) {
            if (val && typeof val === 'object' && k in val) {
                val = val[k];
            } else {
                return fallback || key;
            }
        }
        return val !== undefined && val !== null ? String(val) : (fallback || key);
    }

    function applyTranslations() {
        document.querySelectorAll('[data-i18n]').forEach(elem => {
            const key = elem.getAttribute('data-i18n');
            const txt = t(key);
            if (txt !== key) elem.textContent = txt;
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach(elem => {
            const key = elem.getAttribute('data-i18n-placeholder');
            const txt = t(key);
            if (txt !== key) elem.placeholder = txt;
        });
    }

    function setDirectionForLang(lang) {
        const rtlLanguages = ['ar', 'he', 'fa', 'ur'];
        const isRtl = rtlLanguages.includes(lang);
        const container = document.getElementById('jobsPageContainer');
        if (container) container.setAttribute('dir', isRtl ? 'rtl' : 'ltr');
        state.direction = isRtl ? 'rtl' : 'ltr';
    }

    // ============================================================
    // API HELPER
    // ============================================================
    async function apiCall(url, options = {}) {
        const defaults = {
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        if (options.method && options.method !== 'GET') {
            defaults.headers['X-CSRF-Token'] = state.csrfToken;
        }
        const config = { ...defaults, ...options };
        if (config.headers && options.headers) {
            config.headers = { ...defaults.headers, ...options.headers };
        }
        try {
            const res = await fetch(url, config);
            const contentType = res.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                const data = await res.json();
                if (!res.ok) throw new Error(data.error || data.message || `HTTP ${res.status}`);
                return data;
            } else {
                const text = await res.text();
                if (!res.ok) throw new Error(text || `HTTP ${res.status}`);
                return { success: true, data: text };
            }
        } catch (err) {
            console.error('[Workspace] API call failed:', url, err);
            throw err;
        }
    }

    // ============================================================
    // UTILITIES
    // ============================================================
    function esc(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function showNotification(message, type = 'info') {
        if (window.AdminFramework?.notify) {
            window.AdminFramework.notify(message, type);
        } else {
            alert(`[${type}] ${message}`);
        }
    }

    // Export to CSV
    function exportToCSV(data, filename, columns) {
        if (!data || !data.length) {
            showNotification('No data to export', 'warning');
            return;
        }
        const headers = columns.map(col => col.label).join(',');
        const rows = data.map(item => {
            return columns.map(col => {
                let val = item[col.field] || '';
                if (typeof val === 'string' && val.includes(',')) val = `"${val}"`;
                return val;
            }).join(',');
        }).join('\n');
        const csv = headers + '\n' + rows;
        const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // ============================================================
    // MODULE: Jobs (adapted from original)
    // ============================================================
    const jobsModule = {
        state: {
            page: 1,
            perPage: CONFIG.itemsPerPage || 25,
            total: 0,
            items: [],
            filters: {}
        },
        el: {},

        init() {
            this.cacheElements();
            this.attachEvents();
            this.loadDropdownData();
        },

        cacheElements() {
            this.el = {
                container: document.getElementById('jobsPageContainer'),
                formContainer: document.getElementById('jobFormContainer'),
                tableContainer: document.getElementById('tableContainer'),
                tableBody: document.getElementById('tableBody'),
                loading: document.getElementById('tableLoading'),
                emptyState: document.getElementById('emptyState'),
                pagination: document.getElementById('pagination'),
                paginationInfo: document.getElementById('paginationInfo'),
                addBtn: document.getElementById('btnAddJob'),
                exportBtn: document.getElementById('jobsExportBtn'),
                applyFilters: document.getElementById('btnApplyFilters'),
                resetFilters: document.getElementById('btnResetFilters'),
                searchInput: document.getElementById('searchInput'),
                statusFilter: document.getElementById('statusFilter'),
                jobTypeFilter: document.getElementById('jobTypeFilter'),
                experienceFilter: document.getElementById('experienceLevelFilter'),
                form: document.getElementById('jobForm'),
                formTitle: document.getElementById('formTitle'),
                closeForm: document.getElementById('btnCloseForm'),
                cancelForm: document.getElementById('btnCancelForm'),
                jobTitle: document.getElementById('jobTitle'),
                jobType: document.getElementById('jobType'),
                employmentType: document.getElementById('employmentType'),
                experienceLevel: document.getElementById('experienceLevel'),
                jobCategory: document.getElementById('jobCategory'),
                jobDepartment: document.getElementById('jobDepartment'),
                positionsAvailable: document.getElementById('positionsAvailable'),
                jobStatus: document.getElementById('jobStatus'),
                startDate: document.getElementById('startDate'),
                isFeatured: document.getElementById('isFeatured'),
                isUrgent: document.getElementById('isUrgent'),
                salaryMin: document.getElementById('salaryMin'),
                salaryMax: document.getElementById('salaryMax'),
                salaryCurrency: document.getElementById('salaryCurrency'),
                salaryPeriod: document.getElementById('salaryPeriod'),
                salaryNegotiable: document.getElementById('salaryNegotiable'),
                countryId: document.getElementById('countryId'),
                cityId: document.getElementById('cityId'),
                workLocation: document.getElementById('workLocation'),
                isRemote: document.getElementById('isRemote'),
                applicationFormType: document.getElementById('applicationFormType'),
                externalApplicationUrl: document.getElementById('externalApplicationUrl'),
                applicationDeadline: document.getElementById('applicationDeadline'),
                jobSlug: document.getElementById('jobSlug'),
                jobEntityId: document.getElementById('jobEntityId')
            };
        },

        attachEvents() {
            if (this.el.addBtn) this.el.addBtn.onclick = () => this.showForm();
            if (this.el.exportBtn) this.el.exportBtn.onclick = () => this.exportToExcel();
            if (this.el.applyFilters) this.el.applyFilters.onclick = () => this.applyFilters();
            if (this.el.resetFilters) this.el.resetFilters.onclick = () => this.resetFilters();
            if (this.el.form) this.el.form.onsubmit = (e) => this.save(e);
            if (this.el.closeForm) this.el.closeForm.onclick = () => this.hideForm();
            if (this.el.cancelForm) this.el.cancelForm.onclick = () => this.hideForm();
            // Form tab navigation
            document.querySelectorAll('.form-tabs .tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const tab = btn.dataset.tab;
                    document.querySelectorAll('.form-tabs .tab-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    document.querySelectorAll('.tab-content').forEach(c => {
                        c.style.display = c.id === `tab-${tab}` ? 'block' : 'none';
                    });
                });
            });
            // Country → City cascade
            if (this.el.countryId) {
                this.el.countryId.addEventListener('change', () => {
                    this.loadCitiesForCountry(this.el.countryId.value);
                });
            }
        },

        async load(page = 1) {
            this.state.page = page;
            this.showLoading();
            try {
                const params = new URLSearchParams({
                    page: page,
                    limit: this.state.perPage,
                    tenant_id: state.tenantId,
                    lang: state.language,
                    format: 'json'
                });
                Object.entries(this.state.filters).forEach(([k, v]) => {
                    if (v) params.set(k, v);
                });
                const result = await apiCall(`${API.jobs}?${params}`);
                if (result.success) {
                    const items = result.data.items || result.data;
                    this.state.items = Array.isArray(items) ? items : [];
                    this.state.total = result.data.meta?.total || result.meta?.total || this.state.items.length;
                    this.render();
                } else {
                    throw new Error(result.error || 'Failed to load jobs');
                }
            } catch (err) {
                this.showError(err.message);
            }
        },

        render() {
            if (!this.state.items.length) {
                this.showEmpty();
                return;
            }
            if (this.el.tableBody) {
                this.el.tableBody.innerHTML = this.state.items.map(job => `
                    <tr data-id="${job.id}">
                        <td>${esc(job.id)}</td>
                        <td>${esc(job.job_title)}</td>
                        <td>${esc(job.job_type)}</td>
                        <td>${esc(job.experience_level)}</td>
                        <td>${esc(job.category_name || '')}</td>
                        <td>${job.status === 'published' ? '<span class="badge badge-success">Published</span>' : '<span class="badge badge-secondary">Draft</span>'}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="Workspace.jobs.edit(${job.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="Workspace.jobs.delete(${job.id})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `).join('');
            }
            this.hideLoading();
            this.updatePagination();
        },

        showLoading() {
            if (this.el.loading) this.el.loading.style.display = 'flex';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (this.el.emptyState) this.el.emptyState.style.display = 'none';
        },

        hideLoading() {
            if (this.el.loading) this.el.loading.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'block';
        },

        showEmpty() {
            if (this.el.loading) this.el.loading.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (this.el.emptyState) this.el.emptyState.style.display = 'block';
        },

        showError(msg) {
            showNotification(msg, 'error');
            this.showEmpty();
        },

        updatePagination() {
            const totalPages = Math.ceil(this.state.total / this.state.perPage);
            if (this.el.paginationInfo) {
                const start = (this.state.page - 1) * this.state.perPage + 1;
                const end = Math.min(this.state.page * this.state.perPage, this.state.total);
                this.el.paginationInfo.textContent = `${start}-${end} of ${this.state.total}`;
            }
            if (!this.el.pagination) return;
            if (totalPages <= 1) {
                this.el.pagination.innerHTML = '';
                return;
            }
            let html = '<ul>';
            // Previous
            html += `<li class="${this.state.page <= 1 ? 'disabled' : ''}"><a href="#" onclick="Workspace.jobs.load(${this.state.page - 1}); return false;">‹</a></li>`;
            for (let i = 1; i <= totalPages; i++) {
                if (i === this.state.page) {
                    html += `<li class="active"><span>${i}</span></li>`;
                } else {
                    html += `<li><a href="#" onclick="Workspace.jobs.load(${i}); return false;">${i}</a></li>`;
                }
            }
            html += `<li class="${this.state.page >= totalPages ? 'disabled' : ''}"><a href="#" onclick="Workspace.jobs.load(${this.state.page + 1}); return false;">›</a></li>`;
            html += '</ul>';
            this.el.pagination.innerHTML = html;
        },

        showForm(job = null) {
            if (this.el.formContainer) this.el.formContainer.style.display = 'block';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            // Reset to first tab
            document.querySelectorAll('.form-tabs .tab-btn').forEach((b, i) => b.classList.toggle('active', i === 0));
            document.querySelectorAll('.tab-content').forEach((c, i) => { c.style.display = i === 0 ? 'block' : 'none'; });
            if (job) {
                if (this.el.formTitle) this.el.formTitle.textContent = t('form.edit_title', 'Edit Job');
                if (this.el.jobTitle) this.el.jobTitle.value = job.job_title || '';
                if (this.el.jobSlug) this.el.jobSlug.value = job.slug || '';
                if (this.el.jobType) this.el.jobType.value = job.job_type || '';
                if (this.el.employmentType) this.el.employmentType.value = job.employment_type || 'permanent';
                if (this.el.experienceLevel) this.el.experienceLevel.value = job.experience_level || '';
                if (this.el.jobCategory) this.el.jobCategory.value = job.category || '';
                if (this.el.jobDepartment) this.el.jobDepartment.value = job.department || '';
                if (this.el.positionsAvailable) this.el.positionsAvailable.value = job.positions_available || 1;
                if (this.el.jobStatus) this.el.jobStatus.value = job.status || 'draft';
                if (this.el.startDate) this.el.startDate.value = job.start_date || '';
                if (this.el.isFeatured) this.el.isFeatured.checked = !!parseInt(job.is_featured);
                if (this.el.isUrgent) this.el.isUrgent.checked = !!parseInt(job.is_urgent);
                if (this.el.salaryMin) this.el.salaryMin.value = job.salary_min || '';
                if (this.el.salaryMax) this.el.salaryMax.value = job.salary_max || '';
                if (this.el.salaryCurrency) this.el.salaryCurrency.value = job.salary_currency || 'SAR';
                if (this.el.salaryPeriod) this.el.salaryPeriod.value = job.salary_period || 'monthly';
                if (this.el.salaryNegotiable) this.el.salaryNegotiable.checked = !!parseInt(job.salary_negotiable);
                if (this.el.countryId) {
                    this.el.countryId.value = job.country_id || '';
                    // Load cities for the selected country, then set city
                    if (job.country_id) {
                        this.loadCitiesForCountry(job.country_id).then(() => {
                            if (this.el.cityId) this.el.cityId.value = job.city_id || '';
                        });
                    }
                }
                if (!job.country_id && this.el.cityId) this.el.cityId.value = job.city_id || '';
                if (this.el.workLocation) this.el.workLocation.value = job.work_location || 'onsite';
                if (this.el.isRemote) this.el.isRemote.checked = !!parseInt(job.is_remote);
                if (this.el.applicationFormType) this.el.applicationFormType.value = job.application_form_type || 'simple';
                if (this.el.externalApplicationUrl) this.el.externalApplicationUrl.value = job.external_application_url || '';
                if (this.el.applicationDeadline) this.el.applicationDeadline.value = job.application_deadline ? job.application_deadline.replace(' ', 'T').substring(0, 16) : '';
                if (this.el.jobEntityId) this.el.jobEntityId.value = job.entity_id || '';
                // hidden id
                const formIdEl = document.getElementById('formId');
                if (formIdEl) formIdEl.value = job.id || '';
            } else {
                if (this.el.formTitle) this.el.formTitle.textContent = t('form.add_title', 'Add Job');
                if (this.el.form) this.el.form.reset();
            }
        },

        hideForm() {
            if (this.el.formContainer) this.el.formContainer.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'block';
        },

        async save(e) {
            e.preventDefault();
            const formIdEl = document.getElementById('formId');
            const id = formIdEl?.value || null;
            const data = {
                entity_id: this.el.jobEntityId?.value || null,
                job_title: this.el.jobTitle?.value || '',
                slug: this.el.jobSlug?.value || '',
                job_type: this.el.jobType?.value || 'full_time',
                employment_type: this.el.employmentType?.value || 'permanent',
                experience_level: this.el.experienceLevel?.value || 'entry',
                category: this.el.jobCategory?.value || '',
                department: this.el.jobDepartment?.value || '',
                positions_available: parseInt(this.el.positionsAvailable?.value) || 1,
                status: this.el.jobStatus?.value || 'draft',
                start_date: this.el.startDate?.value || null,
                is_featured: this.el.isFeatured?.checked ? 1 : 0,
                is_urgent: this.el.isUrgent?.checked ? 1 : 0,
                salary_min: this.el.salaryMin?.value || null,
                salary_max: this.el.salaryMax?.value || null,
                salary_currency: this.el.salaryCurrency?.value || 'SAR',
                salary_period: this.el.salaryPeriod?.value || 'monthly',
                salary_negotiable: this.el.salaryNegotiable?.checked ? 1 : 0,
                country_id: this.el.countryId?.value || null,
                city_id: this.el.cityId?.value || null,
                work_location: this.el.workLocation?.value || null,
                is_remote: this.el.isRemote?.checked ? 1 : 0,
                application_form_type: this.el.applicationFormType?.value || 'simple',
                external_application_url: this.el.externalApplicationUrl?.value || null,
                application_deadline: this.el.applicationDeadline?.value || null,
                tenant_id: state.tenantId,
                created_by: state.userId
            };
            if (!data.job_title) {
                showNotification('Job title is required', 'error');
                return;
            }
            if (id) data.id = id;
            try {
                const method = id ? 'PUT' : 'POST';
                const result = await apiCall(API.jobs, {
                    method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                if (result.success) {
                    showNotification(id ? t('strings.update_success', 'Updated') : t('strings.save_success', 'Saved'), 'success');
                    this.hideForm();
                    this.load(this.state.page);
                } else {
                    throw new Error(result.error || result.message || 'Save failed');
                }
            } catch (err) {
                showNotification(err.message, 'error');
            }
        },

        async edit(id) {
            try {
                const result = await apiCall(`${API.jobs}?id=${id}&format=json&tenant_id=${state.tenantId}`);
                if (result.success) {
                    const job = Array.isArray(result.data) ? result.data[0] : result.data;
                    this.showForm(job);
                } else {
                    throw new Error('Job not found');
                }
            } catch (err) {
                showNotification(err.message, 'error');
            }
        },

        async delete(id) {
            if (!confirm(t('strings.delete_confirm', 'Are you sure?'))) return;
            try {
                const result = await apiCall(`${API.jobs}?id=${id}`, { method: 'DELETE' });
                if (result.success) {
                    showNotification(t('strings.delete_success', 'Deleted'), 'success');
                    this.load(this.state.page);
                } else {
                    throw new Error(result.error || 'Delete failed');
                }
            } catch (err) {
                showNotification(err.message, 'error');
            }
        },

        applyFilters() {
            this.state.filters = {
                search: this.el.searchInput?.value || '',
                status: this.el.statusFilter?.value || '',
                job_type: this.el.jobTypeFilter?.value || '',
                experience_level: this.el.experienceFilter?.value || ''
            };
            this.load(1);
        },

        resetFilters() {
            if (this.el.searchInput) this.el.searchInput.value = '';
            if (this.el.statusFilter) this.el.statusFilter.value = '';
            if (this.el.jobTypeFilter) this.el.jobTypeFilter.value = '';
            if (this.el.experienceFilter) this.el.experienceFilter.value = '';
            this.state.filters = {};
            this.load(1);
        },

        exportToExcel() {
            if (!this.state.items.length) {
                showNotification('No data to export', 'warning');
                return;
            }
            const columns = [
                { field: 'id', label: 'ID' },
                { field: 'job_title', label: 'Job Title' },
                { field: 'job_type', label: 'Job Type' },
                { field: 'experience_level', label: 'Experience' },
                { field: 'category_name', label: 'Category' },
                { field: 'status', label: 'Status' },
            ];
            exportToCSV(this.state.items, 'jobs_export.csv', columns);
        },

        async loadDropdownData() {
            // Load categories
            try {
                const catResult = await apiCall(`${API.categories}?page=1&limit=5000&tenant_id=${state.tenantId}&lang=${state.language}&format=json`);
                if (catResult.success) {
                    const cats = catResult.data?.items || catResult.data || [];
                    const catEl = this.el.jobCategory;
                    const catFilter = document.getElementById('categoryFilter');
                    [catEl, catFilter].forEach(sel => {
                        if (!sel) return;
                        const first = sel.options[0];
                        sel.innerHTML = '';
                        if (first) sel.appendChild(first.cloneNode(true));
                        cats.forEach(c => {
                            const o = document.createElement('option');
                            o.value = c.id;
                            o.textContent = c.name || c.category_name || c.id;
                            sel.appendChild(o);
                        });
                    });
                }
            } catch (e) { console.warn('[Jobs] Failed to load categories', e); }

            // Load currencies
            try {
                const curResult = await apiCall(`${API.currencies}?page=1&limit=500&format=json`);
                if (curResult.success) {
                    const curs = curResult.data?.items || curResult.data || [];
                    const curEl = this.el.salaryCurrency;
                    if (curEl && Array.isArray(curs) && curs.length > 0) {
                        const savedVal = curEl.value;
                        curEl.innerHTML = '';
                        curs.forEach(c => {
                            const o = document.createElement('option');
                            o.value = c.code || c.id;
                            o.textContent = `${c.code || c.id} – ${c.name || ''}`;
                            curEl.appendChild(o);
                        });
                        curEl.value = savedVal || 'SAR';
                    }
                }
            } catch (e) { console.warn('[Jobs] Failed to load currencies', e); }

            // Load countries
            try {
                const cntResult = await apiCall(`${API.countries}?page=1&limit=500&lang=${state.language}&format=json`);
                if (cntResult.success) {
                    const countries = cntResult.data?.items || cntResult.data || [];
                    const cntEl = this.el.countryId;
                    if (cntEl) {
                        const savedVal = cntEl.value;
                        cntEl.innerHTML = `<option value="">${t('form.fields.country_id.placeholder', 'Select Country')}</option>`;
                        countries.forEach(c => {
                            const o = document.createElement('option');
                            o.value = c.id;
                            o.textContent = c.name || c.translations?.[state.language] || c.id;
                            cntEl.appendChild(o);
                        });
                        if (savedVal) cntEl.value = savedVal;
                    }
                }
            } catch (e) { console.warn('[Jobs] Failed to load countries', e); }

            // Load languages for translation dropdown
            try {
                const langResult = await apiCall(`${API.languages}?page=1&limit=100&format=json`);
                if (langResult.success) {
                    const langs = langResult.data?.items || langResult.data || [];
                    const langEl = document.getElementById('translationLanguage');
                    if (langEl && Array.isArray(langs)) {
                        langEl.innerHTML = `<option value="">${t('form.translations.choose_lang', 'Select Language')}</option>`;
                        langs.forEach(l => {
                            const o = document.createElement('option');
                            o.value = l.code;
                            o.textContent = l.name;
                            langEl.appendChild(o);
                        });
                    }
                }
            } catch (e) { console.warn('[Jobs] Failed to load languages', e); }
        },

        async loadCitiesForCountry(countryId) {
            const cityEl = this.el.cityId;
            if (!cityEl) return;
            cityEl.innerHTML = `<option value="">${t('form.fields.city_id.placeholder', 'Select City')}</option>`;
            if (!countryId) return;
            try {
                const result = await apiCall(`${API.cities}?country_id=${countryId}&page=1&limit=2000&lang=${state.language}&format=json`);
                if (result.success) {
                    const cities = result.data?.items || result.data || [];
                    cities.forEach(c => {
                        const o = document.createElement('option');
                        o.value = c.id;
                        o.textContent = c.name || c.id;
                        cityEl.appendChild(o);
                    });
                }
            } catch (e) { console.warn('[Jobs] Failed to load cities', e); }
        }
    };

    // ============================================================
    // MODULE: Applications
    // ============================================================
    const applicationsModule = {
        state: { page: 1, perPage: 25, total: 0, items: [], filters: {} },
        el: {},
        init() {
            this.cacheElements();
            this.attachEvents();
        },
        cacheElements() {
            this.el = {
                container: document.getElementById('applicationsTab'),
                formContainer: document.getElementById('applicationFormContainer'),
                tableContainer: document.getElementById('appsTableContainer'),
                tableBody: document.getElementById('applicationsTableBody'),
                loading: document.getElementById('appsTableLoading'),
                emptyState: document.getElementById('appsEmptyState'),
                pagination: document.getElementById('appsPagination'),
                paginationInfo: document.getElementById('appsPaginationInfo'),
                exportBtn: document.getElementById('appsExportBtn'),
                applyFilters: document.getElementById('appsApplyFilters'),
                resetFilters: document.getElementById('appsResetFilters'),
                searchInput: document.getElementById('appsSearchInput'),
                statusFilter: document.getElementById('appsStatusFilter'),
                jobFilter: document.getElementById('appsJobFilter'),
                form: document.getElementById('applicationForm'),
                formTitle: document.getElementById('applicationFormTitle'),
                closeForm: document.getElementById('applicationCloseForm'),
                cancelForm: document.getElementById('applicationCancelForm'),
                // form fields
                appId: document.getElementById('applicationId'),
                appStatus: document.getElementById('applicationStatus'),
                appRating: document.getElementById('applicationRating'),
                appNotes: document.getElementById('applicationNotes')
            };
        },
        attachEvents() {
            if (this.el.exportBtn) this.el.exportBtn.onclick = () => this.exportToExcel();
            if (this.el.applyFilters) this.el.applyFilters.onclick = () => this.applyFilters();
            if (this.el.resetFilters) this.el.resetFilters.onclick = () => this.resetFilters();
            if (this.el.form) this.el.form.onsubmit = (e) => this.save(e);
            if (this.el.closeForm) this.el.closeForm.onclick = () => this.hideForm();
            if (this.el.cancelForm) this.el.cancelForm.onclick = () => this.hideForm();
        },
        async load(page = 1) {
            this.state.page = page;
            this.showLoading();
            try {
                const params = new URLSearchParams({
                    page: page,
                    limit: this.state.perPage,
                    tenant_id: state.tenantId,
                    lang: state.language,
                    format: 'json'
                });
                Object.entries(this.state.filters).forEach(([k, v]) => {
                    if (v) params.set(k, v);
                });
                const result = await apiCall(`${API.applications}?${params}`);
                if (result.success) {
                    const items = result.data.items || result.data;
                    this.state.items = Array.isArray(items) ? items : [];
                    this.state.total = result.data.meta?.total || result.meta?.total || this.state.items.length;
                    this.render();
                } else {
                    throw new Error(result.error || 'Failed to load applications');
                }
            } catch (err) {
                this.showError(err.message);
            }
        },
        render() {
            if (!this.state.items.length) {
                this.showEmpty();
                return;
            }
            if (this.el.tableBody) {
                this.el.tableBody.innerHTML = this.state.items.map(app => `
                    <tr data-id="${app.id}">
                        <td>${esc(app.id)}</td>
                        <td>${esc(app.job_title || '')}</td>
                        <td>${esc(app.full_name || '')}</td>
                        <td>${esc(app.email || '')}</td>
                        <td>${esc(app.phone || '')}</td>
                        <td><span class="badge badge-${app.status === 'rejected' ? 'danger' : (app.status === 'accepted' ? 'success' : 'info')}">${esc(app.status || 'submitted')}</span></td>
                        <td>${app.rating ? esc(app.rating) : '-'}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="Workspace.applications.edit(${app.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="Workspace.applications.delete(${app.id})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `).join('');
            }
            this.hideLoading();
            this.updatePagination();
        },
        showLoading() {
            if (this.el.loading) this.el.loading.style.display = 'flex';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (this.el.emptyState) this.el.emptyState.style.display = 'none';
        },
        hideLoading() {
            if (this.el.loading) this.el.loading.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'block';
        },
        showEmpty() {
            if (this.el.loading) this.el.loading.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (this.el.emptyState) this.el.emptyState.style.display = 'block';
        },
        showError(msg) {
            showNotification(msg, 'error');
            this.showEmpty();
        },
        updatePagination() {
            const totalPages = Math.ceil(this.state.total / this.state.perPage);
            if (this.el.paginationInfo) {
                const start = (this.state.page - 1) * this.state.perPage + 1;
                const end = Math.min(this.state.page * this.state.perPage, this.state.total);
                this.el.paginationInfo.textContent = `${start}-${end} of ${this.state.total}`;
            }
            if (!this.el.pagination) return;
            if (totalPages <= 1) {
                this.el.pagination.innerHTML = '';
                return;
            }
            let html = '<ul>';
            html += `<li class="${this.state.page <= 1 ? 'disabled' : ''}"><a href="#" onclick="Workspace.applications.load(${this.state.page - 1}); return false;">‹</a></li>`;
            for (let i = 1; i <= totalPages; i++) {
                if (i === this.state.page) {
                    html += `<li class="active"><span>${i}</span></li>`;
                } else {
                    html += `<li><a href="#" onclick="Workspace.applications.load(${i}); return false;">${i}</a></li>`;
                }
            }
            html += `<li class="${this.state.page >= totalPages ? 'disabled' : ''}"><a href="#" onclick="Workspace.applications.load(${this.state.page + 1}); return false;">›</a></li>`;
            html += '</ul>';
            this.el.pagination.innerHTML = html;
        },
        showForm(application = null) {
            if (this.el.formContainer) this.el.formContainer.style.display = 'block';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (application) {
                this.el.formTitle.textContent = 'Edit Application';
                if (this.el.appId) this.el.appId.value = application.id || '';
                if (this.el.appStatus) this.el.appStatus.value = application.status || 'submitted';
                if (this.el.appRating) this.el.appRating.value = application.rating || '';
                if (this.el.appNotes) this.el.appNotes.value = application.notes || '';
            } else {
                this.el.formTitle.textContent = 'Add Application';
                if (this.el.form) this.el.form.reset();
            }
        },
        hideForm() {
            if (this.el.formContainer) this.el.formContainer.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'block';
        },
        async save(e) {
            e.preventDefault();
            const data = {
                id: this.el.appId?.value || null,
                status: this.el.appStatus?.value,
                rating: this.el.appRating?.value,
                notes: this.el.appNotes?.value
            };
            try {
                let result;
                if (data.id) {
                    result = await apiCall(API.applications, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                } else {
                    // create - you might need additional fields
                    showNotification('Create not implemented yet', 'warning');
                    return;
                }
                if (result.success) {
                    showNotification(t('strings.update_success'), 'success');
                    this.hideForm();
                    this.load(this.state.page);
                } else {
                    throw new Error(result.error || 'Save failed');
                }
            } catch (err) {
                showNotification(err.message, 'error');
            }
        },
        async edit(id) {
            try {
                const result = await apiCall(`${API.applications}?id=${id}&format=json&tenant_id=${state.tenantId}`);
                if (result.success) {
                    const app = Array.isArray(result.data) ? result.data[0] : result.data;
                    this.showForm(app);
                } else {
                    throw new Error('Application not found');
                }
            } catch (err) {
                showNotification(err.message, 'error');
            }
        },
        async delete(id) {
            if (!confirm(t('strings.delete_confirm'))) return;
            try {
                const result = await apiCall(`${API.applications}?id=${id}`, { method: 'DELETE' });
                if (result.success) {
                    showNotification(t('strings.delete_success'), 'success');
                    this.load(this.state.page);
                } else {
                    throw new Error(result.error || 'Delete failed');
                }
            } catch (err) {
                showNotification(err.message, 'error');
            }
        },
        applyFilters() {
            this.state.filters = {
                search: this.el.searchInput?.value || '',
                status: this.el.statusFilter?.value || '',
                job_id: this.el.jobFilter?.value || ''
            };
            this.load(1);
        },
        resetFilters() {
            if (this.el.searchInput) this.el.searchInput.value = '';
            if (this.el.statusFilter) this.el.statusFilter.value = '';
            if (this.el.jobFilter) this.el.jobFilter.value = '';
            this.state.filters = {};
            this.load(1);
        },
        exportToExcel() {
            if (!this.state.items.length) {
                showNotification('No data to export', 'warning');
                return;
            }
            const columns = [
                { field: 'id', label: 'ID' },
                { field: 'job_title', label: 'Job Title' },
                { field: 'full_name', label: 'Applicant' },
                { field: 'email', label: 'Email' },
                { field: 'phone', label: 'Phone' },
                { field: 'status', label: 'Status' },
                { field: 'rating', label: 'Rating' }
            ];
            exportToCSV(this.state.items, 'applications_export.csv', columns);
        }
    };

    // ============================================================
    // MODULE: Interviews (similar structure, abbreviated)
    // ============================================================
    const interviewsModule = {
        state: { page: 1, perPage: 25, total: 0, items: [], filters: {} },
        el: {},
        init() { this.cacheElements(); this.attachEvents(); },
        cacheElements() {
            this.el = {
                container: document.getElementById('interviewsTab'),
                formContainer: document.getElementById('interviewFormContainer'),
                tableContainer: document.getElementById('interviewsTableContainer'),
                tableBody: document.getElementById('interviewsTableBody'),
                loading: document.getElementById('interviewsTableLoading'),
                emptyState: document.getElementById('interviewsEmptyState'),
                pagination: document.getElementById('interviewsPagination'),
                paginationInfo: document.getElementById('interviewsPaginationInfo'),
                exportBtn: document.getElementById('interviewsExportBtn'),
                applyFilters: document.getElementById('interviewsApplyFilters'),
                resetFilters: document.getElementById('interviewsResetFilters'),
                searchInput: document.getElementById('interviewsSearchInput'),
                statusFilter: document.getElementById('interviewsStatusFilter'),
                typeFilter: document.getElementById('interviewsTypeFilter'),
                form: document.getElementById('interviewForm'),
                closeForm: document.getElementById('interviewCloseForm'),
                cancelForm: document.getElementById('interviewCancelForm'),
                interviewId: document.getElementById('interviewId'),
                interviewType: document.getElementById('interviewType'),
                interviewDate: document.getElementById('interviewDate'),
                interviewDuration: document.getElementById('interviewDuration'),
                interviewStatus: document.getElementById('interviewStatus'),
                interviewFeedback: document.getElementById('interviewFeedback')
            };
        },
        attachEvents() {
            if (this.el.exportBtn) this.el.exportBtn.onclick = () => this.exportToExcel();
            if (this.el.applyFilters) this.el.applyFilters.onclick = () => this.applyFilters();
            if (this.el.resetFilters) this.el.resetFilters.onclick = () => this.resetFilters();
            if (this.el.form) this.el.form.onsubmit = (e) => this.save(e);
            if (this.el.closeForm) this.el.closeForm.onclick = () => this.hideForm();
            if (this.el.cancelForm) this.el.cancelForm.onclick = () => this.hideForm();
        },
        async load(page = 1) {
            this.state.page = page;
            this.showLoading();
            try {
                const params = new URLSearchParams({
                    page, limit: this.state.perPage,
                    tenant_id: state.tenantId, lang: state.language, format: 'json'
                });
                Object.entries(this.state.filters).forEach(([k, v]) => { if (v) params.set(k, v); });
                const result = await apiCall(`${API.interviews}?${params}`);
                if (result.success) {
                    const items = result.data.items || result.data;
                    this.state.items = Array.isArray(items) ? items : [];
                    this.state.total = result.data.meta?.total || result.meta?.total || this.state.items.length;
                    this.render();
                } else {
                    throw new Error(result.error || 'Failed to load interviews');
                }
            } catch (err) { this.showError(err.message); }
        },
        render() {
            if (!this.state.items.length) { this.showEmpty(); return; }
            if (this.el.tableBody) {
                this.el.tableBody.innerHTML = this.state.items.map(iv => `
                    <tr data-id="${iv.id}">
                        <td>${esc(iv.id)}</td>
                        <td>${esc(iv.interview_type || '')}</td>
                        <td>${esc(iv.interview_date || '')}</td>
                        <td><span class="badge badge-${iv.status === 'completed' ? 'success' : (iv.status === 'cancelled' ? 'danger' : 'info')}">${esc(iv.status || 'scheduled')}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="Workspace.interviews.edit(${iv.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="Workspace.interviews.delete(${iv.id})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `).join('');
            }
            this.hideLoading();
        },
        showLoading() {
            if (this.el.loading) this.el.loading.style.display = 'flex';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (this.el.emptyState) this.el.emptyState.style.display = 'none';
        },
        hideLoading() {
            if (this.el.loading) this.el.loading.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'block';
        },
        showEmpty() {
            if (this.el.loading) this.el.loading.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (this.el.emptyState) this.el.emptyState.style.display = 'block';
        },
        showError(msg) { showNotification(msg, 'error'); this.showEmpty(); },
        showForm(interview = null) {
            if (this.el.formContainer) this.el.formContainer.style.display = 'block';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (interview) {
                if (this.el.interviewId) this.el.interviewId.value = interview.id || '';
                if (this.el.interviewType) this.el.interviewType.value = interview.interview_type || '';
                if (this.el.interviewDate) this.el.interviewDate.value = interview.interview_date || '';
                if (this.el.interviewDuration) this.el.interviewDuration.value = interview.interview_duration || 60;
                if (this.el.interviewStatus) this.el.interviewStatus.value = interview.status || 'scheduled';
                if (this.el.interviewFeedback) this.el.interviewFeedback.value = interview.feedback || '';
            } else {
                if (this.el.form) this.el.form.reset();
            }
        },
        hideForm() {
            if (this.el.formContainer) this.el.formContainer.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'block';
        },
        async save(e) {
            e.preventDefault();
            const data = {
                id: this.el.interviewId?.value || null,
                interview_type: this.el.interviewType?.value,
                interview_date: this.el.interviewDate?.value,
                interview_duration: this.el.interviewDuration?.value || 60,
                status: this.el.interviewStatus?.value,
                feedback: this.el.interviewFeedback?.value
            };
            try {
                const method = data.id ? 'PUT' : 'POST';
                const result = await apiCall(API.interviews, {
                    method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                if (result.success) {
                    showNotification(t('strings.save_success'), 'success');
                    this.hideForm();
                    this.load(this.state.page);
                } else { throw new Error(result.error || 'Save failed'); }
            } catch (err) { showNotification(err.message, 'error'); }
        },
        async edit(id) {
            try {
                const result = await apiCall(`${API.interviews}?id=${id}&format=json&tenant_id=${state.tenantId}`);
                if (result.success) { this.showForm(Array.isArray(result.data) ? result.data[0] : result.data); }
                else { throw new Error('Interview not found'); }
            } catch (err) { showNotification(err.message, 'error'); }
        },
        async delete(id) {
            if (!confirm(t('strings.delete_confirm'))) return;
            try {
                const result = await apiCall(`${API.interviews}?id=${id}`, { method: 'DELETE' });
                if (result.success) { showNotification(t('strings.delete_success'), 'success'); this.load(this.state.page); }
                else { throw new Error(result.error || 'Delete failed'); }
            } catch (err) { showNotification(err.message, 'error'); }
        },
        applyFilters() {
            this.state.filters = {
                search: this.el.searchInput?.value || '',
                status: this.el.statusFilter?.value || '',
                interview_type: this.el.typeFilter?.value || ''
            };
            this.load(1);
        },
        resetFilters() {
            if (this.el.searchInput) this.el.searchInput.value = '';
            if (this.el.statusFilter) this.el.statusFilter.value = '';
            if (this.el.typeFilter) this.el.typeFilter.value = '';
            this.state.filters = {};
            this.load(1);
        },
        exportToExcel() {
            if (!this.state.items.length) { showNotification('No data to export', 'warning'); return; }
            exportToCSV(this.state.items, 'interviews_export.csv', [
                { field: 'id', label: 'ID' }, { field: 'interview_type', label: 'Type' },
                { field: 'interview_date', label: 'Date' }, { field: 'status', label: 'Status' }
            ]);
        }
    };

    // ============================================================
    // MODULE: Alerts (similar skeleton)
    // ============================================================
    const alertsModule = {
        // ... similar pattern
        state: { page: 1, perPage: 25, total: 0, items: [], filters: {} },
        el: {},
        init() { this.cacheElements(); this.attachEvents(); },
        cacheElements() {
            this.el = {
                container: document.getElementById('alertsTab'),
                formContainer: document.getElementById('alertFormContainer'),
                tableContainer: document.getElementById('alertsTableContainer'),
                tableBody: document.getElementById('alertsTableBody'),
                loading: document.getElementById('alertsTableLoading'),
                emptyState: document.getElementById('alertsEmptyState'),
                pagination: document.getElementById('alertsPagination'),
                paginationInfo: document.getElementById('alertsPaginationInfo'),
                addBtn: document.getElementById('alertsAddBtn'),
                exportBtn: document.getElementById('alertsExportBtn'),
                applyFilters: document.getElementById('alertsApplyFilters'),
                resetFilters: document.getElementById('alertsResetFilters'),
                searchInput: document.getElementById('alertsSearchInput'),
                activeFilter: document.getElementById('alertsActiveFilter'),
                frequencyFilter: document.getElementById('alertsFrequencyFilter'),
                form: document.getElementById('alertForm'),
                closeForm: document.getElementById('alertCloseForm'),
                cancelForm: document.getElementById('alertCancelForm'),
                // form fields
                alertId: document.getElementById('alertId'),
                alertName: document.getElementById('alertName'),
                alertKeywords: document.getElementById('alertKeywords'),
                alertFrequency: document.getElementById('alertFrequency'),
                alertIsActive: document.getElementById('alertIsActive')
            };
        },
        attachEvents() {
            if (this.el.addBtn) this.el.addBtn.onclick = () => this.showForm();
            if (this.el.exportBtn) this.el.exportBtn.onclick = () => this.exportToExcel();
            if (this.el.applyFilters) this.el.applyFilters.onclick = () => this.applyFilters();
            if (this.el.resetFilters) this.el.resetFilters.onclick = () => this.resetFilters();
            if (this.el.form) this.el.form.onsubmit = (e) => this.save(e);
            if (this.el.closeForm) this.el.closeForm.onclick = () => this.hideForm();
            if (this.el.cancelForm) this.el.cancelForm.onclick = () => this.hideForm();
        },
        async load(page = 1) {
            this.state.page = page;
            this.showLoading();
            try {
                const params = new URLSearchParams({
                    page, limit: this.state.perPage,
                    tenant_id: state.tenantId, lang: state.language, format: 'json'
                });
                Object.entries(this.state.filters).forEach(([k, v]) => { if (v) params.set(k, v); });
                const result = await apiCall(`${API.alerts}?${params}`);
                if (result.success) {
                    const items = result.data.items || result.data;
                    this.state.items = Array.isArray(items) ? items : [];
                    this.state.total = result.data.meta?.total || result.meta?.total || this.state.items.length;
                    this.render();
                } else {
                    throw new Error(result.error || 'Failed to load alerts');
                }
            } catch (err) { this.showError(err.message); }
        },
        render() {
            if (!this.state.items.length) { this.showEmpty(); return; }
            if (this.el.tableBody) {
                this.el.tableBody.innerHTML = this.state.items.map(a => `
                    <tr data-id="${a.id}">
                        <td>${esc(a.id)}</td>
                        <td>${esc(a.alert_name || '')}</td>
                        <td>${esc(a.keywords || '')}</td>
                        <td>${esc(a.frequency || 'daily')}</td>
                        <td><span class="badge badge-${a.is_active ? 'success' : 'secondary'}">${a.is_active ? 'Active' : 'Inactive'}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="Workspace.alerts.edit(${a.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="Workspace.alerts.delete(${a.id})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `).join('');
            }
            this.hideLoading();
        },
        showLoading() {
            if (this.el.loading) this.el.loading.style.display = 'flex';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (this.el.emptyState) this.el.emptyState.style.display = 'none';
        },
        hideLoading() {
            if (this.el.loading) this.el.loading.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'block';
        },
        showEmpty() {
            if (this.el.loading) this.el.loading.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (this.el.emptyState) this.el.emptyState.style.display = 'block';
        },
        showError(msg) { showNotification(msg, 'error'); this.showEmpty(); },
        showForm(alert = null) {
            if (this.el.formContainer) this.el.formContainer.style.display = 'block';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (alert) {
                if (this.el.alertId) this.el.alertId.value = alert.id || '';
                if (this.el.alertName) this.el.alertName.value = alert.alert_name || '';
                if (this.el.alertKeywords) this.el.alertKeywords.value = alert.keywords || '';
                if (this.el.alertFrequency) this.el.alertFrequency.value = alert.frequency || 'daily';
                if (this.el.alertIsActive) this.el.alertIsActive.checked = !!alert.is_active;
            } else {
                if (this.el.form) this.el.form.reset();
            }
        },
        hideForm() {
            if (this.el.formContainer) this.el.formContainer.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'block';
        },
        async save(e) {
            e.preventDefault();
            const data = {
                id: this.el.alertId?.value || null,
                alert_name: this.el.alertName?.value,
                keywords: this.el.alertKeywords?.value,
                frequency: this.el.alertFrequency?.value || 'daily',
                is_active: this.el.alertIsActive?.checked ? 1 : 0
            };
            if (!data.alert_name) { showNotification('Alert name is required', 'error'); return; }
            try {
                const method = data.id ? 'PUT' : 'POST';
                const result = await apiCall(API.alerts, {
                    method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data)
                });
                if (result.success) {
                    showNotification(t('strings.save_success'), 'success');
                    this.hideForm();
                    this.load(this.state.page);
                } else { throw new Error(result.error || 'Save failed'); }
            } catch (err) { showNotification(err.message, 'error'); }
        },
        async edit(id) {
            try {
                const result = await apiCall(`${API.alerts}?id=${id}&format=json&tenant_id=${state.tenantId}`);
                if (result.success) { this.showForm(Array.isArray(result.data) ? result.data[0] : result.data); }
                else { throw new Error('Alert not found'); }
            } catch (err) { showNotification(err.message, 'error'); }
        },
        async delete(id) {
            if (!confirm(t('strings.delete_confirm'))) return;
            try {
                const result = await apiCall(`${API.alerts}?id=${id}`, { method: 'DELETE' });
                if (result.success) { showNotification(t('strings.delete_success'), 'success'); this.load(this.state.page); }
                else { throw new Error(result.error || 'Delete failed'); }
            } catch (err) { showNotification(err.message, 'error'); }
        },
        applyFilters() {
            this.state.filters = {
                search: this.el.searchInput?.value || '',
                is_active: this.el.activeFilter?.value,
                frequency: this.el.frequencyFilter?.value || ''
            };
            this.load(1);
        },
        resetFilters() {
            if (this.el.searchInput) this.el.searchInput.value = '';
            if (this.el.activeFilter) this.el.activeFilter.value = '';
            if (this.el.frequencyFilter) this.el.frequencyFilter.value = '';
            this.state.filters = {};
            this.load(1);
        },
        exportToExcel() {
            if (!this.state.items.length) { showNotification('No data to export', 'warning'); return; }
            exportToCSV(this.state.items, 'alerts_export.csv', [
                { field: 'id', label: 'ID' }, { field: 'alert_name', label: 'Name' },
                { field: 'keywords', label: 'Keywords' }, { field: 'frequency', label: 'Frequency' },
                { field: 'is_active', label: 'Active' }
            ]);
        }
        // Implement similar methods as applicationsModule
    };

    // ============================================================
    // MODULE: Questions
    // ============================================================
    const questionsModule = {
        state: { page: 1, perPage: 25, total: 0, items: [], filters: {} },
        el: {},
        init() {
            this.cacheElements();
            this.attachEvents();
            // load jobs for dropdown
            this.loadJobsForDropdown();
        },
        cacheElements() {
            this.el = {
                container: document.getElementById('questionsTab'),
                formContainer: document.getElementById('questionFormContainer'),
                tableContainer: document.getElementById('questionsTableContainer'),
                tableBody: document.getElementById('questionsTableBody'),
                loading: document.getElementById('questionsTableLoading'),
                emptyState: document.getElementById('questionsEmptyState'),
                pagination: document.getElementById('questionsPagination'),
                paginationInfo: document.getElementById('questionsPaginationInfo'),
                addBtn: document.getElementById('questionsAddBtn'),
                exportBtn: document.getElementById('questionsExportBtn'),
                applyFilters: document.getElementById('questionsApplyFilters'),
                resetFilters: document.getElementById('questionsResetFilters'),
                searchInput: document.getElementById('questionsSearchInput'),
                jobFilter: document.getElementById('questionsJobFilter'),
                typeFilter: document.getElementById('questionsTypeFilter'),
                form: document.getElementById('questionForm'),
                closeForm: document.getElementById('questionCloseForm'),
                cancelForm: document.getElementById('questionCancelForm'),
                // form fields
                questionId: document.getElementById('questionId'),
                questionJobId: document.getElementById('questionJobId'),
                questionText: document.getElementById('questionText'),
                questionType: document.getElementById('questionType'),
                questionOptions: document.getElementById('questionOptions'),
                questionIsRequired: document.getElementById('questionIsRequired'),
                questionSortOrder: document.getElementById('questionSortOrder')
            };
        },
        attachEvents() {
            if (this.el.addBtn) this.el.addBtn.onclick = () => this.showForm();
            if (this.el.exportBtn) this.el.exportBtn.onclick = () => this.exportToExcel();
            if (this.el.applyFilters) this.el.applyFilters.onclick = () => this.applyFilters();
            if (this.el.resetFilters) this.el.resetFilters.onclick = () => this.resetFilters();
            if (this.el.form) this.el.form.onsubmit = (e) => this.save(e);
            if (this.el.closeForm) this.el.closeForm.onclick = () => this.hideForm();
            if (this.el.cancelForm) this.el.cancelForm.onclick = () => this.hideForm();
        },
        async loadJobsForDropdown() {
            try {
                const result = await apiCall(`${API.jobs}?page=1&limit=1000&tenant_id=${state.tenantId}&format=json&lang=${state.language}`);
                if (result.success) {
                    const jobs = result.data.items || result.data;
                    if (this.el.questionJobId) {
                        let options = '<option value="">Select Job</option>';
                        jobs.forEach(job => {
                            options += `<option value="${job.id}">${esc(job.job_title)}</option>`;
                        });
                        this.el.questionJobId.innerHTML = options;
                    }
                    if (this.el.jobFilter) {
                        let options = '<option value="">All Jobs</option>';
                        jobs.forEach(job => {
                            options += `<option value="${job.id}">${esc(job.job_title)}</option>`;
                        });
                        this.el.jobFilter.innerHTML = options;
                    }
                }
            } catch (err) {
                console.warn('Failed to load jobs for dropdown', err);
            }
        },
        async load(page = 1) {
            this.state.page = page;
            this.showLoading();
            try {
                const params = new URLSearchParams({
                    page: page,
                    limit: this.state.perPage,
                    tenant_id: state.tenantId,
                    lang: state.language,
                    format: 'json'
                });
                Object.entries(this.state.filters).forEach(([k, v]) => {
                    if (v) params.set(k, v);
                });
                const result = await apiCall(`${API.questions}?${params}`);
                if (result.success) {
                    const items = result.data.items || result.data;
                    this.state.items = Array.isArray(items) ? items : [];
                    this.state.total = result.data.meta?.total || result.meta?.total || this.state.items.length;
                    this.render();
                } else {
                    throw new Error(result.error || 'Failed to load questions');
                }
            } catch (err) {
                this.showError(err.message);
            }
        },
        render() {
            if (!this.state.items.length) {
                this.showEmpty();
                return;
            }
            if (this.el.tableBody) {
                this.el.tableBody.innerHTML = this.state.items.map(q => `
                    <tr data-id="${q.id}">
                        <td>${esc(q.id)}</td>
                        <td>${esc(q.job_title || '')}</td>
                        <td>${esc(q.question_text)}</td>
                        <td>${esc(q.question_type)}</td>
                        <td>${q.is_required ? 'Yes' : 'No'}</td>
                        <td>${esc(q.sort_order)}</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="Workspace.questions.edit(${q.id})"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="Workspace.questions.delete(${q.id})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `).join('');
            }
            this.hideLoading();
            this.updatePagination();
        },
        showLoading() {
            if (this.el.loading) this.el.loading.style.display = 'flex';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (this.el.emptyState) this.el.emptyState.style.display = 'none';
        },
        hideLoading() {
            if (this.el.loading) this.el.loading.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'block';
        },
        showEmpty() {
            if (this.el.loading) this.el.loading.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (this.el.emptyState) this.el.emptyState.style.display = 'block';
        },
        showError(msg) {
            showNotification(msg, 'error');
            this.showEmpty();
        },
        updatePagination() {
            const totalPages = Math.ceil(this.state.total / this.state.perPage);
            if (this.el.paginationInfo) {
                const start = (this.state.page - 1) * this.state.perPage + 1;
                const end = Math.min(this.state.page * this.state.perPage, this.state.total);
                this.el.paginationInfo.textContent = `${start}-${end} of ${this.state.total}`;
            }
            if (!this.el.pagination) return;
            if (totalPages <= 1) {
                this.el.pagination.innerHTML = '';
                return;
            }
            let html = '<ul>';
            html += `<li class="${this.state.page <= 1 ? 'disabled' : ''}"><a href="#" onclick="Workspace.questions.load(${this.state.page - 1}); return false;">‹</a></li>`;
            for (let i = 1; i <= totalPages; i++) {
                if (i === this.state.page) {
                    html += `<li class="active"><span>${i}</span></li>`;
                } else {
                    html += `<li><a href="#" onclick="Workspace.questions.load(${i}); return false;">${i}</a></li>`;
                }
            }
            html += `<li class="${this.state.page >= totalPages ? 'disabled' : ''}"><a href="#" onclick="Workspace.questions.load(${this.state.page + 1}); return false;">›</a></li>`;
            html += '</ul>';
            this.el.pagination.innerHTML = html;
        },
        showForm(question = null) {
            if (this.el.formContainer) this.el.formContainer.style.display = 'block';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'none';
            if (question) {
                // populate
                if (this.el.questionId) this.el.questionId.value = question.id || '';
                if (this.el.questionJobId) this.el.questionJobId.value = question.job_id || '';
                if (this.el.questionText) this.el.questionText.value = question.question_text || '';
                if (this.el.questionType) this.el.questionType.value = question.question_type || 'text';
                if (this.el.questionOptions) this.el.questionOptions.value = question.options || '';
                if (this.el.questionIsRequired) this.el.questionIsRequired.checked = !!question.is_required;
                if (this.el.questionSortOrder) this.el.questionSortOrder.value = question.sort_order || 0;
            } else {
                if (this.el.form) this.el.form.reset();
            }
        },
        hideForm() {
            if (this.el.formContainer) this.el.formContainer.style.display = 'none';
            if (this.el.tableContainer) this.el.tableContainer.style.display = 'block';
        },
        async save(e) {
            e.preventDefault();
            const data = {
                id: this.el.questionId?.value || null,
                job_id: this.el.questionJobId?.value,
                question_text: this.el.questionText?.value,
                question_type: this.el.questionType?.value,
                options: this.el.questionOptions?.value,
                is_required: this.el.questionIsRequired?.checked ? 1 : 0,
                sort_order: this.el.questionSortOrder?.value || 0
            };
            if (!data.question_text) {
                showNotification('Question text is required', 'error');
                return;
            }
            try {
                let result;
                if (data.id) {
                    result = await apiCall(API.questions, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                } else {
                    result = await apiCall(API.questions, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    });
                }
                if (result.success) {
                    showNotification(t('strings.save_success'), 'success');
                    this.hideForm();
                    this.load(this.state.page);
                } else {
                    throw new Error(result.error || 'Save failed');
                }
            } catch (err) {
                showNotification(err.message, 'error');
            }
        },
        async edit(id) {
            try {
                const result = await apiCall(`${API.questions}?id=${id}&format=json&tenant_id=${state.tenantId}`);
                if (result.success) {
                    const q = Array.isArray(result.data) ? result.data[0] : result.data;
                    this.showForm(q);
                } else {
                    throw new Error('Question not found');
                }
            } catch (err) {
                showNotification(err.message, 'error');
            }
        },
        async delete(id) {
            if (!confirm(t('strings.delete_confirm'))) return;
            try {
                const result = await apiCall(`${API.questions}?id=${id}`, { method: 'DELETE' });
                if (result.success) {
                    showNotification(t('strings.delete_success'), 'success');
                    this.load(this.state.page);
                } else {
                    throw new Error(result.error || 'Delete failed');
                }
            } catch (err) {
                showNotification(err.message, 'error');
            }
        },
        applyFilters() {
            this.state.filters = {
                search: this.el.searchInput?.value || '',
                job_id: this.el.jobFilter?.value || '',
                question_type: this.el.typeFilter?.value || ''
            };
            this.load(1);
        },
        resetFilters() {
            if (this.el.searchInput) this.el.searchInput.value = '';
            if (this.el.jobFilter) this.el.jobFilter.value = '';
            if (this.el.typeFilter) this.el.typeFilter.value = '';
            this.state.filters = {};
            this.load(1);
        },
        exportToExcel() {
            if (!this.state.items.length) {
                showNotification('No data to export', 'warning');
                return;
            }
            const columns = [
                { field: 'id', label: 'ID' },
                { field: 'job_title', label: 'Job' },
                { field: 'question_text', label: 'Question' },
                { field: 'question_type', label: 'Type' },
                { field: 'is_required', label: 'Required' },
                { field: 'sort_order', label: 'Order' }
            ];
            exportToCSV(this.state.items, 'questions_export.csv', columns);
        }
    };

    // ============================================================
    // MAIN WORKSPACE CONTROLLER
    // ============================================================
    const Workspace = {
        modules: {
            jobs: jobsModule,
            applications: applicationsModule,
            interviews: interviewsModule,
            alerts: alertsModule,
            questions: questionsModule
        },

        async init() {
            console.log('[Workspace] Initializing...');
            this.cacheElements();
            await loadTranslations(state.language);
            this.setupTabs();
            // Initialize each module
            for (let key in this.modules) {
                if (this.modules[key].init) this.modules[key].init();
            }
            // Load initial tab data
            this.switchTab(state.activeTab);
        },

        cacheElements() {
            el.errorContainer = document.getElementById('errorContainer');
            el.errorMessage = document.getElementById('errorMessage');
            el.tabButtons = document.querySelectorAll('.workspace-tabs .tab-btn');
            el.tabContents = {
                jobs: document.getElementById('jobsTab'),
                applications: document.getElementById('applicationsTab'),
                interviews: document.getElementById('interviewsTab'),
                alerts: document.getElementById('alertsTab'),
                questions: document.getElementById('questionsTab')
            };
        },

        setupTabs() {
            el.tabButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const tab = btn.dataset.tab;
                    this.switchTab(tab);
                });
            });
        },

        switchTab(tab) {
            state.activeTab = tab;
            el.tabButtons.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.tab === tab);
            });
            for (let key in el.tabContents) {
                if (el.tabContents[key]) {
                    el.tabContents[key].style.display = key === tab ? 'block' : 'none';
                }
            }
            const module = this.modules[tab];
            if (module && typeof module.load === 'function' && module.state.items.length === 0) {
                module.load(1);
            }
        },

        showError(message) {
            if (el.errorContainer) {
                el.errorContainer.style.display = 'block';
                if (el.errorMessage) el.errorMessage.textContent = message;
            } else {
                showNotification(message, 'error');
            }
        },

        hideError() {
            if (el.errorContainer) el.errorContainer.style.display = 'none';
        }
    };

    // Expose globally
    window.Workspace = Workspace;
    window.Workspace.jobs = jobsModule;
    window.Workspace.applications = applicationsModule;
    window.Workspace.interviews = interviewsModule;
    window.Workspace.alerts = alertsModule;
    window.Workspace.questions = questionsModule;

    // Auto-initialize
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => Workspace.init());
    } else {
        Workspace.init();
    }

    console.log('[Workspace] Module loaded');
})();