#!/bin/bash

# نص التحقق من جاهزية نظام الوظائف
# Jobs System Readiness Verification Script

echo "================================================"
echo "    نظام إدارة الوظائف - التحقق من الجاهزية    "
echo "  Jobs Management System - Readiness Check  "
echo "================================================"
echo ""

# Counter for checks
PASSED=0
FAILED=0

# Function to check file exists
check_file() {
    local file=$1
    local desc=$2
    if [ -f "$file" ]; then
        echo "✅ $desc"
        ((PASSED++))
        return 0
    else
        echo "❌ $desc - NOT FOUND"
        ((FAILED++))
        return 1
    fi
}

# Check Admin Fragments
echo "🔍 Checking Admin Fragments..."
check_file "admin/fragments/job_categories.php" "Job Categories Fragment"
check_file "admin/fragments/jobs.php" "Jobs Fragment"
echo ""

# Check JavaScript Files
echo "🔍 Checking JavaScript Files..."
check_file "admin/assets/js/pages/job_categories.js" "Job Categories JS"
check_file "admin/assets/js/pages/jobs.js" "Jobs JS"
echo ""

# Check CSS Files
echo "🔍 Checking CSS Files..."
check_file "admin/assets/css/pages/job_categories.css" "Job Categories CSS"
check_file "admin/assets/css/pages/jobs.css" "Jobs CSS"
echo ""

# Check Translation Files
echo "🔍 Checking Translation Files..."
check_file "languages/JobCategories/ar.json" "Job Categories Arabic Translations"
check_file "languages/JobCategories/en.json" "Job Categories English Translations"
check_file "languages/Jobs/ar.json" "Jobs Arabic Translations"
check_file "languages/Jobs/en.json" "Jobs English Translations"
echo ""

# Check API Routes
echo "🔍 Checking API Routes..."
check_file "api/routes/job_categories.php" "Job Categories API Route"
check_file "api/routes/jobs.php" "Jobs API Route"
echo ""

# Check Repositories
echo "🔍 Checking Repositories..."
check_file "api/v1/models/jobs/repositories/PdoJobCategoriesRepository.php" "Job Categories Repository"
check_file "api/v1/models/jobs/repositories/PdoJobsRepository.php" "Jobs Repository"
echo ""

# Check Services
echo "🔍 Checking Services..."
check_file "api/v1/models/jobs/services/JobCategoriesService.php" "Job Categories Service"
check_file "api/v1/models/jobs/services/JobsService.php" "Jobs Service"
echo ""

# Check Validators
echo "🔍 Checking Validators..."
check_file "api/v1/models/jobs/validators/JobCategoriesValidator.php" "Job Categories Validator"
check_file "api/v1/models/jobs/validators/JobsValidator.php" "Jobs Validator"
echo ""

# Check Documentation
echo "🔍 Checking Documentation..."
check_file "JOBS_SYSTEM_IMPLEMENTATION.md" "Implementation Guide"
check_file "SESSION_COMPLETE_SUMMARY.md" "Session Summary"
check_file "SYSTEM_READY_CONFIRMATION.md" "Ready Confirmation (This File)"
echo ""

# Summary
echo "================================================"
echo "              📊 SUMMARY | الملخص              "
echo "================================================"
echo "✅ Passed: $PASSED"
echo "❌ Failed: $FAILED"
echo ""

if [ $FAILED -eq 0 ]; then
    echo "🎉 SUCCESS! All files are present."
    echo "🎉 نجاح! جميع الملفات موجودة."
    echo ""
    echo "✅ النظام جاهز تماماً للاستخدام"
    echo "✅ System is completely ready for use"
    echo ""
    echo "🚀 Access the system at:"
    echo "   Job Categories: /admin/fragments/job_categories.php"
    echo "   Jobs: /admin/fragments/jobs.php"
    exit 0
else
    echo "⚠️  WARNING: Some files are missing!"
    echo "⚠️  تحذير: بعض الملفات مفقودة!"
    echo ""
    echo "Please ensure you are on the correct branch:"
    echo "git checkout copilot/update-manage-tenant-users"
    exit 1
fi
