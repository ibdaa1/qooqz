<?php
/**
 * Frontend Footer Partial — QOOQZ Global Public Interface
 */
$_year     = date('Y');
$_ctx      = $GLOBALS['PUB_CONTEXT'] ?? [];
$_lang     = $_ctx['lang'] ?? 'ar';
$_appName  = $GLOBALS['PUB_APP_NAME'] ?? 'QOOQZ';
$_basePath = rtrim($GLOBALS['PUB_BASE_PATH'] ?? '/frontend/public', '/');
if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
?>

<!-- =============================================
     FOOTER
============================================= -->
<footer class="pub-footer" role="contentinfo">
    <div class="pub-container">
        <div class="pub-footer-grid">

            <!-- Brand column -->
            <div class="pub-footer-col">
                <p class="pub-footer-brand-name">🌐 <?= e($_appName) ?></p>
                <p class="pub-footer-brand-desc">
                    <?= $_lang === 'ar'
                        ? 'منصة عالمية متكاملة لعرض المنتجات والوظائف والكيانات والمستأجرين.'
                        : 'A complete global platform for products, jobs, entities, and tenants.' ?>
                </p>
            </div>

            <!-- Quick links -->
            <div class="pub-footer-col">
                <p class="pub-footer-col-title"><?= $_lang === 'ar' ? 'روابط سريعة' : 'Quick Links' ?></p>
                <a href="<?= e($_basePath . '/index.php') ?>"><?= $_lang === 'ar' ? 'الرئيسية' : 'Home' ?></a>
                <a href="<?= e($_basePath . '/products.php') ?>"><?= $_lang === 'ar' ? 'المنتجات' : 'Products' ?></a>
                <a href="<?= e($_basePath . '/jobs.php') ?>"><?= $_lang === 'ar' ? 'الوظائف' : 'Jobs' ?></a>
                <a href="<?= e($_basePath . '/entities.php') ?>"><?= $_lang === 'ar' ? 'الكيانات' : 'Entities' ?></a>
                <a href="<?= e($_basePath . '/tenants.php') ?>"><?= $_lang === 'ar' ? 'المستأجرون' : 'Tenants' ?></a>
            </div>

            <!-- Support -->
            <div class="pub-footer-col">
                <p class="pub-footer-col-title"><?= $_lang === 'ar' ? 'الدعم' : 'Support' ?></p>
                <a href="#"><?= $_lang === 'ar' ? 'من نحن' : 'About Us' ?></a>
                <a href="#"><?= $_lang === 'ar' ? 'تواصل معنا' : 'Contact' ?></a>
                <a href="#"><?= $_lang === 'ar' ? 'سياسة الخصوصية' : 'Privacy Policy' ?></a>
                <a href="#"><?= $_lang === 'ar' ? 'الشروط والأحكام' : 'Terms of Service' ?></a>
            </div>

            <!-- Auth -->
            <div class="pub-footer-col">
                <p class="pub-footer-col-title"><?= $_lang === 'ar' ? 'الحساب' : 'Account' ?></p>
                <a href="/frontend/login.html"><?= $_lang === 'ar' ? 'تسجيل الدخول' : 'Login' ?></a>
                <a href="/frontend/register.html"><?= $_lang === 'ar' ? 'إنشاء حساب' : 'Register' ?></a>
            </div>

        </div>
    </div>

    <div class="pub-footer-bottom">
        © <?= $_year ?> <?= e($_appName) ?> —
        <?= $_lang === 'ar' ? 'جميع الحقوق محفوظة' : 'All rights reserved' ?>
    </div>
</footer>

<!-- Back-to-top button -->
<?php $_btt_side = $_lang === 'ar' ? 'left' : 'right'; ?>
<button id="pubBackToTop" title="<?= $_lang === 'ar' ? 'العودة للأعلى' : 'Back to top' ?>"
        style="display:none;position:fixed;bottom:20px;<?= e($_btt_side) ?>:20px;
               z-index:200;width:40px;height:40px;background:var(--pub-primary);color:#fff;
               border:none;border-radius:50%;font-size:1.2rem;cursor:pointer;align-items:center;
               justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,0.2);">↑</button>

<!-- Public JS -->
<script src="/frontend/assets/js/public.js" defer></script>
</body>
</html>
