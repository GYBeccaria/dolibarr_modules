<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2BOrderContext $context */
/** @var Translate $langs */
/** @var array $products */
/** @var array $categories */
/** @var int $totalProducts */
/** @var int $totalPages */
/** @var int $page */
/** @var string $search */
/** @var int $category_id */

$baseUrl = dol_buildpath('/custom/b2border/public/index.php', 1);
$imageUrl = dol_buildpath('/custom/b2border/public/image.php', 1);
$showStock = getDolGlobalInt('B2BORDER_SHOW_STOCK', 1);
?>

<div class="b2b-catalog-header">
	<h1><?php echo $langs->trans("B2BOrderCatalog"); ?></h1>

	<form method="GET" action="<?php echo $baseUrl; ?>" class="b2b-catalog-filters">
		<input type="hidden" name="controller" value="catalog">
		<div class="b2b-filter-row">
			<div class="b2b-filter-search">
				<input type="text" name="search" value="<?php echo dol_escape_htmltag($search); ?>"
					placeholder="<?php echo dol_escape_htmltag($langs->trans("B2BOrderSearchProducts")); ?>" class="b2b-input">
			</div>
			<?php if (!empty($categories)) { ?>
			<div class="b2b-filter-category">
				<select name="category" class="b2b-select">
					<option value="0"><?php echo $langs->trans("B2BOrderAllCategories"); ?></option>
					<?php foreach ($categories as $catId => $catLabel) { ?>
						<option value="<?php echo (int) $catId; ?>"<?php echo $category_id == $catId ? ' selected' : ''; ?>>
							<?php echo dol_escape_htmltag($catLabel); ?>
						</option>
					<?php } ?>
				</select>
			</div>
			<?php } ?>
			<div class="b2b-filter-submit">
				<button type="submit" class="b2b-btn b2b-btn-secondary"><?php echo $langs->trans("Search"); ?></button>
			</div>
		</div>
	</form>
</div>

<?php if (empty($products)) { ?>
	<div class="b2b-empty-state">
		<p><?php echo $langs->trans("B2BOrderNoProducts"); ?></p>
	</div>
<?php } else { ?>

<div class="b2b-product-grid">
	<?php foreach ($products as $prod) { ?>
	<div class="b2b-product-card">
		<a href="<?php echo $baseUrl; ?>?controller=product&id=<?php echo (int) $prod['id']; ?>" class="b2b-product-link">
			<div class="b2b-product-image">
				<?php if (!empty($prod['photo'])) { ?>
					<img src="<?php echo $imageUrl; ?>?id=<?php echo (int) $prod['id']; ?>" alt="<?php echo dol_escape_htmltag($prod['label']); ?>" loading="lazy">
				<?php } else { ?>
					<div class="b2b-product-no-image">
						<span><?php echo dol_escape_htmltag(substr($prod['ref'], 0, 4)); ?></span>
					</div>
				<?php } ?>
			</div>
			<div class="b2b-product-info">
				<span class="b2b-product-ref"><?php echo dol_escape_htmltag($prod['ref']); ?></span>
				<h3 class="b2b-product-name"><?php echo dol_escape_htmltag($prod['label']); ?></h3>
				<div class="b2b-product-price">
					<?php echo b2border_format_price($prod['price_ht']); ?> <small><?php echo $langs->trans("HT"); ?></small>
				</div>
				<?php if ($showStock) { ?>
				<div class="b2b-product-stock <?php echo $prod['in_stock'] ? 'in-stock' : 'out-of-stock'; ?>">
					<?php echo $langs->trans($prod['in_stock'] ? "B2BOrderInStock" : "B2BOrderOutOfStock"); ?>
				</div>
				<?php } ?>
			</div>
		</a>
		<form method="POST" action="<?php echo $baseUrl; ?>?controller=catalog<?php echo ($search ? '&search='.urlencode($search) : '').($category_id ? '&category='.(int) $category_id : '').($page > 1 ? '&page='.(int) $page : ''); ?>" class="b2b-product-add">
			<input type="hidden" name="action" value="addtocart">
			<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">
			<input type="hidden" name="fk_product" value="<?php echo (int) $prod['id']; ?>">
			<input type="hidden" name="qty" value="1">
			<button type="submit" class="b2b-btn b2b-btn-primary b2b-btn-sm"><?php echo $langs->trans("B2BOrderAddToCart"); ?></button>
		</form>
	</div>
	<?php } ?>
</div>

<?php if ($totalPages > 1) { ?>
<nav class="b2b-pagination">
	<?php
	$paginationParams = array();
	if ($search) {
		$paginationParams['search'] = $search;
	}
	if ($category_id) {
		$paginationParams['category'] = $category_id;
	}
	?>
	<?php if ($page > 1) { ?>
		<a href="<?php echo $baseUrl; ?>?controller=catalog&page=<?php echo ($page - 1); ?><?php foreach ($paginationParams as $k => $v) { echo '&'.urlencode($k).'='.urlencode($v); } ?>" class="b2b-page-link">&laquo; <?php echo $langs->trans("Previous"); ?></a>
	<?php } ?>

	<?php
	$startPage = max(1, $page - 2);
	$endPage = min($totalPages, $page + 2);
	for ($p = $startPage; $p <= $endPage; $p++) { ?>
		<a href="<?php echo $baseUrl; ?>?controller=catalog&page=<?php echo $p; ?><?php foreach ($paginationParams as $k => $v) { echo '&'.urlencode($k).'='.urlencode($v); } ?>" class="b2b-page-link<?php echo $p == $page ? ' active' : ''; ?>"><?php echo $p; ?></a>
	<?php } ?>

	<?php if ($page < $totalPages) { ?>
		<a href="<?php echo $baseUrl; ?>?controller=catalog&page=<?php echo ($page + 1); ?><?php foreach ($paginationParams as $k => $v) { echo '&'.urlencode($k).'='.urlencode($v); } ?>" class="b2b-page-link"><?php echo $langs->trans("Next"); ?> &raquo;</a>
	<?php } ?>
</nav>
<?php } ?>

<?php } ?>
