<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2BOrderContext $context */
/** @var Translate $langs */
/** @var array $items */
/** @var array $totals */
/** @var Societe $thirdparty */

$baseUrl = dol_buildpath('/custom/b2border/public/index.php', 1);
?>

<h1><?php echo $langs->trans("B2BOrderCheckout"); ?></h1>

<div class="b2b-checkout-layout">
	<div class="b2b-checkout-summary">
		<h2><?php echo $langs->trans("B2BOrderOrderSummary"); ?></h2>

		<div class="b2b-table-responsive">
		<table class="b2b-table">
			<thead>
				<tr>
					<th><?php echo $langs->trans("Ref"); ?></th>
					<th><?php echo $langs->trans("Product"); ?></th>
					<th class="b2b-text-right"><?php echo $langs->trans("B2BOrderUnitPriceHT"); ?></th>
					<th class="b2b-text-center"><?php echo $langs->trans("Qty"); ?></th>
					<th class="b2b-text-right"><?php echo $langs->trans("B2BOrderLineTotalHT"); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				global $mysoc;
				foreach ($items as $fk_product => $item) {
					$tabprice = calcul_price_total(
						$item['qty'],
						($item['price_base_type'] == 'HT' ? $item['pu_ht'] : $item['pu_ttc']),
						0, $item['tva_tx'], -1, -1, 0,
						$item['price_base_type'], 0, $item['product_type'], $mysoc
					);
					$line_total_ht = $tabprice[0];
				?>
				<tr>
					<td><?php echo dol_escape_htmltag($item['ref']); ?></td>
					<td><?php echo dol_escape_htmltag($item['label']); ?></td>
					<td class="b2b-text-right"><?php echo b2border_format_price($item['pu_ht']); ?></td>
					<td class="b2b-text-center"><?php echo (int) $item['qty']; ?></td>
					<td class="b2b-text-right"><?php echo b2border_format_price($line_total_ht); ?></td>
				</tr>
				<?php } ?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="4" class="b2b-text-right"><strong><?php echo $langs->trans("B2BOrderTotalHT"); ?></strong></td>
					<td class="b2b-text-right"><strong><?php echo b2border_format_price($totals['total_ht']); ?></strong></td>
				</tr>
				<tr>
					<td colspan="4" class="b2b-text-right"><?php echo $langs->trans("B2BOrderVAT"); ?></td>
					<td class="b2b-text-right"><?php echo b2border_format_price($totals['total_vat']); ?></td>
				</tr>
				<tr class="b2b-cart-grand-total">
					<td colspan="4" class="b2b-text-right"><strong><?php echo $langs->trans("B2BOrderTotalTTC"); ?></strong></td>
					<td class="b2b-text-right"><strong><?php echo b2border_format_price($totals['total_ttc']); ?></strong></td>
				</tr>
			</tfoot>
		</table>
		</div>
	</div>

	<div class="b2b-checkout-details">
		<h2><?php echo $langs->trans("B2BOrderDeliveryInfo"); ?></h2>

		<div class="b2b-delivery-address">
			<p><strong><?php echo dol_escape_htmltag($thirdparty->name); ?></strong></p>
			<?php if ($thirdparty->address) { ?>
				<p><?php echo dol_nl2br(dol_escape_htmltag($thirdparty->address)); ?></p>
			<?php } ?>
			<?php if ($thirdparty->zip || $thirdparty->town) { ?>
				<p><?php echo dol_escape_htmltag(trim($thirdparty->zip.' '.$thirdparty->town)); ?></p>
			<?php } ?>
			<?php if ($thirdparty->country) { ?>
				<p><?php echo dol_escape_htmltag($thirdparty->country); ?></p>
			<?php } ?>
		</div>

		<form method="POST" action="<?php echo $baseUrl; ?>?controller=checkout" class="b2b-checkout-form">
			<input type="hidden" name="action" value="createorder">
			<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">

			<div class="b2b-form-group">
				<label for="ref_client"><?php echo $langs->trans("B2BOrderRefClient"); ?></label>
				<input type="text" id="ref_client" name="ref_client" class="b2b-input"
					value="<?php echo dol_escape_htmltag(GETPOST('ref_client', 'alphanohtml')); ?>"
					placeholder="<?php echo dol_escape_htmltag($langs->trans("B2BOrderRefClientPlaceholder")); ?>">
			</div>

			<div class="b2b-form-group">
				<label for="note_public"><?php echo $langs->trans("B2BOrderNotePublic"); ?></label>
				<textarea id="note_public" name="note_public" class="b2b-input b2b-textarea" rows="3"
					placeholder="<?php echo dol_escape_htmltag($langs->trans("B2BOrderNotePlaceholder")); ?>"><?php echo dol_escape_htmltag(GETPOST('note_public', 'restricthtml')); ?></textarea>
			</div>

			<div class="b2b-checkout-actions">
				<a href="<?php echo $baseUrl; ?>?controller=cart" class="b2b-btn b2b-btn-secondary"><?php echo $langs->trans("B2BOrderBackToCart"); ?></a>
				<button type="submit" class="b2b-btn b2b-btn-primary" onclick="return confirm('<?php echo dol_escape_js($langs->trans("B2BOrderConfirmSubmit")); ?>');">
					<?php echo $langs->trans("B2BOrderSubmitOrder"); ?>
				</button>
			</div>
		</form>
	</div>
</div>
