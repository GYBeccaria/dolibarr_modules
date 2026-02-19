<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2BOrderContext $context */
/** @var Translate $langs */
/** @var array $productData */

$baseUrl = dol_buildpath('/custom/b2border/public/index.php', 1);
$imageUrl = dol_buildpath('/custom/b2border/public/image.php', 1);
$showStock = getDolGlobalInt('B2BORDER_SHOW_STOCK', 1);
?>

<div class="b2b-breadcrumb">
	<a href="<?php echo $baseUrl; ?>?controller=catalog"><?php echo $langs->trans("B2BOrderCatalog"); ?></a>
	<span>&rsaquo;</span>
	<span><?php echo dol_escape_htmltag($productData['label']); ?></span>
</div>

<div class="b2b-product-detail">
	<div class="b2b-product-detail-image">
		<?php if (!empty($productData['photo'])) { ?>
			<img src="<?php echo $imageUrl; ?>?id=<?php echo (int) $productData['id']; ?>" alt="<?php echo dol_escape_htmltag($productData['label']); ?>">
		<?php } else { ?>
			<div class="b2b-product-no-image b2b-product-no-image-lg">
				<span><?php echo dol_escape_htmltag($productData['ref']); ?></span>
			</div>
		<?php } ?>
	</div>

	<div class="b2b-product-detail-info">
		<span class="b2b-product-ref"><?php echo dol_escape_htmltag($productData['ref']); ?></span>
		<h1 class="b2b-product-detail-name"><?php echo dol_escape_htmltag($productData['label']); ?></h1>

		<div class="b2b-product-detail-price">
			<span class="b2b-price-amount"><?php echo b2border_format_price($productData['price_ht']); ?></span>
			<span class="b2b-price-label"><?php echo $langs->trans("HT"); ?></span>
			<br>
			<span class="b2b-price-ttc"><?php echo b2border_format_price($productData['price_ttc']); ?> <?php echo $langs->trans("TTC"); ?></span>
			<span class="b2b-price-vat">(<?php echo $langs->trans("VAT"); ?> <?php echo price($productData['tva_tx']); ?>%)</span>
		</div>

		<?php if ($showStock) { ?>
		<div class="b2b-product-stock-detail <?php echo $productData['in_stock'] ? 'in-stock' : 'out-of-stock'; ?>">
			<?php echo $langs->trans($productData['in_stock'] ? "B2BOrderInStock" : "B2BOrderOutOfStock"); ?>
		</div>
		<?php } ?>

		<form method="POST" action="<?php echo $baseUrl; ?>?controller=product&id=<?php echo (int) $productData['id']; ?>" class="b2b-add-to-cart-form">
			<input type="hidden" name="action" value="addtocart">
			<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">
			<input type="hidden" name="fk_product" value="<?php echo (int) $productData['id']; ?>">
			<div class="b2b-qty-row">
				<label for="qty"><?php echo $langs->trans("Qty"); ?>:</label>
				<input type="number" id="qty" name="qty" value="1" min="1" class="b2b-input b2b-input-qty">
				<button type="submit" class="b2b-btn b2b-btn-primary"><?php echo $langs->trans("B2BOrderAddToCart"); ?></button>
			</div>
		</form>

		<?php if (!empty($productData['description'])) { ?>
		<div class="b2b-product-description">
			<h3><?php echo $langs->trans("Description"); ?></h3>
			<div><?php echo dol_htmlentitiesbr($productData['description']); ?></div>
		</div>
		<?php } ?>

		<?php if (!empty($productData['note_public'])) { ?>
		<div class="b2b-product-notes">
			<div><?php echo dol_htmlentitiesbr($productData['note_public']); ?></div>
		</div>
		<?php } ?>
	</div>
</div>
