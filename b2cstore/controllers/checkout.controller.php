<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2cstore/controllers/checkout.controller.php
 * \brief   Order preview and creation controller
 */

/** @var B2CStoreContext $context */
/** @var Conf $conf */
/** @var Translate $langs */
/** @var DoliDB $db */

$price_level = $context->getPriceLevel();
$cart = new B2CStoreCart($db);

if ($cart->isEmpty()) {
	header('Location: index.php?controller=cart');
	exit;
}

$action = GETPOST('action', 'alphanohtml');

if ($action === 'createorder') {
	$token = GETPOST('token', 'alphanohtml');
	if (!$context->verifyToken($token)) {
		$context->addError($langs->trans('B2CStoreErrorInvalidToken'));
	} else {
		$note_public = GETPOST('note_public', 'restricthtml');
		$ref_client  = GETPOST('ref_client', 'alphanohtml');

		$error = 0;
		$items = $cart->getItems($price_level);

		if (empty($items)) {
			$context->addError($langs->trans('B2CStoreCartEmpty'));
			$error++;
		}

		if (!$error) {
			$db->begin();

			$order = new Commande($db);
			$order->socid           = $context->logged_thirdparty->id;
			$order->date_commande   = dol_now();
			$order->date            = dol_now();
			$order->note_public     = $note_public;
			$order->ref_client      = $ref_client;
			$order->module_source   = 'b2cstore';
			$order->cond_reglement_id  = $context->logged_thirdparty->cond_reglement_id;
			$order->mode_reglement_id  = $context->logged_thirdparty->mode_reglement_id;
			$order->fk_delivery_address = $context->logged_thirdparty->fk_delivery_address;
			$order->multicurrency_code  = $conf->currency;

			$user = $context->logged_user;
			$order_id = $order->create($user);

			if ($order_id < 0) {
				$error++;
				$context->addError($langs->trans('B2CStoreErrorCreatingOrder').': '.$order->error);
				dol_syslog('B2CStore checkout: order create failed: '.$order->error, LOG_ERR);
			}

			if (!$error) {
				foreach ($items as $item) {
					$result = $order->addline(
						$item['label'],
						(float) $item['pu_ht'],
						(int) $item['qty'],
						(float) $item['tva_tx'],
						0, 0,
						(int) $item['fk_product'],
						0, 0, 0,
						$item['price_base_type'],
						(float) $item['pu_ttc'],
						'', '',
						(int) $item['product_type']
					);
					if ($result < 0) {
						$error++;
						$context->addError($langs->trans('B2CStoreErrorAddingLine', $item['ref']).': '.$order->error);
						dol_syslog('B2CStore checkout: addline failed for product '.$item['fk_product'].': '.$order->error, LOG_ERR);
						break;
					}
				}
			}

			if (!$error) {
				$db->commit();
				$cart->clear();
				header('Location: index.php?controller=confirmation&id='.$order_id);
				exit;
			} else {
				$db->rollback();
			}
		}
	}
}

$items     = $cart->getItems($price_level);
$totals    = $cart->getTotals($price_level);
$thirdparty = $context->logged_thirdparty;

$title  = $langs->trans('B2CStoreCheckout').' - '.getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'navbar.tpl.php';
include $tplDir.'checkout.tpl.php';
include $tplDir.'footer.tpl.php';
