<?php
declare(strict_types=1);

/**
 * /admin/fragments/jobs.php
 * Complete Jobs Management System
 * 
 * ✅ Multi-language support for job translations
 * ✅ Job categories and skills management
 * ✅ Comprehensive job form with all schema fields
 * ✅ Status workflow (draft/published/closed/filled/cancelled)
 * ✅ Advanced filtering and search
 */

// ════════════════════════════════════════════════════════════
// DETECT REQUEST TYPE
// ════════════════════════════════════════════════════════════
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isEmbedded = isset($_GET['embedded']) || isset($_POST['embedded']);
$isFragment = $isAjax || $isEmbedded;

// ════════════════════════════════════════════════════════════
// LOAD CONTEXT / HEADER
// ════════════════════════════════════════════════════════════
if ($isFragment) {
    require_once __DIR__ . '/../includes/admin_context.php';
} else {
    require_once __DIR__ . '/../includes/header.php';
}

// ════════════════════════════════════════════════════════════
// VERIFY USER IS LOGGED IN
// ════════════════════════════════════════════════════════════
if (!is_admin_logged_in()) {
    if ($isFragment) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    } else {
        header('Location: /admin/login.php');
        exit;
    }
}

// ════════════════════════════════════════════════════════════
// GET USER CONTEXT & PERMISSIONS
// ════════════════════════════════════════════════════════════
$user = admin_user();
$lang = admin_lang();
$dir = in_array($lang, ['ar', 'he', 'fa', 'ur']) ? 'rtl' : 'ltr';
$csrf = admin_csrf();
$tenantId = admin_tenant_id();
$userId = admin_user_id();

// ════════════════════════════════════════════════════════════
// CHECK PERMISSIONS
// ════════════════════════════════════════════════════════════
$canManageJobs = can('jobs.manage') || can('jobs.create');
$canViewAll = can_view_all('jobs');
$canViewOwn = can_view_own('jobs');
$canViewTenant = can_view_tenant('jobs');
$canCreate = can_create('jobs');
$canEditAll = can_edit_all('jobs');
$canEditOwn = can_edit_own('jobs');
$canDeleteAll = can_delete_all('jobs');
$canDeleteOwn = can_delete_own('jobs');

$canView = $canViewAll || $canViewOwn || $canViewTenant;
$canEdit = $canEditAll || $canEditOwn || $canManageJobs;
$canDelete = $canDeleteAll || $canDeleteOwn || $canManageJobs;

if (!$canView && !is_super_admin()) {
    if ($isFragment) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    } else {
        http_response_code(403);
        die('Access denied: You do not have permission to view jobs');
    }
}

// ════════════════════════════════════════════════════════════
// TRANSLATION HELPERS
// ════════════════════════════════════════════════════════════
function __t($key, $fallback = '') {
    if (function_exists('i18n_get')) {
        $v = i18n_get($key);
        return $v ?? ($fallback ?? $key);
    }
    return $fallback ?? $key;
}

function __tr($key, $replacements = []) {
    $text = __t($key, $key);
    foreach ($replacements as $ph => $val) {
        $text = str_replace("{" . $ph . "}", (string)$val, $text);
    }
    return $text;
}

// ════════════════════════════════════════════════════════════
// API BASE
// ════════════════════════════════════════════════════════════
$apiBase = '/api';

?>
<!-- Force load CSS if embedded -->
<?php if ($isFragment): ?>
<link rel="stylesheet" href="/admin/assets/css/pages/jobs.css?v=<?= time() ?>">
<?php endif; ?>

<!-- Page Meta -->
<meta data-page="jobs"
      data-i18n-files="/admin/languages/Jobs/<?= rawurlencode($lang) ?>.json">

