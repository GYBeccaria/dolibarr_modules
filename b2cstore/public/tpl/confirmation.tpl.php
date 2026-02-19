<?php /* Copyright (C) 2025 Henaxis */
$baseUrl = dol_buildpath('/custom/b2cstore/public/index.php', 1);
?>
<div class="b2cs-page b2cs-page--confirmation">
	<div class="container">
		<div class="b2cs-confirmation">
			<div class="b2cs-confirmation__icon">✓</div>
			<h1 class="b2cs-confirmation__title"><?php echo $langs->trans('B2CStoreOrderConfirmed'); ?></h1>
			<p class="b2cs-confirmation__ref"><?php echo $langs->trans('B2CStoreOrderRef'); ?>: <strong><?php echo dol_escape_htmltag($order->ref); ?></strong></p>
			<p class="b2cs-confirmation__msg"><?php echo $langs->trans('B2CStoreOrderConfirmedMsg'); ?></p>

			<?php if (!empty($order->lines)) { ?>
			<div class="b2cs-confirmation__items">
				<h2><?php echo $langs->trans('B2CStoreOrderSummary'); ?></h2>
				<table class="b2cs-table">
					<thead>
						<tr>
							<th><?php echo $langs->trans('B2CStoreProduct'); ?></th>
							<th><?php echo $langs->trans('B2CStoreQty'); ?></th>
							<th><?php echo $langs->trans('B2CStorePriceHT'); ?></th>
							<th><?php echo $langs->trans('B2CStoreTotal'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($order->lines as $line) { ?>
						<tr>
							<td><?php echo dol_escape_htmltag($line->product_label ?: $line->desc); ?></td>
							<td><?php echo price($line->qty); ?></td>
							<td><?php echo price($line->subprice); ?></td>
							<td><?php echo price($line->total_ht); ?></td>
						</tr>
						<?php } ?>
					</tbody>
					<tfoot>
						<tr>
							<td colspan="3"><?php echo $langs->trans('B2CStoreSubtotalHT'); ?></td>
							<td><?php echo price($order->total_ht); ?></td>
						</tr>
						<tr>
							<td colspan="3"><?php echo $langs->trans('B2CStoreVat'); ?></td>
							<td><?php echo price($order->total_tva); ?></td>
						</tr>
						<tr class="b2cs-table__foot-total">
							<td colspan="3"><?php echo $langs->trans('B2CStoreTotalTTC'); ?></td>
							<td><?php echo price($order->total_ttc); ?></td>
						</tr>
					</tfoot>
				</table>
			</div>
			<?php } ?>

			<div class="b2cs-confirmation__actions">
				<a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2cs-btn b2cs-btn--primary"><?php echo $langs->trans('B2CStoreContinueShopping'); ?></a>
				<a href="<?php echo $baseUrl; ?>?controller=home" class="b2cs-btn b2cs-btn--ghost"><?php echo $langs->trans('B2CStoreBackHome'); ?></a>
			</div>
		</div>
	</div>
</div>
