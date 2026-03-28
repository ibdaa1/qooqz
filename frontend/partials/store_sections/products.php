<?php
/**
 * frontend/partials/store_sections/products.php
 * Store Products Section — Product grid with categories, search, pagination
 *
 * Expected variables:
 *   $entity, $entityId, $entityTenantId
 *   $products, $productMeta, $productPage, $productLimit
 *   $categories, $categoryTree, $selectedCat, $productSearch
 *   $lang, $_entityProductCardStyle, $_entityProductCardClass, $_entityProductImgStyle
 *   $sectionSettings — Section JSON settings
 */

$showCategories = ($sectionSettings['show_categories'] ?? true);
$showSearch     = ($sectionSettings['show_search']     ?? true);
$showCart       = ($sectionSettings['show_cart']        ?? true);
?>

<div class="pub-tab-panel active" id="tabProducts">
    <!-- Hierarchical category menus + search -->
    <?php if ($showCategories && !empty($categoryTree)):
        // Pre-compute which parent category (if any) contains the selected category
        $activePrimaryId = 0;
        foreach ($categoryTree as $mainCat) {
            $mId = (int)($mainCat['id'] ?? 0);
            if ($selectedCat === $mId) { $activePrimaryId = $mId; break; }
            foreach (($mainCat['children'] ?? []) as $ch) {
                if ($selectedCat === (int)($ch['id'] ?? 0)) { $activePrimaryId = $mId; break 2; }
            }
        }
    ?>
    <!-- Main category tabs (parent categories) -->
    <div class="pub-cat-tabs pub-cat-tabs--main" style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;overflow-x:auto;padding-bottom:4px;" role="tablist">
        <a href="?id=<?= $entityId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn <?= !$selectedCat ? 'active' : '' ?>" role="tab"
           aria-selected="<?= !$selectedCat ? 'true' : 'false' ?>">
            <?= e(t('entity.all_categories')) ?>
        </a>
        <?php foreach ($categoryTree as $mainCat):
            $mainId      = (int)($mainCat['id'] ?? 0);
            $parentActive = ($activePrimaryId === $mainId);
        ?>
        <a href="?id=<?= $entityId ?>&cat=<?= $mainId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn <?= $parentActive ? 'active' : '' ?>" role="tab"
           aria-selected="<?= $parentActive ? 'true' : 'false' ?>">
            <?= e($mainCat['name'] ?? '') ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php if ($activePrimaryId):
        // Find the active parent and render its sub-category tabs
        foreach ($categoryTree as $mainCat):
            if ((int)($mainCat['id'] ?? 0) !== $activePrimaryId) continue;
            $children = $mainCat['children'] ?? [];
            if (empty($children)) break;
    ?>
    <!-- Sub-category tabs -->
    <div class="pub-cat-tabs pub-cat-tabs--sub" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;overflow-x:auto;padding-bottom:4px;padding-inline-start:16px;" role="tablist">
        <a href="?id=<?= $entityId ?>&cat=<?= $activePrimaryId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn pub-cat-tab-btn--sub <?= ($selectedCat === $activePrimaryId) ? 'active' : '' ?>">
            <?= e(t('entity.all_in_category', 'All items')) ?>
        </a>
        <?php foreach ($children as $subCat):
            $subId = (int)($subCat['id'] ?? 0);
        ?>
        <a href="?id=<?= $entityId ?>&cat=<?= $subId ?><?= $productSearch ? '&q=' . urlencode($productSearch) : '' ?>"
           class="pub-cat-tab-btn pub-cat-tab-btn--sub <?= $selectedCat === $subId ? 'active' : '' ?>">
            <?= e($subCat['name'] ?? '') ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endforeach; endif; ?>
    <?php endif; ?>

    <?php if (!empty($products)): ?>
    <div class="pub-grid" style="margin-top:20px;">
        <?php foreach ($products as $p): ?>
        <?php
            // Build all images list for slideshow
            $pAllImgs = [];
            if (!empty($p['image_urls'])) {
                foreach (explode('|', $p['image_urls']) as $rawU) {
                    $s = pub_img(trim($rawU), 'product_thumb');
                    if ($s) $pAllImgs[] = $s;
                }
            } elseif (!empty($p['image_url'])) {
                $pAllImgs[] = pub_img($p['image_url'], 'product_thumb');
            }
            $pHasMulti = count($pAllImgs) > 1;
        ?>
        <div class="pub-product-card<?= $_entityProductCardClass ? ' ' . $_entityProductCardClass : '' ?>"<?= $_entityProductCardStyle ? ' style="' . e($_entityProductCardStyle) . '"' : '' ?><?= $pHasMulti ? ' data-img-slide="1"' : '' ?>>
            <a href="/frontend/public/product.php?id=<?= (int)($p['id'] ?? 0) ?>"
               style="text-decoration:none;display:block;">
            <div class="pub-cat-img-wrap" style="<?= e($_entityProductImgStyle) ?>">
                <?php if (!empty($pAllImgs)): ?>
                    <?php foreach ($pAllImgs as $piIdx => $piSrc): ?>
                    <img src="<?= e($piSrc) ?>"
                         alt="<?= e($p['name'] ?? '') ?>" class="pub-cat-img pub-slide-img<?= $piIdx > 0 ? ' pub-slide-img--hidden' : '' ?>" loading="lazy"
                         onerror="this.style.display='none'">
                    <?php endforeach; ?>
                    <span class="pub-img-placeholder" style="display:none;">🖼️</span>
                    <?php if ($pHasMulti): ?>
                    <div class="pub-slide-dots" aria-hidden="true">
                        <?php for ($pdi = 0; $pdi < count($pAllImgs); $pdi++): ?>
                        <span class="pub-slide-dot<?= $pdi === 0 ? ' pub-slide-dot--active' : '' ?>"></span>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="pub-img-placeholder">🖼️</span>
                <?php endif; ?>
            </div>
            <div class="pub-product-card-body">
                <?php if (!empty($p['is_featured'])): ?>
                    <span class="pub-product-badge"><?= e(t('products.featured')) ?></span>
                <?php endif; ?>
                <p class="pub-product-name"><?= e($p['name'] ?? '') ?></p>
                <?php if (!empty($p['price'])): ?>
                    <p class="pub-product-price"><?= number_format((float)$p['price'], 2) ?> <?= e($p['currency_code'] ?? t('common.currency')) ?></p>
                <?php endif; ?>
            </div>
            </a>
            <?php if ($showCart): ?>
            <button class="pub-cart-add-btn"
                    onclick="pubAddToCart(this)"
                    data-product-id="<?= (int)($p['id'] ?? 0) ?>"
                    data-product-name="<?= e($p['name'] ?? '') ?>"
                    data-product-price="<?= (float)($p['price'] ?? 0) ?>"
                    data-product-image="<?= e($pAllImgs[0] ?? ($p['image_url'] ?? '')) ?>"
                    data-product-sku="<?= e($p['sku'] ?? '') ?>">
                🛒 <?= e(t('cart.add')) ?>
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- Product pagination -->
    <?php
    $totalPg = (int)($productMeta['total_pages'] ?? 1);
    if ($totalPg > 1):
        $pg_url = fn(int $pg) => '?id=' . $entityId . ($selectedCat ? '&cat=' . $selectedCat : '') . '&page=' . $pg . '#tabProducts';
    ?>
    <nav class="pub-pagination" style="margin-top:24px;">
        <a href="<?= $pg_url(max(1,$productPage-1)) ?>" class="pub-page-btn <?= $productPage<=1?'disabled':'' ?>">
            <?= e(t('pagination.prev')) ?>
        </a>
        <?php for ($i=max(1,$productPage-2); $i<=min($totalPg,$productPage+2); $i++): ?>
            <a href="<?= $pg_url($i) ?>" class="pub-page-btn <?= $i===$productPage?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a href="<?= $pg_url(min($totalPg,$productPage+1)) ?>" class="pub-page-btn <?= $productPage>=$totalPg?'disabled':'' ?>">
            <?= e(t('pagination.next')) ?>
        </a>
    </nav>
    <?php endif; ?>
    <?php else: ?>
    <div class="pub-empty" style="margin-top:40px;">
        <div class="pub-empty-icon">🛍️</div>
        <p class="pub-empty-msg"><?= e(t('entity.no_products')) ?></p>
    </div>
    <?php endif; ?>
</div>