<!-- Page Container -->
<div class="page-container" id="jobsPageContainer" dir="<?= htmlspecialchars($dir) ?>">

    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <h1 class="page-title" data-i18n="jobs.title"><?= __t('jobs.title', 'Jobs') ?></h1>
            <p class="page-subtitle" data-i18n="jobs.subtitle"><?= __t('jobs.subtitle', 'Manage job postings and applications') ?></p>
        </div>
        <div class="page-header-actions">
            <?php if ($canCreate): ?>
            <button id="btnAddJob" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                <span data-i18n="jobs.add_new"><?= __t('jobs.add_new', 'Add Job') ?></span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Error Container -->
    <div id="errorContainer" class="alert alert-error" style="display:none; margin-bottom:16px;">
        <i class="fas fa-exclamation-circle"></i>
        <span id="errorMessage"></span>
    </div>

    <!-- Form Container -->
    <div id="jobFormContainer" class="card form-card" style="display:none">
        <div class="card-header">
            <h3 class="card-title" id="formTitle" data-i18n="form.add_title"><?= __t('form.add_title', 'Add Job') ?></h3>
            <button type="button" class="btn btn-sm btn-outline" id="btnCloseForm" aria-label="<?= __t('accessibility.close', 'Close') ?>">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form id="jobForm" novalidate>
                <!-- Hidden Fields -->
                <input type="hidden" id="formId" name="id">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" id="jobEntityId" name="entity_id">
                <input type="hidden" id="jobCreatedBy" name="created_by" value="<?= $userId ?>">
                <input type="hidden" id="jobTranslationsData" name="translations_data">
                <input type="hidden" id="jobSkillsData" name="skills_data">

                <!-- Tabs Navigation -->
                <div class="form-tabs">
                    <button type="button" class="tab-btn active" data-tab="basic">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="tabs.basic"><?= __t('tabs.basic', 'Basic Info') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="details">
                        <i class="fas fa-file-alt"></i>
                        <span data-i18n="tabs.details"><?= __t('tabs.details', 'Job Details') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="salary">
                        <i class="fas fa-dollar-sign"></i>
                        <span data-i18n="tabs.salary"><?= __t('tabs.salary', 'Salary') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="location">
                        <i class="fas fa-map-marker-alt"></i>
                        <span data-i18n="tabs.location"><?= __t('tabs.location', 'Location') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="application">
                        <i class="fas fa-file-signature"></i>
                        <span data-i18n="tabs.application"><?= __t('tabs.application', 'Application') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="skills">
                        <i class="fas fa-tools"></i>
                        <span data-i18n="tabs.skills"><?= __t('tabs.skills', 'Skills') ?></span>
                    </button>
                    <button type="button" class="tab-btn" data-tab="translations">
                        <i class="fas fa-language"></i>
                        <span data-i18n="tabs.translations"><?= __t('tabs.translations', 'Translations') ?></span>
                    </button>
                </div>

                <!-- Tab: Basic Info -->
                <div class="tab-content active" id="tab-basic">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="jobTitle" class="required" data-i18n="form.fields.job_title.label">
                                <?= __t('form.fields.job_title.label', 'Job Title') ?>
                            </label>
                            <input type="text" id="jobTitle" name="job_title" class="form-control" required
                                   data-i18n-placeholder="form.fields.job_title.placeholder"
                                   placeholder="<?= __t('form.fields.job_title.placeholder', 'Enter job title') ?>">
                            <div class="invalid-feedback" data-i18n="form.fields.job_title.required">
                                <?= __t('form.fields.job_title.required', 'Job title is required') ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="jobSlug" data-i18n="form.fields.slug.label">
                                <?= __t('form.fields.slug.label', 'Slug') ?>
                            </label>
                            <input type="text" id="jobSlug" name="slug" class="form-control"
                                   data-i18n-placeholder="form.fields.slug.placeholder"
                                   placeholder="<?= __t('form.fields.slug.placeholder', 'job-slug') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="jobType" class="required" data-i18n="form.fields.job_type.label">
                                <?= __t('form.fields.job_type.label', 'Job Type') ?>
                            </label>
                            <select id="jobType" name="job_type" class="form-control" required>
                                <option value="" data-i18n="form.fields.job_type.select">Select Job Type</option>
                                <option value="full-time" data-i18n="form.fields.job_type.full_time">Full-Time</option>
                                <option value="part-time" data-i18n="form.fields.job_type.part_time">Part-Time</option>
                                <option value="contract" data-i18n="form.fields.job_type.contract">Contract</option>
                                <option value="temporary" data-i18n="form.fields.job_type.temporary">Temporary</option>
                                <option value="internship" data-i18n="form.fields.job_type.internship">Internship</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="employmentType" data-i18n="form.fields.employment_type.label">
                                <?= __t('form.fields.employment_type.label', 'Employment Type') ?>
                            </label>
                            <select id="employmentType" name="employment_type" class="form-control">
                                <option value="employee" data-i18n="form.fields.employment_type.employee">Employee</option>
                                <option value="freelance" data-i18n="form.fields.employment_type.freelance">Freelance</option>
                                <option value="volunteer" data-i18n="form.fields.employment_type.volunteer">Volunteer</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="experienceLevel" data-i18n="form.fields.experience_level.label">
                                <?= __t('form.fields.experience_level.label', 'Experience Level') ?>
                            </label>
                            <select id="experienceLevel" name="experience_level" class="form-control">
                                <option value="entry" data-i18n="form.fields.experience_level.entry">Entry Level</option>
                                <option value="mid" data-i18n="form.fields.experience_level.mid">Mid Level</option>
                                <option value="senior" data-i18n="form.fields.experience_level.senior">Senior</option>
                                <option value="lead" data-i18n="form.fields.experience_level.lead">Lead</option>
                                <option value="executive" data-i18n="form.fields.experience_level.executive">Executive</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="jobCategory" data-i18n="form.fields.category.label">
                                <?= __t('form.fields.category.label', 'Category') ?>
                            </label>
                            <select id="jobCategory" name="category" class="form-control">
                                <option value="">Select Category</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="jobDepartment" data-i18n="form.fields.department.label">
                                <?= __t('form.fields.department.label', 'Department') ?>
                            </label>
                            <input type="text" id="jobDepartment" name="department" class="form-control"
                                   data-i18n-placeholder="form.fields.department.placeholder"
                                   placeholder="<?= __t('form.fields.department.placeholder', 'Enter department') ?>">
                        </div>

                        <div class="form-group">
                            <label for="positionsAvailable" data-i18n="form.fields.positions_available.label">
                                <?= __t('form.fields.positions_available.label', 'Positions Available') ?>
                            </label>
                            <input type="number" id="positionsAvailable" name="positions_available" class="form-control" min="1" value="1">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="jobStatus" data-i18n="form.fields.status.label">
                                <?= __t('form.fields.status.label', 'Status') ?>
                            </label>
                            <select id="jobStatus" name="status" class="form-control">
                                <option value="draft" data-i18n="form.fields.status.draft">Draft</option>
                                <option value="published" data-i18n="form.fields.status.published">Published</option>
                                <option value="closed" data-i18n="form.fields.status.closed">Closed</option>
                                <option value="filled" data-i18n="form.fields.status.filled">Filled</option>
                                <option value="cancelled" data-i18n="form.fields.status.cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="startDate" data-i18n="form.fields.start_date.label">
                                <?= __t('form.fields.start_date.label', 'Start Date') ?>
                            </label>
                            <input type="date" id="startDate" name="start_date" class="form-control">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" id="isFeatured" name="is_featured" value="1">
                                    <span data-i18n="form.fields.is_featured.label"><?= __t('form.fields.is_featured.label', 'Featured Job') ?></span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" id="isUrgent" name="is_urgent" value="1">
                                    <span data-i18n="form.fields.is_urgent.label"><?= __t('form.fields.is_urgent.label', 'Urgent Hiring') ?></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Job Details -->
                <div class="tab-content" id="tab-details" style="display:none">
                    <p class="tab-description" data-i18n="tabs.details_description">
                        <?= __t('tabs.details_description', 'Job description will be managed in translations tab') ?>
                    </p>
                </div>

                <!-- Tab: Salary -->
                <div class="tab-content" id="tab-salary" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="salaryMin" data-i18n="form.fields.salary_min.label">
                                <?= __t('form.fields.salary_min.label', 'Minimum Salary') ?>
                            </label>
                            <input type="number" id="salaryMin" name="salary_min" class="form-control" min="0" step="0.01">
                        </div>

                        <div class="form-group">
                            <label for="salaryMax" data-i18n="form.fields.salary_max.label">
                                <?= __t('form.fields.salary_max.label', 'Maximum Salary') ?>
                            </label>
                            <input type="number" id="salaryMax" name="salary_max" class="form-control" min="0" step="0.01">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="salaryCurrency" data-i18n="form.fields.salary_currency.label">
                                <?= __t('form.fields.salary_currency.label', 'Currency') ?>
                            </label>
                            <select id="salaryCurrency" name="salary_currency" class="form-control">
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="GBP">GBP</option>
                                <option value="SAR">SAR</option>
                                <option value="AED">AED</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="salaryPeriod" data-i18n="form.fields.salary_period.label">
                                <?= __t('form.fields.salary_period.label', 'Salary Period') ?>
                            </label>
                            <select id="salaryPeriod" name="salary_period" class="form-control">
                                <option value="hour" data-i18n="form.fields.salary_period.hour">Per Hour</option>
                                <option value="day" data-i18n="form.fields.salary_period.day">Per Day</option>
                                <option value="week" data-i18n="form.fields.salary_period.week">Per Week</option>
                                <option value="month" data-i18n="form.fields.salary_period.month">Per Month</option>
                                <option value="year" data-i18n="form.fields.salary_period.year">Per Year</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" id="salaryNegotiable" name="salary_negotiable" value="1">
                                    <span data-i18n="form.fields.salary_negotiable.label"><?= __t('form.fields.salary_negotiable.label', 'Salary Negotiable') ?></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Location -->
                <div class="tab-content" id="tab-location" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="countryId" data-i18n="form.fields.country_id.label">
                                <?= __t('form.fields.country_id.label', 'Country') ?>
                            </label>
                            <input type="number" id="countryId" name="country_id" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="cityId" data-i18n="form.fields.city_id.label">
                                <?= __t('form.fields.city_id.label', 'City') ?>
                            </label>
                            <input type="number" id="cityId" name="city_id" class="form-control">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="workLocation" data-i18n="form.fields.work_location.label">
                                <?= __t('form.fields.work_location.label', 'Work Location') ?>
                            </label>
                            <select id="workLocation" name="work_location" class="form-control">
                                <option value="onsite" data-i18n="form.fields.work_location.onsite">On-site</option>
                                <option value="remote" data-i18n="form.fields.work_location.remote">Remote</option>
                                <option value="hybrid" data-i18n="form.fields.work_location.hybrid">Hybrid</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" id="isRemote" name="is_remote" value="1">
                                    <span data-i18n="form.fields.is_remote.label"><?= __t('form.fields.is_remote.label', 'Remote Work Available') ?></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Application -->
                <div class="tab-content" id="tab-application" style="display:none">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="applicationFormType" data-i18n="form.fields.application_form_type.label">
                                <?= __t('form.fields.application_form_type.label', 'Application Form Type') ?>
                            </label>
                            <select id="applicationFormType" name="application_form_type" class="form-control">
                                <option value="internal" data-i18n="form.fields.application_form_type.internal">Internal Form</option>
                                <option value="external" data-i18n="form.fields.application_form_type.external">External URL</option>
                                <option value="email" data-i18n="form.fields.application_form_type.email">Email</option>
                            </select>
                        </div>

                        <div class="form-group" id="externalUrlGroup" style="display:none">
                            <label for="externalApplicationUrl" data-i18n="form.fields.external_application_url.label">
                                <?= __t('form.fields.external_application_url.label', 'External Application URL') ?>
                            </label>
                            <input type="url" id="externalApplicationUrl" name="external_application_url" class="form-control"
                                   data-i18n-placeholder="form.fields.external_application_url.placeholder"
                                   placeholder="<?= __t('form.fields.external_application_url.placeholder', 'https://example.com/apply') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="applicationDeadline" data-i18n="form.fields.application_deadline.label">
                                <?= __t('form.fields.application_deadline.label', 'Application Deadline') ?>
                            </label>
                            <input type="datetime-local" id="applicationDeadline" name="application_deadline" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Tab: Skills -->
                <div class="tab-content" id="tab-skills" style="display:none">
                    <div class="skills-header">
                        <h4 data-i18n="skills.title"><?= __t('skills.title', 'Required Skills') ?></h4>
                        <button type="button" id="btnAddSkill" class="btn btn-sm btn-secondary">
                            <i class="fas fa-plus"></i>
                            <span data-i18n="skills.add"><?= __t('skills.add', 'Add Skill') ?></span>
                        </button>
                    </div>

                    <div id="skillsList" class="skills-list"></div>

                    <!-- Add Skill Form -->
                    <div id="addSkillForm" class="add-skill-form" style="display:none">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="skillName" data-i18n="skills.form.skill_name">Skill Name</label>
                                <input type="text" id="skillName" class="form-control" placeholder="e.g., JavaScript">
                            </div>
                            <div class="form-group">
                                <label for="skillProficiency" data-i18n="skills.form.proficiency">Proficiency Level</label>
                                <select id="skillProficiency" class="form-control">
                                    <option value="beginner">Beginner</option>
                                    <option value="intermediate">Intermediate</option>
                                    <option value="advanced">Advanced</option>
                                    <option value="expert">Expert</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="skillRequired">
                                    <span data-i18n="skills.form.required">Required</span>
                                </label>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="button" id="btnSaveSkill" class="btn btn-sm btn-primary">
                                <span data-i18n="skills.form.save">Save Skill</span>
                            </button>
                            <button type="button" id="btnCancelSkill" class="btn btn-sm btn-outline">
                                <span data-i18n="skills.form.cancel">Cancel</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab: Translations -->
                <div class="tab-content" id="tab-translations" style="display:none">
                    <div class="translations-header">
                        <h4 data-i18n="translations.title"><?= __t('translations.title', 'Translations') ?></h4>
                        <button type="button" id="btnAddTranslation" class="btn btn-sm btn-secondary">
                            <i class="fas fa-plus"></i>
                            <span data-i18n="translations.add"><?= __t('translations.add', 'Add Translation') ?></span>
                        </button>
                    </div>

                    <div id="translationsList" class="translations-list"></div>

                    <!-- Add Translation Form -->
                    <div id="addTranslationForm" class="add-translation-form" style="display:none">
                        <div class="form-group">
                            <label for="translationLanguage" data-i18n="translations.form.language">Language</label>
                            <select id="translationLanguage" class="form-control">
                                <option value="">Select Language</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="translationJobTitle" data-i18n="translations.form.job_title">Job Title</label>
                            <input type="text" id="translationJobTitle" class="form-control">
                        </div>

                        <div class="form-group">
                            <label for="translationDescription" data-i18n="translations.form.description">Description</label>
                            <textarea id="translationDescription" class="form-control" rows="4"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="translationRequirements" data-i18n="translations.form.requirements">Requirements</label>
                            <textarea id="translationRequirements" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="translationResponsibilities" data-i18n="translations.form.responsibilities">Responsibilities</label>
                            <textarea id="translationResponsibilities" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-group">
                            <label for="translationBenefits" data-i18n="translations.form.benefits">Benefits</label>
                            <textarea id="translationBenefits" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="button" id="btnSaveTranslation" class="btn btn-sm btn-primary">
                                <span data-i18n="translations.form.save">Save Translation</span>
                            </button>
                            <button type="button" id="btnCancelTranslation" class="btn btn-sm btn-outline">
                                <span data-i18n="translations.form.cancel">Cancel</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions-footer">
                    <?php if ($canEdit || $canCreate): ?>
                    <button type="submit" id="btnSubmit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        <span data-i18n="form.save"><?= __t('form.save', 'Save Job') ?></span>
                    </button>
                    <button type="button" id="btnSaveAndPublish" class="btn btn-success" style="display:none">
                        <i class="fas fa-check"></i>
                        <span data-i18n="form.save_and_publish"><?= __t('form.save_and_publish', 'Save & Publish') ?></span>
                    </button>
                    <button type="button" class="btn btn-outline" id="btnCancelForm">
                        <span data-i18n="form.cancel"><?= __t('form.cancel', 'Cancel') ?></span>
                    </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <div class="card filter-card">
        <div class="card-body">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="searchInput" data-i18n="filters.search">
                        <?= __t('filters.search', 'Search') ?>
                    </label>
                    <input type="text" id="searchInput" class="form-control"
                           data-i18n-placeholder="filters.search_placeholder"
                           placeholder="<?= __t('filters.search_placeholder', 'Search jobs...') ?>">
                </div>

                <div class="filter-group">
                    <label for="statusFilter" data-i18n="filters.status">
                        <?= __t('filters.status', 'Status') ?>
                    </label>
                    <select id="statusFilter" class="form-control">
                        <option value="" data-i18n="filters.status_options.all">All Status</option>
                        <option value="draft" data-i18n="filters.status_options.draft">Draft</option>
                        <option value="published" data-i18n="filters.status_options.published">Published</option>
                        <option value="closed" data-i18n="filters.status_options.closed">Closed</option>
                        <option value="filled" data-i18n="filters.status_options.filled">Filled</option>
                        <option value="cancelled" data-i18n="filters.status_options.cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="jobTypeFilter" data-i18n="filters.job_type">Job Type</label>
                    <select id="jobTypeFilter" class="form-control">
                        <option value="">All Types</option>
                        <option value="full-time">Full-Time</option>
                        <option value="part-time">Part-Time</option>
                        <option value="contract">Contract</option>
                        <option value="temporary">Temporary</option>
                        <option value="internship">Internship</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="experienceLevelFilter" data-i18n="filters.experience_level">Experience Level</label>
                    <select id="experienceLevelFilter" class="form-control">
                        <option value="">All Levels</option>
                        <option value="entry">Entry Level</option>
                        <option value="mid">Mid Level</option>
                        <option value="senior">Senior</option>
                        <option value="lead">Lead</option>
                        <option value="executive">Executive</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="categoryFilter" data-i18n="filters.category">Category</label>
                    <select id="categoryFilter" class="form-control">
                        <option value="">All Categories</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button id="btnApplyFilters" class="btn btn-secondary" data-i18n="filters.apply">
                        <?= __t('filters.apply', 'Apply') ?>
                    </button>
                    <button id="btnResetFilters" class="btn btn-outline" data-i18n="filters.reset">
                        <?= __t('filters.reset', 'Reset') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div id="resultsCount" class="results-count" style="padding:12px 16px; margin-bottom:12px; background:var(--card-bg,#081127); border:1px solid var(--border-color,#263044); border-radius:8px; display:none;">
        <span style="color:var(--text-secondary,#94a3b8); font-size:0.9rem;">
            <i class="fas fa-briefcase"></i> 
            <span id="resultsCountText"></span>
        </span>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-body">
            <div id="tableLoading" class="loading-state">
                <div class="spinner"></div>
                <p data-i18n="jobs.loading"><?= __t('jobs.loading', 'Loading...') ?></p>
            </div>

            <div id="tableContainer" style="display:none">
                <div class="table-responsive">
                    <table class="data-table" id="jobsTable">
                        <thead>
                            <tr>
                                <th data-i18n="table.headers.id">ID</th>
                                <th data-i18n="table.headers.job_title">Job Title</th>
                                <th data-i18n="table.headers.job_type">Job Type</th>
                                <th data-i18n="table.headers.experience_level">Experience</th>
                                <th data-i18n="table.headers.category">Category</th>
                                <th data-i18n="table.headers.status">Status</th>
                                <th data-i18n="table.headers.positions">Positions</th>
                                <th data-i18n="table.headers.applications">Applications</th>
                                <th data-i18n="table.headers.deadline">Deadline</th>
                                <th data-i18n="table.headers.actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody"></tbody>
                    </table>
                </div>

                <div class="pagination-wrapper">
                    <div class="pagination-info">
                        <span data-i18n="pagination.showing">Showing</span>
                        <span id="paginationInfo">0-0 of 0</span>
                    </div>
                    <div class="pagination" id="pagination"></div>
                </div>
            </div>

            <div id="emptyState" class="empty-state" style="display:none">
                <div class="empty-icon">💼</div>
                <h3 data-i18n="table.empty.title">No Jobs Found</h3>
                <p data-i18n="table.empty.message">Start by adding your first job posting</p>
                <?php if ($canCreate): ?>
                <button class="btn btn-primary" onclick="if(window.Jobs)window.Jobs.add()">
                    <i class="fas fa-plus"></i>
                    <span data-i18n="table.empty.add_first">Add First Job</span>
                </button>
                <?php endif; ?>
            </div>

            <div id="errorState" class="error-state" style="display:none">
                <div class="error-icon">⚠️</div>
                <h3 data-i18n="messages.error.load_failed">Error Loading Data</h3>
                <p id="errorMessage"></p>
                <button id="btnRetry" class="btn btn-secondary" data-i18n="jobs.retry">Retry</button>
            </div>
        </div>
    </div>

