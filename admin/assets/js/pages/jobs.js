(function () {
    'use strict';

    /**
     * /admin/assets/js/pages/jobs.js
     * Jobs Management Module - Complete Implementation
     * Based on entities.js and products.js patterns
     */

    // ════════════════════════════════════════════════════════════
    // CONFIGURATION & STATE
    // ════════════════════════════════════════════════════════════
    const CONFIG = window.JOBS_CONFIG || {};
    const AF = window.AdminFramework || {};
    const PERMS = window.PAGE_PERMISSIONS || {};

    const API = {
        jobs: CONFIG.apiUrl || '/api/jobs',
        languages: CONFIG.languagesApi || '/api/languages',
        categories: CONFIG.categoriesApi || '/api/job_categories',
        skills: CONFIG.skillsApi || '/api/job_skills'
    };

    const state = {
        page: 1,
        perPage: CONFIG.itemsPerPage || 25,
        total: 0,
        jobs: [],
        languages: [],
        categories: [],
        filters: {},
        currentJob: null,
        currentTranslations: [],
        currentSkills: [],
        permissions: PERMS,
        language: window.USER_LANGUAGE || CONFIG.lang || 'en',
        direction: window.USER_DIRECTION || 'ltr',
        csrfToken: window.CSRF_TOKEN || CONFIG.csrfToken || '',
        tenantId: window.APP_CONFIG?.TENANT_ID || 1,
        userId: window.APP_CONFIG?.USER_ID || null
    };

    let el = {}; // DOM elements cache
    let translations = {}; // i18n translations
    let _messageListenerAdded = false; // prevent duplicate message listeners

    // ════════════════════════════════════════════════════════════
    // TRANSLATIONS
    // ════════════════════════════════════════════════════════════
    async function loadTranslations(lang) {
        try {
            const url = `/languages/Jobs/${encodeURIComponent(lang || state.language)}.json`;
            console.log('[Jobs] Loading translations:', url);
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error(`Failed to load translations: ${res.status}`);
            const raw = await res.json();
            const s = raw.strings || raw;
            translations = buildTranslationsMap(s);
            if (raw.direction) setDirectionForLang(raw.direction === 'rtl' ? 'ar' : 'en');
            console.log('[Jobs] Translations loaded');
            applyTranslations();
        } catch (err) {
            console.warn('[Jobs] Translation load failed:', err);
            translations = {};
        }
    }

    function buildTranslationsMap(s) {
        const g = s.general || {};
        const j = s.job || {};
        const sk = s.skills || {};
        const tr = s.translations || {};
        const val = s.validation || {};
        const msg = s.messages || {};
        return {
            jobs: {
                title: s.title || s.jobs || 'Jobs',
                subtitle: s.job || 'Job',
                add_new: s.create || 'Add New',
                loading: s.loading || 'Loading...',
                retry: s.refresh || 'Retry'
            },
            tabs: {
                general: g.general || 'General',
                skills: sk.skills || 'Skills',
                translations: tr.translations || 'Translations'
            },
            form: {
                add_title: s.create || 'Add Job',
                edit_title: s.edit || 'Edit Job',
                fields: {
                    job_title: { label: j.job_title || 'Job Title', placeholder: j.job_title || 'Job Title', required: val.title_required || 'Title is required' },
                    job_type: { label: j.job_type || 'Job Type' },
                    experience_level: { label: j.experience_level || 'Experience Level' },
                    location: { label: j.location || 'Location', placeholder: j.location || 'Location' },
                    salary_min: { label: j.salary_min || 'Min Salary' },
                    salary_max: { label: j.salary_max || 'Max Salary' },
                    currency: { label: j.currency || 'Currency' },
                    category: { label: j.category || 'Category' },
                    description: { label: g.description || 'Description' },
                    requirements: { label: j.requirements || 'Requirements' },
                    responsibilities: { label: j.responsibilities || 'Responsibilities' },
                    benefits: { label: j.benefits || 'Benefits' },
                    application_deadline: { label: j.application_deadline || 'Application Deadline' },
                    status: { label: s.status || 'Status', active: s.active || 'Active', inactive: s.inactive || 'Inactive' },
                    is_remote: { label: j.is_remote || 'Remote Job', yes: g.yes || 'Yes', no: g.no || 'No' },
                    is_featured: { label: j.is_featured || 'Featured', yes: g.yes || 'Yes', no: g.no || 'No' }
                },
                buttons: {
                    save: s.save || 'Save',
                    cancel: s.cancel || 'Cancel',
                    add_skill: sk.add_skill || 'Add Skill',
                    add_translation: tr.add_translation || 'Add Translation'
                },
                skills: {
                    skill_name: sk.skill_name || 'Skill Name',
                    proficiency_level: sk.proficiency_level || 'Proficiency Level',
                    is_required: sk.is_required || 'Required',
                    select_skill: sk.select_skill || 'Select Skill'
                },
                translations: {
                    select_lang: tr.select_language || 'Select Language',
                    job_title: j.job_title || 'Job Title',
                    description: g.description || 'Description',
                    requirements: j.requirements || 'Requirements',
                    responsibilities: j.responsibilities || 'Responsibilities',
                    benefits: j.benefits || 'Benefits'
                }
            },
            filters: {
                search: s.search_placeholder || 'Search jobs...',
                search_placeholder: s.search_placeholder || 'Search jobs...',
                status: s.status || 'Status',
                job_type: j.job_type || 'Job Type',
                experience_level: j.experience_level || 'Experience Level',
                category: j.category || 'Category',
                status_options: { all: s.all || 'All', active: s.active || 'Active', inactive: s.inactive || 'Inactive' },
                apply: s.apply || 'Apply',
                reset: s.reset || 'Reset'
            },
            table: {
                headers: {
                    id: 'ID',
                    job_title: j.job_title || 'Job Title',
                    job_type: j.job_type || 'Job Type',
                    experience_level: j.experience_level || 'Experience Level',
                    location: j.location || 'Location',
                    category: j.category || 'Category',
                    status: s.status || 'Status',
                    actions: s.actions || 'Actions'
                },
                empty: {
                    title: s.no_jobs || 'No jobs found',
                    message: s.create || 'Add your first job',
                    add_first: s.create || 'Add Job'
                },
                actions: { delete: s.delete || 'Delete' }
            },
            pagination: { showing: s.showing || 'Showing' },
            messages: {
                error: { load_failed: msg.server_error || 'Error loading data' },
                success: { save_success: s.save_success || 'Job saved successfully', delete_success: s.delete_success || 'Job deleted successfully' }
            },
            strings: {
                save_success: s.save_success || 'Job saved successfully',
                update_success: s.update_success || 'Job updated successfully',
                delete_confirm: s.delete_confirm || 'Are you sure you want to delete this job?',
                delete_success: s.delete_success || 'Job deleted successfully',
                saving: s.saving || 'Saving...',
                loading: s.loading || 'Loading...'
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
        const container = document.getElementById('jobsPageContainer');
        if (!container) return;

        container.querySelectorAll('[data-i18n]').forEach(elem => {
            const key = elem.getAttribute('data-i18n');
            const txt = t(key);
            if (txt !== key) {
                if (elem.tagName === 'INPUT' && elem.type !== 'submit' && elem.type !== 'button') {
                    if (elem.hasAttribute('placeholder')) elem.placeholder = txt;
                } else {
                    elem.textContent = txt;
                }
            }
        });

        container.querySelectorAll('[data-i18n-placeholder]').forEach(elem => {
            const key = elem.getAttribute('data-i18n-placeholder');
            const txt = t(key);
            if (txt !== key) elem.placeholder = txt;
        });

        console.log('[Jobs] Translations applied to DOM');
    }

    function setDirectionForLang(lang) {
        const container = document.getElementById('jobsPageContainer');
        if (container) {
            container.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
        }
        state.direction = lang === 'ar' ? 'rtl' : 'ltr';
    }

    // ════════════════════════════════════════════════════════════
    // API HELPERS
    // ════════════════════════════════════════════════════════════
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
                if (!res.ok) {
                    throw new Error(data.error || data.message || `HTTP ${res.status}`);
                }
                return data;
            } else {
                const text = await res.text();
                if (!res.ok) {
                    throw new Error(text || `HTTP ${res.status}`);
                }
                try {
                    return JSON.parse(text);
                } catch {
                    return { success: true, data: text };
                }
            }
        } catch (err) {
            console.error('[Jobs] API call failed:', url, err);
            throw err;
        }
    }

    // ════════════════════════════════════════════════════════════
    // DATA LOADING
    // ════════════════════════════════════════════════════════════
    async function loadJobs(page = 1) {
        try {
            console.log('[Jobs] Loading page:', page);

            showLoading();

            state.page = page;
            const params = new URLSearchParams({
                page: page,
                limit: state.perPage,
                tenant_id: state.tenantId,
                lang: state.language,
                format: 'json'
            });

            // Add filters (skip empty values)
            Object.entries(state.filters).forEach(([key, val]) => {
                if (val !== undefined && val !== null && val !== '') {
                    params.set(key, val);
                }
            });

            console.log('[Jobs] API URL:', `${API.jobs}?${params}`);

            const result = await apiCall(`${API.jobs}?${params}`);
            console.log('[Jobs] API response:', result);

            if (result.success && result.data) {
                const items = result.data.items || result.data;
                const meta = result.data.meta || result.meta || {};

                state.jobs = Array.isArray(items) ? items : [];
                state.total = meta.total || state.jobs.length;

                await renderTable(state.jobs);
                updatePagination(meta.total !== undefined ? meta : { page, per_page: state.perPage, total: state.total });
                updateResultsCount(state.total);

                showTable();
            } else {
                throw new Error(result.error || result.message || 'Invalid response format');
            }
        } catch (err) {
            console.error('[Jobs] Load failed:', err);
            showError(err.message || t('messages.error.load_failed', 'Failed to load jobs'));
        }
    }

    async function loadDropdownData() {
        try {
            // Load languages
            try {
                const languagesResult = await apiCall(`${API.languages}?format=json`);
                if (languagesResult.success) {
                    const langsData = languagesResult.data?.items || languagesResult.data;
                    state.languages = Array.isArray(langsData) ? langsData : [];
                    populateDropdown(el.jobLangSelect, state.languages, 'code', 'name', t('form.translations.select_lang', 'Select language'));
                }
            } catch (err) {
                console.warn('[Jobs] Failed to load languages:', err);
            }

            // Load categories
            try {
                const categoriesResult = await apiCall(`${API.categories}?page=1&limit=1000&tenant_id=${state.tenantId}&lang=${state.language}&format=json`);
                if (categoriesResult.success) {
                    const categoriesData = categoriesResult.data?.items || categoriesResult.data;
                    state.categories = Array.isArray(categoriesData) ? categoriesData : [];
                    populateDropdown(el.jobCategory, state.categories, 'id', 'category_name', t('form.fields.category.label', 'Select category'));
                    populateDropdown(el.categoryFilter, state.categories, 'id', 'category_name', t('filters.all_categories', 'All Categories'));
                }
            } catch (err) {
                console.warn('[Jobs] Failed to load categories:', err);
            }

            console.log('[Jobs] Dropdown data loaded');
        } catch (err) {
            console.error('[Jobs] Failed to load dropdown data:', err);
        }
    }

    function populateDropdown(selectEl, data, valueKey, textKey, placeholder = '') {
        if (!selectEl) return;

        selectEl.innerHTML = '';

        if (placeholder) {
            const opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            selectEl.appendChild(opt);
        }

        data.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = item[textKey];
            selectEl.appendChild(opt);
        });
    }

    // ════════════════════════════════════════════════════════════
    // RENDERING
    // ════════════════════════════════════════════════════════════
    async function renderTable(items) {
        console.log('[Jobs] Rendering table with', items?.length || 0, 'items');

        if (!el.tbody) {
            console.error('[Jobs] tbody element not found!');
            return;
        }

        if (!items || !items.length) {
            console.log('[Jobs] No items, showing empty state');
            showEmpty();
            return;
        }

        const canEdit = state.permissions.canEdit !== false;
        const canDelete = state.permissions.canDelete !== false;

        el.tbody.innerHTML = items.map(job => {
            const statusBadge = job.is_active == 1 
                ? '<span class="badge badge-success">Active</span>' 
                : '<span class="badge badge-secondary">Inactive</span>';

            const featuredBadge = job.is_featured == 1 
                ? '<span class="badge badge-warning ml-1">Featured</span>' 
                : '';

            const remoteBadge = job.is_remote == 1 
                ? '<span class="badge badge-info ml-1">Remote</span>' 
                : '';

            return `
                <tr data-id="${job.id}">
                    <td>${esc(job.id)}</td>
                    <td>${esc(job.job_title || '')}</td>
                    <td>${esc(job.job_type || '')}</td>
                    <td>${esc(job.experience_level || '')}</td>
                    <td>${esc(job.location || '')}</td>
                    <td>${esc(job.category_name || '')}</td>
                    <td>${statusBadge}${featuredBadge}${remoteBadge}</td>
                    <td>
                        ${canEdit ? `<button type="button" class="btn btn-sm btn-primary" onclick="Jobs.edit(${job.id})">
                            <i class="fas fa-edit"></i>
                        </button>` : ''}
                        ${canDelete ? `<button type="button" class="btn btn-sm btn-danger ml-1" onclick="Jobs.remove(${job.id})">
                            <i class="fas fa-trash"></i>
                        </button>` : ''}
                    </td>
                </tr>
            `;
        }).join('');
    }

    function showEmpty() {
        if (!el.tbody) return;
        
        el.tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <div class="empty-state">
                        <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                        <h5>${t('table.empty.title', 'No jobs found')}</h5>
                        <p class="text-muted">${t('table.empty.message', 'Add your first job')}</p>
                        ${state.permissions.canCreate !== false ? `
                            <button type="button" class="btn btn-primary" onclick="Jobs.add()">
                                <i class="fas fa-plus"></i> ${t('table.empty.add_first', 'Add Job')}
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    }

    function showLoading() {
        if (!el.tbody) return;
        el.tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">${t('jobs.loading', 'Loading...')}</span>
                    </div>
                </td>
            </tr>
        `;
        
        if (el.tableContainer) el.tableContainer.classList.remove('d-none');
        if (el.errorContainer) el.errorContainer.classList.add('d-none');
    }

    function showTable() {
        if (el.tableContainer) el.tableContainer.classList.remove('d-none');
        if (el.errorContainer) el.errorContainer.classList.add('d-none');
    }

    function showError(message) {
        if (el.errorContainer) {
            el.errorContainer.classList.remove('d-none');
            if (el.errorMessage) {
                el.errorMessage.textContent = message;
            }
        }
        if (el.tableContainer) el.tableContainer.classList.add('d-none');
    }

    // ════════════════════════════════════════════════════════════
    // PAGINATION
    // ════════════════════════════════════════════════════════════
    function updatePagination(meta) {
        if (!el.pagination) return;

        const currentPage = meta.page || state.page;
        const perPage = meta.per_page || state.perPage;
        const total = meta.total || state.total;
        const totalPages = Math.ceil(total / perPage);

        if (totalPages <= 1) {
            el.pagination.innerHTML = '';
            return;
        }

        let html = '<ul class="pagination mb-0">';

        // Previous button
        html += `
            <li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="Jobs.load(${currentPage - 1}); return false;">
                    <i class="fas fa-chevron-left"></i>
                </a>
            </li>
        `;

        // Page numbers
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" onclick="Jobs.load(1); return false;">1</a></li>`;
            if (startPage > 2) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="Jobs.load(${i}); return false;">${i}</a>
                </li>
            `;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
            html += `<li class="page-item"><a class="page-link" href="#" onclick="Jobs.load(${totalPages}); return false;">${totalPages}</a></li>`;
        }

        // Next button
        html += `
            <li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="Jobs.load(${currentPage + 1}); return false;">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </li>
        `;

        html += '</ul>';
        el.pagination.innerHTML = html;
    }

    function updateResultsCount(total) {
        if (el.resultsCount) {
            el.resultsCount.textContent = total;
        }
        if (el.resultsCountText) {
            el.resultsCountText.textContent = t('pagination.showing', 'Showing') + ' ' + total + ' ' + t('jobs.title', 'jobs');
        }
    }

    // ════════════════════════════════════════════════════════════
    // FORM MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function showForm(job = null) {
        console.log('[Jobs] Showing form for:', job ? `job #${job.id}` : 'new job');

        state.currentJob = job;
        state.currentSkills = [];
        state.currentTranslations = [];

        if (el.formContainer) el.formContainer.classList.remove('d-none');
        if (el.tableContainer) el.tableContainer.classList.add('d-none');

        // Reset form
        if (el.form) el.form.reset();

        // Set form title
        if (el.formTitle) {
            el.formTitle.textContent = job ? t('form.edit_title', 'Edit Job') : t('form.add_title', 'Add Job');
        }

        // Populate form if editing
        if (job) {
            if (el.jobTitle) el.jobTitle.value = job.job_title || '';
            if (el.jobType) el.jobType.value = job.job_type || '';
            if (el.experienceLevel) el.experienceLevel.value = job.experience_level || '';
            if (el.jobLocation) el.jobLocation.value = job.location || '';
            if (el.salaryMin) el.salaryMin.value = job.salary_min || '';
            if (el.salaryMax) el.salaryMax.value = job.salary_max || '';
            if (el.jobCurrency) el.jobCurrency.value = job.currency || 'SAR';
            if (el.jobCategory) el.jobCategory.value = job.job_category_id || '';
            if (el.jobDescription) el.jobDescription.value = job.description || '';
            if (el.jobRequirements) el.jobRequirements.value = job.requirements || '';
            if (el.jobResponsibilities) el.jobResponsibilities.value = job.responsibilities || '';
            if (el.jobBenefits) el.jobBenefits.value = job.benefits || '';
            if (el.applicationDeadline) el.applicationDeadline.value = job.application_deadline || '';
            if (el.jobStatus) el.jobStatus.value = job.is_active || '1';
            if (el.isRemote) el.isRemote.value = job.is_remote || '0';
            if (el.isFeatured) el.isFeatured.value = job.is_featured || '0';

            // Load related data
            loadJobSkills(job.id);
            loadJobTranslations(job.id);
        } else {
            // Clear skills and translations
            if (el.jobSkillsList) el.jobSkillsList.innerHTML = '';
            if (el.jobTranslations) el.jobTranslations.innerHTML = '';
        }

        // Switch to general tab
        switchTab('general');
    }

    function hideForm() {
        if (el.formContainer) el.formContainer.classList.add('d-none');
        if (el.tableContainer) el.tableContainer.classList.remove('d-none');
        state.currentJob = null;
        state.currentSkills = [];
        state.currentTranslations = [];
    }

    // ════════════════════════════════════════════════════════════
    // CRUD OPERATIONS
    // ════════════════════════════════════════════════════════════
    async function saveJob(e) {
        if (e) e.preventDefault();

        try {
            console.log('[Jobs] Saving job...');

            // Collect form data
            const formData = {
                job_title: el.jobTitle?.value || '',
                job_type: el.jobType?.value || '',
                experience_level: el.experienceLevel?.value || '',
                location: el.jobLocation?.value || '',
                salary_min: el.salaryMin?.value || null,
                salary_max: el.salaryMax?.value || null,
                currency: el.jobCurrency?.value || 'SAR',
                job_category_id: el.jobCategory?.value || null,
                description: el.jobDescription?.value || '',
                requirements: el.jobRequirements?.value || '',
                responsibilities: el.jobResponsibilities?.value || '',
                benefits: el.jobBenefits?.value || '',
                application_deadline: el.applicationDeadline?.value || null,
                is_active: el.jobStatus?.value || '1',
                is_remote: el.isRemote?.value || '0',
                is_featured: el.isFeatured?.value || '0',
                tenant_id: state.tenantId
            };

            // Validate required fields
            if (!formData.job_title) {
                showNotification(t('form.fields.job_title.required', 'Job title is required'), 'error');
                return;
            }

            let result;
            if (state.currentJob) {
                // Update existing job
                formData.id = state.currentJob.id;
                result = await apiCall(API.jobs, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
            } else {
                // Create new job
                result = await apiCall(API.jobs, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
            }

            if (result.success) {
                const savedJobId = result.data?.id || state.currentJob?.id;

                // Save skills
                if (savedJobId && state.currentSkills.length > 0) {
                    await saveJobSkills(savedJobId, state.currentSkills);
                }

                // Save translations
                const translations = collectTranslations();
                if (savedJobId && Object.keys(translations).length > 0) {
                    await saveJobTranslations(savedJobId, translations);
                }

                showNotification(
                    state.currentJob 
                        ? t('strings.update_success', 'Job updated successfully') 
                        : t('strings.save_success', 'Job saved successfully'), 
                    'success'
                );
                hideForm();
                loadJobs(state.page);
            } else {
                throw new Error(result.error || result.message || 'Failed to save job');
            }
        } catch (err) {
            console.error('[Jobs] Save failed:', err);
            showNotification(err.message || 'Failed to save job', 'error');
        }
    }

    async function deleteJob(id) {
        if (!confirm(t('strings.delete_confirm', 'Are you sure you want to delete this job?'))) {
            return;
        }

        try {
            console.log('[Jobs] Deleting job:', id);

            const result = await apiCall(`${API.jobs}?id=${id}`, {
                method: 'DELETE'
            });

            if (result.success) {
                showNotification(t('strings.delete_success', 'Job deleted successfully'), 'success');
                loadJobs(state.page);
            } else {
                throw new Error(result.error || result.message || 'Failed to delete job');
            }
        } catch (err) {
            console.error('[Jobs] Delete failed:', err);
            showNotification(err.message || 'Failed to delete job', 'error');
        }
    }

    // ════════════════════════════════════════════════════════════
    // SKILLS MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function addSkill() {
        const skill = {
            skill_name: '',
            proficiency_level: 'intermediate',
            is_required: 0
        };

        state.currentSkills.push(skill);
        renderSkills();
    }

    function renderSkills() {
        if (!el.jobSkillsList) return;

        el.jobSkillsList.innerHTML = state.currentSkills.map((skill, idx) => `
            <div class="skill-item card mb-2" data-index="${idx}">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>${t('form.skills.skill_name', 'Skill Name')}</label>
                                <input type="text" class="form-control" value="${esc(skill.skill_name || '')}" 
                                       onchange="Jobs.updateSkillField(${idx}, 'skill_name', this.value)">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>${t('form.skills.proficiency_level', 'Proficiency Level')}</label>
                                <select class="form-control" onchange="Jobs.updateSkillField(${idx}, 'proficiency_level', this.value)">
                                    <option value="beginner" ${skill.proficiency_level === 'beginner' ? 'selected' : ''}>Beginner</option>
                                    <option value="intermediate" ${skill.proficiency_level === 'intermediate' ? 'selected' : ''}>Intermediate</option>
                                    <option value="advanced" ${skill.proficiency_level === 'advanced' ? 'selected' : ''}>Advanced</option>
                                    <option value="expert" ${skill.proficiency_level === 'expert' ? 'selected' : ''}>Expert</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>${t('form.skills.is_required', 'Required')}</label>
                                <select class="form-control" onchange="Jobs.updateSkillField(${idx}, 'is_required', this.value)">
                                    <option value="0" ${skill.is_required == 0 ? 'selected' : ''}>No</option>
                                    <option value="1" ${skill.is_required == 1 ? 'selected' : ''}>Yes</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-danger btn-block" onclick="Jobs.removeSkill(${idx})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function updateSkillField(index, field, value) {
        if (state.currentSkills[index]) {
            state.currentSkills[index][field] = value;
        }
    }

    function removeSkill(index) {
        state.currentSkills.splice(index, 1);
        renderSkills();
    }

    async function loadJobSkills(jobId) {
        try {
            console.log('[Jobs] Loading skills for job:', jobId);
            const result = await apiCall(`${API.skills}?job_id=${jobId}&format=json`);
            if (result.success) {
                const items = Array.isArray(result.data) ? result.data : (result.data?.items || []);
                state.currentSkills = items.map(s => ({
                    id: s.id,
                    skill_name: s.skill_name || '',
                    proficiency_level: s.proficiency_level || 'intermediate',
                    is_required: s.is_required || 0
                }));
                renderSkills();
            }
        } catch (err) {
            console.warn('[Jobs] Failed to load skills:', err);
        }
    }

    async function saveJobSkills(jobId, skills) {
        try {
            console.log('[Jobs] Saving skills for job:', jobId);

            // Delete existing skills
            try {
                await apiCall(`${API.skills}?job_id=${jobId}`, { method: 'DELETE' });
            } catch (e) {
                console.warn('[Jobs] Failed to delete existing skills:', e);
            }

            // Create new skills
            for (const skill of skills) {
                if (!skill.skill_name) continue;

                const skillData = {
                    job_id: jobId,
                    skill_name: skill.skill_name,
                    proficiency_level: skill.proficiency_level || 'intermediate',
                    is_required: skill.is_required || 0
                };

                await apiCall(API.skills, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(skillData)
                });
            }

            console.log('[Jobs] Skills saved successfully');
        } catch (err) {
            console.warn('[Jobs] Failed to save skills:', err);
        }
    }

    // ════════════════════════════════════════════════════════════
    // TRANSLATIONS MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function addTranslation() {
        if (!el.jobLangSelect || !el.jobLangSelect.value) {
            showNotification('Please select a language', 'warning');
            return;
        }

        const langCode = el.jobLangSelect.value;
        const langName = el.jobLangSelect.options[el.jobLangSelect.selectedIndex].text;

        // Check if translation already exists
        const existing = el.jobTranslations?.querySelector(`[data-lang="${langCode}"]`);
        if (existing) {
            showNotification('Translation for this language already exists', 'warning');
            return;
        }

        const panel = createTranslationPanel(langCode, langName, {
            job_title: '',
            description: '',
            requirements: '',
            responsibilities: '',
            benefits: ''
        });

        if (el.jobTranslations) {
            el.jobTranslations.appendChild(panel);
        }

        el.jobLangSelect.value = '';
    }

    function createTranslationPanel(langCode, langName, data) {
        const panel = document.createElement('div');
        panel.className = 'card mb-3 translation-panel';
        panel.dataset.lang = langCode;

        panel.innerHTML = `
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>${esc(langName)} (${esc(langCode)})</strong>
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.translation-panel').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>${t('form.translations.job_title', 'Job Title')}</label>
                    <input type="text" class="form-control trans-job-title" value="${esc(data.job_title || '')}" data-lang="${langCode}">
                </div>
                <div class="form-group">
                    <label>${t('form.translations.description', 'Description')}</label>
                    <textarea class="form-control trans-description" rows="3" data-lang="${langCode}">${esc(data.description || '')}</textarea>
                </div>
                <div class="form-group">
                    <label>${t('form.translations.requirements', 'Requirements')}</label>
                    <textarea class="form-control trans-requirements" rows="3" data-lang="${langCode}">${esc(data.requirements || '')}</textarea>
                </div>
                <div class="form-group">
                    <label>${t('form.translations.responsibilities', 'Responsibilities')}</label>
                    <textarea class="form-control trans-responsibilities" rows="3" data-lang="${langCode}">${esc(data.responsibilities || '')}</textarea>
                </div>
                <div class="form-group">
                    <label>${t('form.translations.benefits', 'Benefits')}</label>
                    <textarea class="form-control trans-benefits" rows="3" data-lang="${langCode}">${esc(data.benefits || '')}</textarea>
                </div>
            </div>
        `;

        return panel;
    }

    function collectTranslations() {
        const translations = {};

        document.querySelectorAll('.translation-panel').forEach(panel => {
            const lang = panel.dataset.lang;
            const jobTitle = panel.querySelector('.trans-job-title')?.value || '';
            const description = panel.querySelector('.trans-description')?.value || '';
            const requirements = panel.querySelector('.trans-requirements')?.value || '';
            const responsibilities = panel.querySelector('.trans-responsibilities')?.value || '';
            const benefits = panel.querySelector('.trans-benefits')?.value || '';

            if (jobTitle || description || requirements || responsibilities || benefits) {
                translations[lang] = {
                    job_title: jobTitle,
                    description: description,
                    requirements: requirements,
                    responsibilities: responsibilities,
                    benefits: benefits
                };
            }
        });

        return translations;
    }

    async function loadJobTranslations(jobId) {
        try {
            console.log('[Jobs] Loading translations for job:', jobId);
            const result = await apiCall(`/api/job_translations?job_id=${jobId}&format=json`);
            if (result.success) {
                const items = Array.isArray(result.data) ? result.data : (result.data?.items || []);
                if (el.jobTranslations) el.jobTranslations.innerHTML = '';
                items.forEach(trans => {
                    const langName = state.languages.find(l => l.code === trans.language_code)?.name || trans.language_code;
                    const panel = createTranslationPanel(trans.language_code, langName, {
                        job_title: trans.job_title || '',
                        description: trans.description || '',
                        requirements: trans.requirements || '',
                        responsibilities: trans.responsibilities || '',
                        benefits: trans.benefits || ''
                    });
                    if (el.jobTranslations) el.jobTranslations.appendChild(panel);
                });
            }
        } catch (err) {
            console.warn('[Jobs] Failed to load translations:', err);
        }
    }

    async function saveJobTranslations(jobId, translations) {
        try {
            console.log('[Jobs] Saving translations for job:', jobId);

            for (const [langCode, trans] of Object.entries(translations)) {
                const translationData = {
                    job_id: jobId,
                    language_code: langCode,
                    job_title: trans.job_title || '',
                    description: trans.description || '',
                    requirements: trans.requirements || '',
                    responsibilities: trans.responsibilities || '',
                    benefits: trans.benefits || ''
                };

                // Try to update first, if not exists then create
                try {
                    await apiCall('/api/job_translations', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(translationData)
                    });
                } catch (e) {
                    // If update fails, try create
                    await apiCall('/api/job_translations', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(translationData)
                    });
                }
            }

            console.log('[Jobs] Translations saved successfully');
        } catch (err) {
            console.warn('[Jobs] Failed to save translations:', err);
        }
    }

    // ════════════════════════════════════════════════════════════
    // FILTERS
    // ════════════════════════════════════════════════════════════
    function applyFilters() {
        state.filters = {
            search: el.searchInput?.value || '',
            status: el.statusFilter?.value || '',
            job_type: el.jobTypeFilter?.value || '',
            experience_level: el.experienceLevelFilter?.value || '',
            category_id: el.categoryFilter?.value || ''
        };

        console.log('[Jobs] Applying filters:', state.filters);
        loadJobs(1);
    }

    function resetFilters() {
        state.filters = {};

        if (el.searchInput) el.searchInput.value = '';
        if (el.statusFilter) el.statusFilter.value = '';
        if (el.jobTypeFilter) el.jobTypeFilter.value = '';
        if (el.experienceLevelFilter) el.experienceLevelFilter.value = '';
        if (el.categoryFilter) el.categoryFilter.value = '';

        loadJobs(1);
    }

    // ════════════════════════════════════════════════════════════
    // TAB MANAGEMENT
    // ════════════════════════════════════════════════════════════
    function initTabs() {
        const tabButtons = document.querySelectorAll('.job-tab-btn');
        tabButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.dataset.tab;
                switchTab(tabName);
            });
        });
    }

    function switchTab(tabName) {
        // Update buttons
        document.querySelectorAll('.job-tab-btn').forEach(btn => {
            if (btn.dataset.tab === tabName) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // Update panels
        document.querySelectorAll('.job-tab-pane').forEach(pane => {
            if (pane.dataset.tab === tabName) {
                pane.classList.add('active');
                pane.classList.remove('d-none');
            } else {
                pane.classList.remove('active');
                pane.classList.add('d-none');
            }
        });
    }

    // ════════════════════════════════════════════════════════════
    // UTILITIES
    // ════════════════════════════════════════════════════════════
    function esc(str) {
        if (str == null) return '';
        const div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function showNotification(message, type = 'info') {
        console.log(`[Jobs] Notification (${type}):`, message);

        // If AdminFramework has notification system, use it
        if (AF && AF.notify) {
            AF.notify(message, type);
            return;
        }

        // Fallback to alert
        if (type === 'error') {
            alert('Error: ' + message);
        } else if (type === 'success') {
            alert(message);
        } else if (type === 'warning') {
            alert('Warning: ' + message);
        } else {
            alert(message);
        }
    }

    // ════════════════════════════════════════════════════════════
    // INITIALIZATION
    // ════════════════════════════════════════════════════════════
    async function init() {
        console.log('[Jobs] Initializing...');

        const $id = (id) => document.getElementById(id);

        // Cache DOM elements
        el = {
            // Containers
            formContainer: $id('jobFormContainer'),
            tableContainer: $id('jobTableContainer'),
            errorContainer: $id('jobErrorContainer'),
            errorMessage: $id('jobErrorMessage'),
            form: $id('jobForm'),
            formTitle: $id('jobFormTitle'),

            // Form fields - General
            jobTitle: $id('jobTitle'),
            jobType: $id('jobType'),
            experienceLevel: $id('experienceLevel'),
            jobLocation: $id('jobLocation'),
            salaryMin: $id('salaryMin'),
            salaryMax: $id('salaryMax'),
            jobCurrency: $id('jobCurrency'),
            jobCategory: $id('jobCategory'),
            jobDescription: $id('jobDescription'),
            jobRequirements: $id('jobRequirements'),
            jobResponsibilities: $id('jobResponsibilities'),
            jobBenefits: $id('jobBenefits'),
            applicationDeadline: $id('applicationDeadline'),
            jobStatus: $id('jobStatus'),
            isRemote: $id('isRemote'),
            isFeatured: $id('isFeatured'),

            // Skills
            btnAddSkill: $id('btnAddSkill'),
            jobSkillsList: $id('jobSkillsList'),

            // Translations
            jobTranslations: $id('jobTranslations'),
            jobLangSelect: $id('jobLangSelect'),
            jobAddLangBtn: $id('jobAddLangBtn'),

            // Table
            tbody: $id('tableBody'),

            // Filters
            searchInput: $id('searchInput'),
            statusFilter: $id('statusFilter'),
            jobTypeFilter: $id('jobTypeFilter'),
            experienceLevelFilter: $id('experienceLevelFilter'),
            categoryFilter: $id('categoryFilter'),

            // Buttons
            btnSubmit: $id('btnSubmitForm'),
            btnAdd: $id('btnAddJob'),
            btnClose: $id('btnCloseForm'),
            btnCancel: $id('btnCancelForm'),
            btnApply: $id('btnApplyFilters'),
            btnReset: $id('btnResetFilters'),
            btnRetry: $id('btnRetry'),

            // Pagination
            pagination: $id('pagination'),
            paginationInfo: $id('paginationInfo'),
            resultsCount: $id('resultsCount'),
            resultsCountText: $id('resultsCountText')
        };

        console.log('[Jobs] DOM elements found:', {
            form: !!el.form,
            formContainer: !!el.formContainer,
            btnAdd: !!el.btnAdd,
            btnSubmit: !!el.btnSubmit,
            tbody: !!el.tbody
        });

        // Load translations
        await loadTranslations(state.language);

        // Setup event listeners
        if (el.form) {
            el.form.onsubmit = saveJob;
            console.log('[Jobs] ✓ Form submit handler attached');
        } else {
            console.error('[Jobs] ✗ Form element not found!');
        }

        if (el.btnAdd) {
            el.btnAdd.onclick = function () { showForm(); };
            console.log('[Jobs] ✓ Add button handler attached');
        } else {
            console.error('[Jobs] ✗ Add button not found!');
        }

        if (el.btnClose) el.btnClose.onclick = hideForm;
        if (el.btnCancel) el.btnCancel.onclick = hideForm;
        if (el.btnApply) el.btnApply.onclick = applyFilters;
        if (el.btnReset) el.btnReset.onclick = resetFilters;
        if (el.btnRetry) el.btnRetry.onclick = function () { loadJobs(state.page); };

        // Skills
        if (el.btnAddSkill) el.btnAddSkill.onclick = addSkill;

        // Translations
        if (el.jobAddLangBtn) el.jobAddLangBtn.onclick = addTranslation;

        // Initialize tabs
        initTabs();

        // Load dropdown data
        await loadDropdownData();

        // Load initial data
        await loadJobs(1);

        console.log('[Jobs] ✓ Initialized successfully');
    }

    // ════════════════════════════════════════════════════════════
    // PUBLIC API
    // ════════════════════════════════════════════════════════════
    window.Jobs = {
        init,
        load: loadJobs,
        add: () => showForm(),
        edit: async (id) => {
            try {
                const result = await apiCall(`${API.jobs}?id=${id}&format=json&lang=${state.language}&tenant_id=${state.tenantId}`);
                if (result.success && result.data) {
                    const job = Array.isArray(result.data) ? result.data[0] : result.data;
                    showForm(job);
                } else {
                    throw new Error('Job not found');
                }
            } catch (err) {
                console.error('[Jobs] Edit failed:', err);
                showNotification(err.message || t('messages.error.load_failed', 'Failed to load job'), 'error');
            }
        },
        remove: deleteJob,
        updateSkillField,
        removeSkill,
        setLanguage: async (lang) => {
            state.language = lang;
            await loadTranslations(lang);
            setDirectionForLang(lang);
            loadJobs(state.page);
        }
    };

    // Fragment support
    window.page = { run: init };

    // Auto-initialize on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            if (window.AdminFramework && !window.page.__fragment_init) {
                init().catch(function (e) { console.error('[Jobs] Auto-init failed:', e); });
            }
        });
    } else {
        if (window.AdminFramework && !window.page.__fragment_init) {
            init().catch(function (e) { console.error('[Jobs] Auto-init failed:', e); });
        }
    }
    window.page.__fragment_init = false;

    console.log('[Jobs] Module loaded');

})();
