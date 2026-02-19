<?php
/* Copyright (C) 2025 Henaxis */

/**
 * \file    custom/b2border/public/logout.php
 * \brief   Logout for B2B Order Portal
 */

include 'main.inc.php';

/** @var B2BOrderContext $context */
$context->destroySession();

// Restart session to allow login
if (session_status() === PHP_SESSION_NONE) {
	$prefix = dol_getprefix('');
	session_name('B2BORDER_SESSID_'.$prefix);
	session_start();
}

header('Location: index.php?controller=login');
exit;
