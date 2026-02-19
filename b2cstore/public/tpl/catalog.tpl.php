<?php /* Copyright (C) 2025 Henaxis */
$baseUrl  = dol_buildpath('/custom/b2cstore/public/index.php', 1);
$imageUrl = dol_buildpath('/custom/b2cstore/public/image.php', 1);
?>
<div class="b2cs-page b2cs-page--catalog">
	<div class="container">
		<h1 class="b2cs-page__title"><?php echo $langs->trans('B2CStoreCatalog'); ?></h1>

		<?php if (!empty($info)) { ?><div class="b2cs-alert b2cs-alert--info"><?php echo dol_escape_htmltag($info); ?></div><?php } ?>
		<?php if (!empty($error)) { ?><div class="b2cs-alert b2cs-alert--error"><?php echo dol_escape_htmltag($error); ?></div><?php } ?>

		<!-- Filters -->
		<form class="b2cs-catalog-filters" method="get" action="<?php echo $baseUrl; ?>">
			<input type="hidden" name="controller" value="catalog">
			<div class="b2cs-catalog-filters__inner">
				<div class="b2cs-catalog-filters__search">
					<input type="text" name="q" value="<?php echo dol_escape_htmltag($searchQuery ?? ''); ?>" placeholder="<?php echo $langs->trans('B2CStoreSearch'); ?>..." class="b2cs-input">
				</div>
				<?php if (!empty($categories)) { ?>
				<div class="b2cs-catalog-filters__cats">
					<select name="category" class="b2cs-select" onchange="this.form.submit()">
						<option value=""><?php echo $langs->trans('B2CStoreAllCategories'); ?></option>
						<?php foreach ($categories as $cat) { ?>
						<option value="<?php echo (int) $cat['id']; ?>" <?php echo (isset($selectedCategory) && $selectedCategory == $cat['id']) ? 'selected' : ''; ?>><?php echo dol_escape_htmltag($cat['label']); ?></option>
						<?php } ?>
					</select>
				</div>
				<?php } ?>
				<button type="submit" class="b2cs-btn b2cs-btn--secondary"><?php echo $langs->trans('B2CStoreSearch'); ?></button>
				<?php if (!empty($searchQuery) || !empty($selectedCategory)) { ?>
				<a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2cs-btn b2cs-btn--ghost"><?php echo $langs->trans('B2CStoreReset'); ?></a>
				<?php } ?>
			</div>
		</form>

		<!-- Product grid -->
		<?php if (!empty($products)) { ?>
		<div class="b2cs-grid b2cs-grid--products">
			<?php foreach ($products as $prod) { ?>
			<article class="b2cs-product-card">
				<a href="<?php echo $baseUrl; ?>?controller=product&id=<?php echo (int) $prod['id']; ?>" class="b2cs-product-card__link">
					<div class="b2cs-product-card__img-wrap">
						<?php if (!empty($prod['photo'])) { ?>
						<img src="<?php echo $imageUrl; ?>?id=<?php echo (int) $prod['id']; ?>&thumb=1" alt="<?php echo dol_escape_htmltag($prod['label']); ?>" loading="lazy" class="b2cs-product-card__img">
						<?php } else { ?>
						<div class="b2cs-product-card__no-img">📦</div>
						<?php } ?>
					</div>
					<div class="b2cs-product-card__body">
						<?php if (!empty($prod['ref'])) { ?><span class="b2cs-product-card__ref"><?php echo dol_escape_htmltag($prod['ref']); ?></span><?php } ?>
						<h2 class="b2cs-product-card__name"><?php echo dol_escape_htmltag($prod['label']); ?></h2>
						<?php if (!$pricesHidden) { ?>
						<p class="b2cs-product-card__price"><?php echo price($prod['price_ttc']); ?></p>
						<?php } ?>
					</div>
				</a>
				<?php if (!$cartRequiresLogin || $context->isLogged()) { ?>
				<form method="post" action="<?php echo $baseUrl; ?>?controller=cart" class="b2cs-product-card__add">
					<input type="hidden" name="action" value="add">
					<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">
					<input type="hidden" name="product_id" value="<?php echo (int) $prod['id']; ?>">
					<button type="submit" class="b2cs-btn b2cs-btn--primary b2cs-btn--sm"><?php echo $langs->trans('B2CStoreAddToCart'); ?></button>
				</form>
				<?php } ?>
			</article>
			<?php } ?>
		</div>

		<!-- Pagination -->
		<?php if (!empty($pagination) && $pagination['total_pages'] > 1) { ?>
		<nav class="b2cs-pagination" aria-label="<?php echo $langs->trans('B2CStorePagination'); ?>">
			<?php if ($pagination['current_page'] > 1) { ?>
			<a href="<?php echo $baseUrl; ?>?controller=catalog&page=<?php echo $pagination['current_page'] - 1; ?><?php echo !empty($searchQuery) ? '&q='.urlencode($searchQuery) : ''; ?><?php echo !empty($selectedCategory) ? '&category='.(int)$selectedCategory : ''; ?>" class="b2cs-pagination__btn">&laquo;</a>
			<?php } ?>
			<?php for ($p = 1; $p <= $pagination['total_pages']; $p++) { ?>
			<a href="<?php echo $baseUrl; ?>?controller=catalog&page=<?php echo $p; ?><?php echo !empty($searchQuery) ? '&q='.urlencode($searchQuery) : ''; ?><?php echo !empty($selectedCategory) ? '&category='.(int)$selectedCategory : ''; ?>" class="b2cs-pagination__btn <?php echo $p == $pagination['current_page'] ? 'b2cs-pagination__btn--active' : ''; ?>"><?php echo $p; ?></a>
			<?php } ?>
			<?php if ($pagination['current_page'] < $pagination['total_pages']) { ?>
			<a href="<?php echo $baseUrl; ?>?controller=catalog&page=<?php echo $pagination['current_page'] + 1; ?><?php echo !empty($searchQuery) ? '&q='.urlencode($searchQuery) : ''; ?><?php echo !empty($selectedCategory) ? '&category='.(int)$selectedCategory : ''; ?>" class="b2cs-pagination__btn">&raquo;</a>
			<?php } ?>
		</nav>
		<?php } ?>

		<?php } else { ?>
		<p class="b2cs-empty"><?php echo $langs->trans('B2CStoreNoProducts'); ?></p>
		<?php } ?>
	</div>
</div>
