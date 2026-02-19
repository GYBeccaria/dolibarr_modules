<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2cstore/controllers/contact.controller.php
 * \brief   Contact form controller — saves messages to llx_b2cstore_contact
 */

/** @var B2CStoreContext $context */
/** @var Translate $langs */
/** @var DoliDB $db */

$action = GETPOST('action', 'alphanohtml');
$success = false;

if ($action === 'sendmessage') {
	$token    = GETPOST('token', 'alphanohtml');
	$honeypot = GETPOST('company', 'alphanohtml'); // anti-spam honeypot

	if (!$context->verifyToken($token)) {
		$context->addError($langs->trans('B2CStoreErrorInvalidToken'));
	} elseif (!empty($honeypot)) {
		$success = true; // silently discard bot submission
	} else {
		$name    = GETPOST('name', 'alphanohtml');
		$email   = GETPOST('email', 'alphanohtml');
		$phone   = GETPOST('phone', 'alphanohtml');
		$subject = GETPOST('subject', 'alphanohtml');
		$message = GETPOST('message', 'restricthtml');

		if (empty($name) || empty($email) || empty($message)) {
			$context->addError($langs->trans('B2CStoreErrorContactRequired'));
		} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$context->addError($langs->trans('B2CStoreErrorInvalidEmail'));
		} else {
			$fk_soc = $context->isAuthenticated() && $context->logged_thirdparty ? $context->logged_thirdparty->id : 0;
			$ip = filter_var($_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP) ?: '';

			$sql = "INSERT INTO ".$db->prefix()."b2cstore_contact";
			$sql .= " (entity, datec, name, email, phone, subject, message, ip, status, fk_soc)";
			$sql .= " VALUES (";
			$sql .= $db->getEntity('societe').",";
			$sql .= "'".$db->idate(dol_now())."',";
			$sql .= "'".$db->escape($name)."',";
			$sql .= "'".$db->escape($email)."',";
			$sql .= "'".$db->escape($phone)."',";
			$sql .= "'".$db->escape($subject)."',";
			$sql .= "'".$db->escape($message)."',";
			$sql .= "'".$db->escape($ip)."',";
			$sql .= "0,";
			$sql .= (int) $fk_soc;
			$sql .= ")";

			if ($db->query($sql)) {
				$success = true;
				$context->addMessage($langs->trans('B2CStoreContactSent'));
			} else {
				$context->addError($langs->trans('B2CStoreErrorSendingMessage'));
			}
		}
	}
}

$title  = $langs->trans('B2CStoreContact').' - '.getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$tplDir = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'navbar.tpl.php';
include $tplDir.'page_static.tpl.php'; // Reuse static template with contact form inline
include $tplDir.'footer.tpl.php';