</div>

<!-- Expose client-side globals for the module -->
<script type="text/javascript">
window.APP_CONFIG = window.APP_CONFIG || {};
window.APP_CONFIG.API_BASE = window.APP_CONFIG.API_BASE || '<?= $apiBase ?>';
window.APP_CONFIG.TENANT_ID = window.APP_CONFIG.TENANT_ID || <?= $tenantId ?>;
window.APP_CONFIG.CSRF_TOKEN = window.APP_CONFIG.CSRF_TOKEN || '<?= addslashes($csrf) ?>';
window.APP_CONFIG.USER_ID = window.APP_CONFIG.USER_ID || <?= admin_user_id() ?>;

window.USER_LANGUAGE = window.USER_LANGUAGE || '<?= addslashes($lang) ?>';
window.USER_DIRECTION = window.USER_DIRECTION || '<?= addslashes($dir) ?>';
window.CSRF_TOKEN = window.CSRF_TOKEN || '<?= addslashes($csrf) ?>';

// Page permissions available to JS
window.PAGE_PERMISSIONS = <?= json_encode([
    'canCreate' => $canCreate,
    'canEdit' => $canEdit,
    'canDelete' => $canDelete,
    'canViewAll' => $canViewAll,
    'canViewOwn' => $canViewOwn,
    'canViewTenant' => $canViewTenant,
    'canEditAll' => $canEditAll,
    'canEditOwn' => $canEditOwn,
    'canDeleteAll' => $canDeleteAll,
    'canDeleteOwn' => $canDeleteOwn,
    'isSuperAdmin' => is_super_admin()
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<script type="text/javascript">
window.JOBS_CONFIG = {
    apiUrl: '<?= $apiBase ?>/jobs',
    languagesApi: '<?= $apiBase ?>/languages',
    categoriesApi: '<?= $apiBase ?>/job_categories',
    skillsApi: '<?= $apiBase ?>/job_skills',
    lang: '<?= addslashes($lang) ?>',
    dir: '<?= addslashes($dir) ?>',
    csrfToken: '<?= addslashes($csrf) ?>',
    itemsPerPage: 25
};
</script>

<!-- Load JS -->
<script src="/admin/assets/js/pages/jobs.js?v=<?= time() ?>"></script>

<?php if (!$isFragment): ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>
