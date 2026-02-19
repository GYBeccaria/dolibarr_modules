<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2BOrderContext $context */
/** @var Translate $langs */
/** @var Commande $order */

$baseUrl = dol_buildpath('/custom/b2border/public/index.php', 1);
?>

<div class="b2b-confirmation">
	<div class="b2b-confirmation-icon">&#10003;</div>
	<h1><?php echo $langs->trans("B2BOrderConfirmationTitle"); ?></h1>
	<p class="b2b-confirmation-message"><?php echo $langs->trans("B2BOrderConfirmationMessage"); ?></p>

	<div class="b2b-confirmation-details">
		<table class="b2b-table b2b-confirmation-table">
			<tr>
				<td><strong><?php echo $langs->trans("Ref"); ?></strong></td>
				<td><?php echo dol_escape_htmltag($order->ref); ?></td>
			</tr>
			<tr>
				<td><strong><?php echo $langs->trans("Date"); ?></strong></td>
				<td><?php echo dol_print_date($order->date_commande, 'day'); ?></td>
			</tr>
			<tr>
				<td><strong><?php echo $langs->trans("Status"); ?></strong></td>
				<td><?php echo $langs->trans("B2BOrderStatusDraft"); ?></td>
			</tr>
			<?php if ($order->ref_client) { ?>
			<tr>
				<td><strong><?php echo $langs->trans("B2BOrderRefClient"); ?></strong></td>
				<td><?php echo dol_escape_htmltag($order->ref_client); ?></td>
			</tr>
			<?php } ?>
			<tr>
				<td><strong><?php echo $langs->trans("B2BOrderTotalHT"); ?></strong></td>
				<td><?php echo b2border_format_price($order->total_ht); ?></td>
			</tr>
			<tr>
				<td><strong><?php echo $langs->trans("B2BOrderTotalTTC"); ?></strong></td>
				<td><?php echo b2border_format_price($order->total_ttc); ?></td>
			</tr>
		</table>
	</div>

	<p class="b2b-confirmation-note"><?php echo $langs->trans("B2BOrderConfirmationNote"); ?></p>

	<div class="b2b-confirmation-actions">
		<a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2b-btn b2b-btn-primary"><?php echo $langs->trans("B2BOrderContinueShopping"); ?></a>
	</div>
</div>
