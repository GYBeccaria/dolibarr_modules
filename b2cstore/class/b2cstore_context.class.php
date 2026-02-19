<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';

/**
 * B2C Store Context - Singleton
 * Handles authentication, session state, CSRF, and user context.
 * Supports guest browsing when B2CSTORE_ALLOW_GUEST_BROWSING is enabled.
 */
class B2CStoreContext
{
	/** @var B2CStoreContext Singleton instance */
	private static $instance;

	/** @var DoliDB Database handler */
	public $db;

	/** @var User Dolibarr internal user for backend operations */
	public $logged_user;

	/** @var Societe Customer thirdparty object */
	public $logged_thirdparty;

	/** @var SocieteAccount Portal account */
	public $logged_account;

	/** @var array Error messages */
	public $errors = array();

	/** @var array Success messages */
	public $messages = array();

	/**
	 * Get singleton instance.
	 *
	 * @return B2CStoreContext
	 */
	public static function getInstance()
	{
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
	}

	/**
	 * Set the database handler.
	 *
	 * @param DoliDB $db Database handler
	 * @return void
	 */
	public function setDb($db)
	{
		$this->db = $db;
	}

	/**
	 * Check if the current visitor is authenticated.
	 *
	 * @return bool
	 */
	public function isAuthenticated()
	{
		return !empty($_SESSION['b2cstore_account_id']) && (int) $_SESSION['b2cstore_account_id'] > 0;
	}

	/**
	 * Check if guest browsing is allowed (catalog visible without login).
	 *
	 * @return bool
	 */
	public function isGuestAllowed()
	{
		return (bool) getDolGlobalInt('B2CSTORE_ALLOW_GUEST_BROWSING', 1);
	}

	/**
	 * Authenticate visitor against llx_societe_account (site='b2cstore_portal').
	 *
	 * @param string $login    Login (email or username)
	 * @param string $pass     Plain-text password
	 * @return int             Account rowid on success, <0 on failure
	 */
	public function authenticate($login, $pass)
	{
		$sql = "SELECT sa.rowid, sa.pass_crypted, sa.status";
		$sql .= " FROM ".$this->db->prefix()."societe_account sa";
		$sql .= " WHERE BINARY sa.login = '".$this->db->escape($login)."'";
		$sql .= " AND sa.site = 'b2cstore_portal'";
		$sql .= " AND sa.entity IN (".getEntity('societe').")";

		dol_syslog("B2CStoreContext::authenticate login='".$login."'", LOG_DEBUG);
		$res = $this->db->query($sql);
		if (!$res) {
			return -1;
		}

		if ($this->db->num_rows($res) != 1) {
			sleep(1);
			return -4; // not found or duplicate
		}

		$obj = $this->db->fetch_object($res);

		// Verify password (bcrypt via password_verify, or Dolibarr hash)
		$passok = false;
		if (password_verify($pass, $obj->pass_crypted)) {
			$passok = true;
		} elseif (dol_verifyHash($pass, $obj->pass_crypted, '0')) {
			$passok = true;
		}

		if (!$passok) {
			dol_syslog("B2CStoreContext::authenticate wrong password for ".$login, LOG_NOTICE);
			sleep(1);
			return -3;
		}

		// Check account status
		if ((int) $obj->status === 0) {
			return -2; // account pending approval
		}

		return (int) $obj->rowid;
	}

	/**
	 * Load session context after authentication.
	 * Loads internal user, portal account, and thirdparty.
	 *
	 * @return int 1 on success, <0 on error
	 */
	public function loadSessionContext()
	{
		global $conf, $langs;

		$account_id = (int) $_SESSION['b2cstore_account_id'];

		// Load portal account
		$account = new SocieteAccount($this->db);
		if ($account->fetch($account_id) <= 0) {
			$this->destroySession();
			return -1;
		}
		$this->logged_account = $account;

		// Load internal Dolibarr user
		$user_id = getDolGlobalInt('WEBPORTAL_USER_LOGGED');
		if ($user_id <= 0) {
			$this->addError('B2C Store: WEBPORTAL_USER_LOGGED not configured.');
			return -2;
		}
		$internalUser = new User($this->db);
		if ($internalUser->fetch($user_id) <= 0) {
			$this->addError('B2C Store: cannot load internal user ID='.$user_id);
			return -3;
		}
		$internalUser->loadRights();
		$this->logged_user = $internalUser;

		// Load thirdparty
		if ($account->fetch_thirdparty() < 0 || !is_object($account->thirdparty) || $account->thirdparty->id <= 0) {
			$this->addError('B2C Store: cannot load thirdparty for account '.$account_id);
			return -4;
		}
		$this->logged_thirdparty = $account->thirdparty;

		return 1;
	}

	/**
	 * Perform login: regenerate session ID and store account reference.
	 *
	 * @param int $account_id Account rowid
	 * @return void
	 */
	public function doLogin($account_id)
	{
		session_regenerate_id(true);
		$_SESSION['b2cstore_account_id'] = (int) $account_id;
		$_SESSION['b2cstore_cart'] = array('items' => array());
	}

	/**
	 * Destroy session (logout).
	 *
	 * @return void
	 */
	public function destroySession()
	{
		$_SESSION['b2cstore_account_id'] = 0;
		$_SESSION['b2cstore_cart'] = array('items' => array());
		session_destroy();
	}

	/**
	 * Add error message.
	 *
	 * @param string $msg
	 * @return void
	 */
	public function addError($msg)
	{
		$this->errors[] = $msg;
	}

	/**
	 * Add success/info message.
	 *
	 * @param string $msg
	 * @return void
	 */
	public function addMessage($msg)
	{
		$this->messages[] = $msg;
	}

	/**
	 * Get CSRF token (generated once per session).
	 *
	 * @return string
	 */
	public function getToken()
	{
		if (empty($_SESSION['b2cstore_token'])) {
			$_SESSION['b2cstore_token'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['b2cstore_token'];
	}

	/**
	 * Verify CSRF token from form submission.
	 *
	 * @param string $token Token from POST
	 * @return bool
	 */
	public function verifyToken($token)
	{
		return !empty($token)
			&& !empty($_SESSION['b2cstore_token'])
			&& hash_equals($_SESSION['b2cstore_token'], $token);
	}

	/**
	 * Get price level for current customer.
	 * Uses thirdparty price_level if set, otherwise B2CSTORE_DEFAULT_PRICE_LEVEL, then 1.
	 *
	 * @return int Price level (1-6)
	 */
	public function getPriceLevel()
	{
		if ($this->logged_thirdparty && (int) $this->logged_thirdparty->price_level > 0) {
			return (int) $this->logged_thirdparty->price_level;
		}
		return max(1, getDolGlobalInt('B2CSTORE_DEFAULT_PRICE_LEVEL', 1));
	}

	/**
	 * Check if prices should be hidden for guests.
	 *
	 * @return bool
	 */
	public function arePricesHidden()
	{
		return getDolGlobalInt('B2CSTORE_REQUIRE_LOGIN_FOR_PRICES', 0) && !$this->isAuthenticated();
	}

	/**
	 * Check if cart requires login and current visitor is guest.
	 *
	 * @return bool
	 */
	public function cartRequiresLogin()
	{
		return getDolGlobalInt('B2CSTORE_REQUIRE_LOGIN_FOR_CART', 1) && !$this->isAuthenticated();
	}
}
