<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2BOrderContext $context */
/** @var Translate $langs */
/** @var array $items */
/** @var array $totals */

$baseUrl = dol_buildpath('/custom/b2border/public/index.php', 1);
?>

<h1><?php echo $langs->trans("B2BOrderCart"); ?></h1>

<?php if (empty($items)) { ?>
	<div class="b2b-empty-state">
		<p><?php echo $langs->trans("B2BOrderCartEmpty"); ?></p>
		<a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2b-btn b2b-btn-primary"><?php echo $langs->trans("B2BOrderContinueShopping"); ?></a>
	</div>
<?php } else { ?>

<form method="POST" action="<?php echo $baseUrl; ?>?controller=cart" class="b2b-cart-form">
	<input type="hidden" name="action" value="updateall">
	<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">

	<div class="b2b-table-responsive">
	<table class="b2b-table b2b-cart-table">
		<thead>
			<tr>
				<th><?php echo $langs->trans("Ref"); ?></th>
				<th><?php echo $langs->trans("Product"); ?></th>
				<th class="b2b-text-right"><?php echo $langs->trans("B2BOrderUnitPriceHT"); ?></th>
				<th class="b2b-text-center"><?php echo $langs->trans("Qty"); ?></th>
				<th class="b2b-text-right"><?php echo $langs->trans("B2BOrderLineTotalHT"); ?></th>
				<th class="b2b-text-center"><?php echo $langs->trans("Action"); ?></th>
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
				<td class="b2b-cart-ref">
					<a href="<?php echo $baseUrl; ?>?controller=product&id=<?php echo (int) $fk_product; ?>">
						<?php echo dol_escape_htmltag($item['ref']); ?>
					</a>
				</td>
				<td class="b2b-cart-label"><?php echo dol_escape_htmltag($item['label']); ?></td>
				<td class="b2b-text-right"><?php echo b2border_format_price($item['pu_ht']); ?></td>
				<td class="b2b-text-center">
					<input type="number" name="qtys[<?php echo (int) $fk_product; ?>]" value="<?php echo (int) $item['qty']; ?>" min="1" class="b2b-input b2b-input-qty-sm">
				</td>
				<td class="b2b-text-right b2b-cart-line-total"><?php echo b2border_format_price($line_total_ht); ?></td>
				<td class="b2b-text-center">
					<a href="<?php echo $baseUrl; ?>?controller=cart&action=remove&fk_product=<?php echo (int) $fk_product; ?>&token=<?php echo $context->getToken(); ?>" class="b2b-btn b2b-btn-danger b2b-btn-xs" onclick="return confirm('<?php echo dol_escape_js($langs->trans("B2BOrderConfirmRemove")); ?>');">&times;</a>
				</td>
			</tr>
			<?php } ?>
		</tbody>
		<tfoot>
			<tr class="b2b-cart-totals">
				<td colspan="4" class="b2b-text-right"><strong><?php echo $langs->trans("B2BOrderTotalHT"); ?></strong></td>
				<td class="b2b-text-right"><strong><?php echo b2border_format_price($totals['total_ht']); ?></strong></td>
				<td></td>
			</tr>
			<tr class="b2b-cart-totals">
				<td colspan="4" class="b2b-text-right"><?php echo $langs->trans("B2BOrderVAT"); ?></td>
				<td class="b2b-text-right"><?php echo b2border_format_price($totals['total_vat']); ?></td>
				<td></td>
			</tr>
			<tr class="b2b-cart-totals b2b-cart-grand-total">
				<td colspan="4" class="b2b-text-right"><strong><?php echo $langs->trans("B2BOrderTotalTTC"); ?></strong></td>
				<td class="b2b-text-right"><strong><?php echo b2border_format_price($totals['total_ttc']); ?></strong></td>
				<td></td>
			</tr>
		</tfoot>
	</table>
	</div>

	<div class="b2b-cart-actions">
		<div class="b2b-cart-actions-left">
			<a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2b-btn b2b-btn-secondary"><?php echo $langs->trans("B2BOrderContinueShopping"); ?></a>
			<a href="<?php echo $baseUrl; ?>?controller=cart&action=clear&token=<?php echo $context->getToken(); ?>" class="b2b-btn b2b-btn-outline" onclick="return confirm('<?php echo dol_escape_js($langs->trans("B2BOrderConfirmClear")); ?>');"><?php echo $langs->trans("B2BOrderClearCart"); ?></a>
		</div>
		<div class="b2b-cart-actions-right">
			<button type="submit" class="b2b-btn b2b-btn-secondary"><?php echo $langs->trans("B2BOrderUpdateCart"); ?></button>
			<a href="<?php echo $baseUrl; ?>?controller=checkout" class="b2b-btn b2b-btn-primary"><?php echo $langs->trans("B2BOrderProceedCheckout"); ?></a>
		</div>
	</div>
</form>

<?php } ?>
