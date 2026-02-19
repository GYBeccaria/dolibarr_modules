<?php /* Copyright (C) 2025 Henaxis */
$baseUrl = dol_buildpath('/custom/b2cstore/public/index.php', 1);
$imageUrl = dol_buildpath('/custom/b2cstore/public/image.php', 1);
?>
<div class="b2cs-page b2cs-page--cart">
	<div class="container">
		<h1 class="b2cs-page__title"><?php echo $langs->trans('B2CStoreCart'); ?></h1>

		<?php if (!empty($info)) { ?><div class="b2cs-alert b2cs-alert--info"><?php echo dol_escape_htmltag($info); ?></div><?php } ?>
		<?php if (!empty($error)) { ?><div class="b2cs-alert b2cs-alert--error"><?php echo dol_escape_htmltag($error); ?></div><?php } ?>

		<?php if (!empty($cartItems)) { ?>
		<div class="b2cs-cart">
			<div class="b2cs-cart__items">
				<?php foreach ($cartItems as $item) { ?>
				<div class="b2cs-cart__item">
					<div class="b2cs-cart__item-img">
						<?php if (!empty($item['photo'])) { ?>
						<img src="<?php echo $imageUrl; ?>?id=<?php echo (int) $item['product_id']; ?>&thumb=1" alt="<?php echo dol_escape_htmltag($item['label']); ?>" loading="lazy">
						<?php } else { ?>
						<div class="b2cs-cart__item-no-img">📦</div>
						<?php } ?>
					</div>
					<div class="b2cs-cart__item-info">
						<a href="<?php echo $baseUrl; ?>?controller=product&id=<?php echo (int) $item['product_id']; ?>" class="b2cs-cart__item-name"><?php echo dol_escape_htmltag($item['label']); ?></a>
						<?php if (!empty($item['ref'])) { ?><span class="b2cs-cart__item-ref"><?php echo dol_escape_htmltag($item['ref']); ?></span><?php } ?>
						<span class="b2cs-cart__item-unit-price"><?php echo price($item['unit_price_ttc']); ?> / <?php echo $langs->trans('B2CStoreUnit'); ?></span>
					</div>
					<form method="post" action="<?php echo $baseUrl; ?>?controller=cart" class="b2cs-cart__item-qty-form">
						<input type="hidden" name="action" value="update">
						<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">
						<input type="hidden" name="product_id" value="<?php echo (int) $item['product_id']; ?>">
						<input type="number" name="qty" value="<?php echo (int) $item['qty']; ?>" min="1" max="9999" class="b2cs-input b2cs-input--qty" onchange="this.form.submit()">
					</form>
					<div class="b2cs-cart__item-subtotal"><?php echo price($item['subtotal_ttc']); ?></div>
					<form method="post" action="<?php echo $baseUrl; ?>?controller=cart" class="b2cs-cart__item-remove">
						<input type="hidden" name="action" value="remove">
						<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">
						<input type="hidden" name="product_id" value="<?php echo (int) $item['product_id']; ?>">
						<button type="submit" class="b2cs-btn-icon" title="<?php echo $langs->trans('B2CStoreRemove'); ?>">&times;</button>
					</form>
				</div>
				<?php } ?>
			</div>

			<div class="b2cs-cart__summary">
				<div class="b2cs-cart__totals">
					<div class="b2cs-cart__total-row">
						<span><?php echo $langs->trans('B2CStoreSubtotalHT'); ?></span>
						<span><?php echo price($totals['total_ht']); ?></span>
					</div>
					<div class="b2cs-cart__total-row">
						<span><?php echo $langs->trans('B2CStoreVat'); ?></span>
						<span><?php echo price($totals['total_vat']); ?></span>
					</div>
					<div class="b2cs-cart__total-row b2cs-cart__total-row--grand">
						<span><?php echo $langs->trans('B2CStoreTotalTTC'); ?></span>
						<span><?php echo price($totals['total_ttc']); ?></span>
					</div>
				</div>
				<a href="<?php echo $baseUrl; ?>?controller=checkout" class="b2cs-btn b2cs-btn--primary b2cs-btn--full"><?php echo $langs->trans('B2CStoreProceedCheckout'); ?></a>
				<a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2cs-btn b2cs-btn--ghost b2cs-btn--full"><?php echo $langs->trans('B2CStoreContinueShopping'); ?></a>
				<form method="post" action="<?php echo $baseUrl; ?>?controller=cart" class="b2cs-cart__clear">
					<input type="hidden" name="action" value="clear">
					<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">
					<button type="submit" class="b2cs-btn-link b2cs-btn-link--danger"><?php echo $langs->trans('B2CStoreClearCart'); ?></button>
				</form>
			</div>
		</div>
		<?php } else { ?>
		<div class="b2cs-empty">
			<p><?php echo $langs->trans('B2CStoreCartEmpty'); ?></p>
			<a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2cs-btn b2cs-btn--primary"><?php echo $langs->trans('B2CStoreViewCatalog'); ?></a>
		</div>
		<?php } ?>
	</div>
</div>
