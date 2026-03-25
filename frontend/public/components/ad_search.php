<?php
declare(strict_types=1);
/**
 * Component: ad_search
 * Renders the search bar form.
 */

?>
<div class="pub-search-bar">
    <div class="pub-container">
        <form class="pub-search-form" method="get" action="/frontend/public/entities.php" id="pubSearchForm">
            <input type="search" 
                   name="q" 
                   class="pub-search-input"
                   placeholder="<?= e(t('search.placeholder')) ?>"
                   value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit" class="pub-search-btn"><?= e(t('search.button')) ?></button>
        </form>
    </div>
</div>