<?php /* Copyright (C) 2025 Henaxis */
$baseUrl = dol_buildpath('/custom/b2cstore/public/index.php', 1);
$imageUrl = dol_buildpath('/custom/b2cstore/public/image.php', 1);
?>
<div class="b2cs-page b2cs-page--checkout">
	<div class="container">
		<h1 class="b2cs-page__title"><?php echo $langs->trans('B2CStoreCheckout'); ?></h1>

		<?php if (!empty($error)) { ?><div class="b2cs-alert b2cs-alert--error"><?php echo dol_escape_htmltag($error); ?></div><?php } ?>

		<div class="b2cs-checkout">
			<div class="b2cs-checkout__order-summary">
				<h2 class="b2cs-checkout__section-title"><?php echo $langs->trans('B2CStoreOrderSummary'); ?></h2>
				<div class="b2cs-checkout__items">
					<?php foreach ($cartItems as $item) { ?>
					<div class="b2cs-checkout__item">
						<div class="b2cs-checkout__item-img">
							<?php if (!empty($item['photo'])) { ?>
							<img src="<?php echo $imageUrl; ?>?id=<?php echo (int) $item['product_id']; ?>&thumb=1" alt="<?php echo dol_escape_htmltag($item['label']); ?>" loading="lazy">
							<?php } ?>
						</div>
						<div class="b2cs-checkout__item-label">
							<?php echo dol_escape_htmltag($item['label']); ?>
							<span class="b2cs-checkout__item-qty"> &times; <?php echo (int) $item['qty']; ?></span>
						</div>
						<div class="b2cs-checkout__item-subtotal"><?php echo price($item['subtotal_ttc']); ?></div>
					</div>
					<?php } ?>
				</div>
				<div class="b2cs-checkout__totals">
					<div class="b2cs-checkout__total-row">
						<span><?php echo $langs->trans('B2CStoreSubtotalHT'); ?></span>
						<span><?php echo price($totals['total_ht']); ?></span>
					</div>
					<div class="b2cs-checkout__total-row">
						<span><?php echo $langs->trans('B2CStoreVat'); ?></span>
						<span><?php echo price($totals['total_vat']); ?></span>
					</div>
					<div class="b2cs-checkout__total-row b2cs-checkout__total-row--grand">
						<span><?php echo $langs->trans('B2CStoreTotalTTC'); ?></span>
						<span><?php echo price($totals['total_ttc']); ?></span>
					</div>
				</div>
				<a href="<?php echo $baseUrl; ?>?controller=cart" class="b2cs-btn-link">&larr; <?php echo $langs->trans('B2CStoreEditCart'); ?></a>
			</div>

			<div class="b2cs-checkout__form-section">
				<h2 class="b2cs-checkout__section-title"><?php echo $langs->trans('B2CStoreDeliveryInfo'); ?></h2>
				<?php if (!empty($customer)) { ?>
				<div class="b2cs-checkout__customer-info">
					<p><strong><?php echo dol_escape_htmltag($customer->name); ?></strong></p>
					<?php if (!empty($customer->address)) { ?><p><?php echo dol_escape_htmltag($customer->address); ?></p><?php } ?>
					<?php if (!empty($customer->zip) || !empty($customer->town)) { ?><p><?php echo dol_escape_htmltag($customer->zip.' '.$customer->town); ?></p><?php } ?>
					<?php if (!empty($customer->email)) { ?><p><?php echo dol_escape_htmltag($customer->email); ?></p><?php } ?>
				</div>
				<?php } ?>

				<form method="post" action="<?php echo $baseUrl; ?>?controller=checkout">
					<input type="hidden" name="action" value="createorder">
					<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">
					<div class="b2cs-form__field">
						<label for="co-notes"><?php echo $langs->trans('B2CStoreOrderNotes'); ?></label>
						<textarea id="co-notes" name="note" rows="3" class="b2cs-textarea"><?php echo dol_escape_htmltag($formData['note'] ?? ''); ?></textarea>
					</div>
					<?php if (!empty($paymentTerms)) { ?>
					<div class="b2cs-form__field">
						<label for="co-payment"><?php echo $langs->trans('B2CStorePaymentMethod'); ?></label>
						<select id="co-payment" name="payment_terms_id" class="b2cs-select">
							<?php foreach ($paymentTerms as $pt) { ?>
							<option value="<?php echo (int) $pt['id']; ?>"><?php echo dol_escape_htmltag($pt['label']); ?></option>
							<?php } ?>
						</select>
					</div>
					<?php } ?>
					<button type="submit" class="b2cs-btn b2cs-btn--primary b2cs-btn--full"><?php echo $langs->trans('B2CStorePlaceOrder'); ?></button>
				</form>
			</div>
		</div>
	</div>
</div>
