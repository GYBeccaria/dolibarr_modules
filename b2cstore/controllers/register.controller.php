<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2cstore/controllers/register.controller.php
 * \brief   Customer registration controller
 */

/** @var B2CStoreContext $context */
/** @var Translate $langs */
/** @var DoliDB $db */

// If registration is disabled, redirect to login
if (!getDolGlobalInt('B2CSTORE_ENABLE_REGISTRATION', 1)) {
	header('Location: index.php?controller=login');
	exit;
}

$action = GETPOST('action', 'alphanohtml');

if ($action === 'register') {
	$token = GETPOST('token', 'alphanohtml');
	// Honeypot anti-spam: if filled, silently discard
	$honeypot = GETPOST('website', 'alphanohtml');

	if (!$context->verifyToken($token)) {
		$context->addError($langs->trans('B2CStoreErrorInvalidToken'));
	} elseif (!empty($honeypot)) {
		// Bot trap — pretend success
		header('Location: index.php?controller=register&success=1');
		exit;
	} else {
		$data = array(
			'name'       => GETPOST('name', 'alphanohtml'),
			'email'      => GETPOST('email', 'alphanohtml'),
			'phone'      => GETPOST('phone', 'alphanohtml'),
			'address'    => GETPOST('address', 'alphanohtml'),
			'city'       => GETPOST('city', 'alphanohtml'),
			'zip'        => GETPOST('zip', 'alphanohtml'),
			'country_id' => GETPOST('country_id', 'int'),
			'password'   => GETPOST('password', 'none'),
			'password2'  => GETPOST('password2', 'none'),
		);

		// Password match check
		if ($data['password'] !== $data['password2']) {
			$context->addError($langs->trans('B2CStoreErrorPasswordMismatch'));
		} elseif (strlen($data['password']) < 8) {
			$context->addError($langs->trans('B2CStoreErrorPasswordTooShort'));
		} else {
			// Load internal user for Societe::create()
			$user_id = getDolGlobalInt('WEBPORTAL_USER_LOGGED');
			$internalUser = new User($db);
			if ($user_id <= 0 || $internalUser->fetch($user_id) <= 0) {
				$context->addError('B2C Store: WEBPORTAL_USER_LOGGED not configured.');
			} else {
				$internalUser->loadRights();
				$customer = new B2CStoreCustomer($db);
				$acc_id = $customer->register($data, $internalUser);
				if ($acc_id > 0) {
					$requireApproval = getDolGlobalInt('B2CSTORE_REGISTRATION_REQUIRES_APPROVAL', 0);
					if ($requireApproval) {
						$context->addMessage($langs->trans('B2CStoreRegistrationPending'));
						header('Location: index.php?controller=login');
						exit;
					} else {
						// Auto-login
						$context->doLogin($acc_id);
						$context->addMessage($langs->trans('B2CStoreWelcome'));
						header('Location: index.php?controller=home');
						exit;
					}
				} else {
					foreach ($customer->errors as $err) {
						$context->addError($err);
					}
				}
			}
		}
	}
}

$success = GETPOST('success', 'int');
$title   = $langs->trans('B2CStoreRegister').' - '.getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$tplDir  = dirname(__DIR__).'/public/tpl/';

include $tplDir.'header.tpl.php';
include $tplDir.'navbar.tpl.php';
include $tplDir.'register.tpl.php';
include $tplDir.'footer.tpl.php';
