<?php /* Copyright (C) 2025 Henaxis */
$baseUrl  = dol_buildpath('/custom/b2cstore/public/index.php', 1);
$imageUrl = dol_buildpath('/custom/b2cstore/public/image.php', 1);
?>
<div class="b2cs-page b2cs-page--product">
	<div class="container">
		<nav class="b2cs-breadcrumb" aria-label="breadcrumb">
			<a href="<?php echo $baseUrl; ?>?controller=catalog">&larr; <?php echo $langs->trans('B2CStoreCatalog'); ?></a>
		</nav>

		<?php if (!empty($info)) { ?><div class="b2cs-alert b2cs-alert--info"><?php echo dol_escape_htmltag($info); ?></div><?php } ?>
		<?php if (!empty($error)) { ?><div class="b2cs-alert b2cs-alert--error"><?php echo dol_escape_htmltag($error); ?></div><?php } ?>

		<div class="b2cs-product-detail">
			<div class="b2cs-product-detail__gallery">
				<?php if (!empty($product['photo'])) { ?>
				<img src="<?php echo $imageUrl; ?>?id=<?php echo (int) $product['id']; ?>" alt="<?php echo dol_escape_htmltag($product['label']); ?>" class="b2cs-product-detail__img">
				<?php } else { ?>
				<div class="b2cs-product-detail__no-img">📦</div>
				<?php } ?>
			</div>
			<div class="b2cs-product-detail__info">
				<?php if (!empty($product['ref'])) { ?><p class="b2cs-product-detail__ref"><?php echo $langs->trans('B2CStoreRef'); ?>: <?php echo dol_escape_htmltag($product['ref']); ?></p><?php } ?>
				<h1 class="b2cs-product-detail__name"><?php echo dol_escape_htmltag($product['label']); ?></h1>
				<?php if (!empty($product['description'])) { ?>
				<div class="b2cs-product-detail__desc"><?php echo dol_string_nohtmltag($product['description'], 0, 'UTF-8', 0, 1); ?></div>
				<?php } ?>
				<?php if (!$pricesHidden) { ?>
				<div class="b2cs-product-detail__pricing">
					<span class="b2cs-product-detail__price"><?php echo price($product['price_ttc']); ?></span>
					<?php if (!empty($product['tva_tx'])) { ?>
					<span class="b2cs-product-detail__vat"><?php echo $langs->trans('B2CStoreVatIncluded'); ?> (<?php echo price($product['tva_tx']); ?>%)</span>
					<?php } ?>
				</div>
				<?php } ?>
				<?php if ($context->isLogged() || !$cartRequiresLogin) { ?>
				<form method="post" action="<?php echo $baseUrl; ?>?controller=cart" class="b2cs-product-detail__add-form">
					<input type="hidden" name="action" value="add">
					<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">
					<input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
					<div class="b2cs-product-detail__qty-row">
						<label for="pd-qty"><?php echo $langs->trans('B2CStoreQty'); ?></label>
						<input type="number" id="pd-qty" name="qty" value="1" min="1" max="9999" class="b2cs-input b2cs-input--qty">
						<button type="submit" class="b2cs-btn b2cs-btn--primary"><?php echo $langs->trans('B2CStoreAddToCart'); ?></button>
					</div>
				</form>
				<?php } elseif ($cartRequiresLogin) { ?>
				<p class="b2cs-product-detail__login-prompt">
					<a href="<?php echo $baseUrl; ?>?controller=login"><?php echo $langs->trans('B2CStoreLoginToOrder'); ?></a>
				</p>
				<?php } ?>
			</div>
		</div>
	</div>
</div>
