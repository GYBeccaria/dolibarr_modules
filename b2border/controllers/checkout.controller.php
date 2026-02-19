<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2border/controllers/checkout.controller.php
 * \brief   Checkout controller - review order and create Commande
 */

/** @var B2BOrderContext $context */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var DoliDB $db */
/** @var Societe $mysoc */

$cart = new B2BOrderCart($db);
$price_level = $context->getPriceLevel();

// Redirect to cart if empty
if ($cart->isEmpty()) {
	header('Location: index.php?controller=cart');
	exit;
}

/*
 * Action - Create order
 */
$action = GETPOST('action', 'alphanohtml');

if ($action == 'createorder') {
	$token = GETPOST('token', 'alphanohtml');
	if (!$context->verifyToken($token)) {
		$context->addError($langs->trans("B2BOrderErrorInvalidToken"));
	} else {
		$note_public = GETPOST('note_public', 'restricthtml');
		$ref_client = GETPOST('ref_client', 'alphanohtml');

		$error = 0;
		$items = $cart->getItems($price_level);

		if (empty($items)) {
			$context->addError($langs->trans("B2BOrderCartEmpty"));
			$error++;
		}

		if (!$error) {
			$db->begin();

			// Create order as DRAFT
			$order = new Commande($db);
			$order->socid = $context->logged_thirdparty->id;
			$order->date_commande = dol_now();
			$order->date = dol_now();
			$order->note_public = $note_public;
			$order->ref_client = $ref_client;
			$order->module_source = 'b2border';
			$order->cond_reglement_id = $context->logged_thirdparty->cond_reglement_id;
			$order->mode_reglement_id = $context->logged_thirdparty->mode_reglement_id;
			$order->fk_delivery_address = $context->logged_thirdparty->fk_delivery_address;
			$order->multicurrency_code = $conf->currency;

			$user = $context->logged_user;
			$order_id = $order->create($user);

			if ($order_id < 0) {
				$error++;
				$context->addError($langs->trans("B2BOrderErrorCreatingOrder").': '.$order->error);
				dol_syslog("B2BOrder checkout: order create failed: ".$order->error, LOG_ERR);
			}

			if (!$error) {
				// Add lines
				foreach ($items as $item) {
					$desc = $item['label'];
					$pu_ht = (float) $item['pu_ht'];
					$pu_ttc = (float) $item['pu_ttc'];
					$qty = (int) $item['qty'];
					$tva_tx = (float) $item['tva_tx'];
					$fk_product = (int) $item['fk_product'];
					$price_base_type = $item['price_base_type'];
					$product_type = (int) $item['product_type'];

					$result = $order->addline(
						$desc,           // desc
						$pu_ht,          // pu_ht
						$qty,            // qty
						$tva_tx,         // txtva
						0,               // txlocaltax1
						0,               // txlocaltax2
						$fk_product,     // fk_product
						0,               // remise_percent
						0,               // info_bits
						0,               // fk_remise_except
						$price_base_type,// price_base_type
						$pu_ttc,         // pu_ttc
						'',              // date_start
						'',              // date_end
						$product_type    // type (0=product, 1=service)
					);

					if ($result < 0) {
						$error++;
						$context->addError($langs->trans("B2BOrderErrorAddingLine", $item['ref']).': '.$order->error);
						dol_syslog("B2BOrder checkout: addline failed for product ".$fk_product.": ".$order->error, LOG_ERR);
						break;
					}
				}
			}

			if (!$error) {
				$db->commit();
				// Clear cart
				$cart->clear();
				// Redirect to confirmation
				header('Location: index.php?controller=confirmation&id='.$order_id);
				exit;
			} else {
				$db->rollback();
			}
		}
	}
}

/*
 * Fetch data for display
 */
$items = $cart->getItems($price_level);
$totals = $cart->getTotals($price_level);
$thirdparty = $context->logged_thirdparty;

/*
 * View
 */
$title = $langs->trans("B2BOrderCheckout").' - '.getDolGlobalString('B2BORDER_PORTAL_TITLE', 'B2B Order Portal');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'menu.tpl.php';
include $tplDir.'checkout.tpl.php';
include $tplDir.'footer.tpl.php';
