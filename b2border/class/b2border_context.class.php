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
 * B2B Order Portal Context - Singleton
 * Handles authentication, session, and logged-in user/thirdparty context
 */
class B2BOrderContext
{
	/** @var B2BOrderContext Singleton instance */
	private static $instance;

	/** @var DoliDB Database handler */
	public $db;

	/** @var User Dolibarr internal user for backend operations */
	public $logged_user;

	/** @var Societe Customer thirdparty object */
	public $logged_thirdparty;

	/** @var SocieteAccount Portal account */
	public $logged_account;

	/** @var string Current controller */
	public $controller = 'login';

	/** @var array Error messages */
	public $errors = array();

	/** @var array Success messages */
	public $messages = array();

	/**
	 * Get singleton instance
	 *
	 * @return B2BOrderContext
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
	 * Set the database handler
	 *
	 * @param	DoliDB	$db		Database handler
	 * @return	void
	 */
	public function setDb($db)
	{
		$this->db = $db;
	}

	/**
	 * Check if user is authenticated
	 *
	 * @return bool
	 */
	public function isAuthenticated()
	{
		return !empty($_SESSION['b2border_account_id']) && $_SESSION['b2border_account_id'] > 0;
	}

	/**
	 * Try to find the third-party account from login credentials
	 * Replicates webportal context.class.php:662-720
	 *
	 * @param	string	$login		Login
	 * @param	string	$pass		Password
	 * @return	int					Account ID on success, <0 on error
	 */
	public function getThirdPartyAccountFromLogin($login, $pass)
	{
		$id = 0;

		$sql = "SELECT sa.rowid as id, sa.pass_crypted";
		$sql .= " FROM ".$this->db->prefix()."societe_account as sa";
		$sql .= " WHERE BINARY sa.login = '".$this->db->escape($login)."'";
		$sql .= " AND sa.site = 'dolibarr_portal'";
		$sql .= " AND sa.status = 1";
		$sql .= " AND sa.entity IN (".getEntity('societe').")";

		dol_syslog("B2BOrderContext::getThirdPartyAccountFromLogin try login='".$login."'", LOG_DEBUG);
		$result = $this->db->query($sql);
		if ($result) {
			if ($this->db->num_rows($result) == 1) {
				$passok = false;
				$obj = $this->db->fetch_object($result);
				if ($obj) {
					$passcrypted = $obj->pass_crypted;

					$cryptType = '';
					if (getDolGlobalString('DATABASE_PWD_ENCRYPTED')) {
						$cryptType = getDolGlobalString('DATABASE_PWD_ENCRYPTED');
					}
					if (!in_array($cryptType, array('auto'))) {
						$cryptType = 'auto';
					}

					if ($cryptType == 'auto') {
						if ($passcrypted && dol_verifyHash($pass, $passcrypted, '0')) {
							$passok = true;
						}
					}

					if ($passok) {
						$id = $obj->id;
					} else {
						dol_syslog("B2BOrderContext::getThirdPartyAccountFromLogin auth KO for ".$login, LOG_NOTICE);
						sleep(1);
						return -3;
					}
				}
			} else {
				dol_syslog("B2BOrderContext::getThirdPartyAccountFromLogin multiple accounts for ".$login, LOG_ERR);
				sleep(1);
				return -2;
			}
		} else {
			dol_syslog("B2BOrderContext::getThirdPartyAccountFromLogin DB error: ".$this->db->lasterror(), LOG_ERR);
			return -1;
		}

		if (empty($id)) {
			sleep(1);
			return -4;
		}

		return $id;
	}

	/**
	 * Load session context after authentication
	 * Loads the internal Dolibarr user, account, and thirdparty objects
	 *
	 * @return	int		1 on success, <0 on error
	 */
	public function loadSessionContext()
	{
		global $conf, $langs;

		$account_id = $_SESSION['b2border_account_id'];

		// Load portal account
		$websiteaccount = new SocieteAccount($this->db);
		$result = $websiteaccount->fetch($account_id);
		if ($result <= 0) {
			dol_syslog("B2BOrderContext::loadSessionContext cannot load account ID=".$account_id, LOG_WARNING);
			$this->destroySession();
			return -1;
		}
		$this->logged_account = $websiteaccount;

		// Load internal Dolibarr user from WEBPORTAL_USER_LOGGED
		$user_id = getDolGlobalInt('WEBPORTAL_USER_LOGGED');
		if ($user_id <= 0) {
			$this->addError($langs->trans("B2BOrderSetupNotComplete"));
			return -2;
		}

		$logged_user = new User($this->db);
		$result = $logged_user->fetch($user_id);
		if ($result <= 0) {
			$this->addError("Cannot load internal user ID=".$user_id);
			return -3;
		}
		$logged_user->loadRights();
		$this->logged_user = $logged_user;

		// Load thirdparty
		$result = $websiteaccount->fetch_thirdparty();
		if ($result < 0 || !is_object($websiteaccount->thirdparty) || $websiteaccount->thirdparty->id <= 0) {
			$this->addError("Cannot load customer for account ID=".$account_id);
			return -4;
		}
		$this->logged_thirdparty = $websiteaccount->thirdparty;

		return 1;
	}

	/**
	 * Perform login
	 *
	 * @param	int		$account_id		Account ID from successful auth
	 * @return	void
	 */
	public function doLogin($account_id)
	{
		session_regenerate_id(true);
		$_SESSION['b2border_account_id'] = (int) $account_id;
		$_SESSION['b2border_cart'] = array('items' => array());
	}

	/**
	 * Destroy session
	 *
	 * @return	void
	 */
	public function destroySession()
	{
		$_SESSION['b2border_account_id'] = 0;
		$_SESSION['b2border_cart'] = array('items' => array());
		session_destroy();
	}

	/**
	 * Add error message
	 *
	 * @param	string	$msg	Error message
	 * @return	void
	 */
	public function addError($msg)
	{
		$this->errors[] = $msg;
	}

	/**
	 * Add success message
	 *
	 * @param	string	$msg	Success message
	 * @return	void
	 */
	public function addMessage($msg)
	{
		$this->messages[] = $msg;
	}

	/**
	 * Get CSRF token
	 *
	 * @return	string
	 */
	public function getToken()
	{
		if (empty($_SESSION['b2border_token'])) {
			$_SESSION['b2border_token'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['b2border_token'];
	}

	/**
	 * Verify CSRF token
	 *
	 * @param	string	$token	Token from form
	 * @return	bool
	 */
	public function verifyToken($token)
	{
		return !empty($token) && !empty($_SESSION['b2border_token']) && hash_equals($_SESSION['b2border_token'], $token);
	}

	/**
	 * Get the price level for the logged thirdparty.
	 * Uses thirdparty's own price_level if set, otherwise falls back to
	 * B2BORDER_DEFAULT_PRICE_LEVEL portal setting, then to 1.
	 *
	 * @return	int		Price level
	 */
	public function getPriceLevel()
	{
		if ($this->logged_thirdparty && $this->logged_thirdparty->price_level > 0) {
			return (int) $this->logged_thirdparty->price_level;
		}
		$defaultLevel = getDolGlobalInt('B2BORDER_DEFAULT_PRICE_LEVEL', 1);
		return max(1, $defaultLevel);
	}
}
