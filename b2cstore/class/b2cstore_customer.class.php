<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societeaccount.class.php';

/**
 * B2C Store - Customer registration and management.
 * Manages creation of llx_societe + llx_societe_account for B2C portal.
 */
class B2CStoreCustomer
{
	/** @var DoliDB */
	private $db;

	/** @var array Errors */
	public $errors = array();

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Register a new B2C customer.
	 * Creates a Societe (thirdparty) and a SocieteAccount with site='b2cstore_portal'.
	 *
	 * @param array $data     Registration data: name, email, phone, address, city, zip, country_id, password
	 * @param User  $internalUser Internal Dolibarr user for object creation
	 * @return int            Account rowid on success, <0 on error
	 */
	public function register($data, $internalUser)
	{
		// Validate required fields
		$requiredFields = getDolGlobalString('B2CSTORE_REGISTRATION_FIELDS', 'name,email,phone,address');
		$fields = array_map('trim', explode(',', $requiredFields));

		foreach ($fields as $field) {
			if (empty($data[$field])) {
				$this->errors[] = 'Field "'.$field.'" is required.';
				return -10;
			}
		}

		// Validate email format
		if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			$this->errors[] = 'Invalid email address.';
			return -11;
		}

		// Check email uniqueness
		if ($this->emailExists($data['email'])) {
			$this->errors[] = 'An account with this email already exists.';
			return -12;
		}

		// Rate limiting: max 3 registrations per IP per hour
		if (!$this->checkRateLimit()) {
			$this->errors[] = 'Too many registration attempts. Please try again later.';
			return -13;
		}

		// Determine account status
		$requireApproval = getDolGlobalInt('B2CSTORE_REGISTRATION_REQUIRES_APPROVAL', 0);
		$accountStatus = $requireApproval ? 0 : 1;

		$this->db->begin();

		// 1. Create thirdparty (Societe)
		$soc = new Societe($this->db);
		$soc->name        = $this->db->escape($data['name']);
		$soc->email       = $this->db->escape($data['email']);
		$soc->phone       = isset($data['phone']) ? $this->db->escape($data['phone']) : '';
		$soc->address     = isset($data['address']) ? $this->db->escape($data['address']) : '';
		$soc->town        = isset($data['city']) ? $this->db->escape($data['city']) : '';
		$soc->zip         = isset($data['zip']) ? $this->db->escape($data['zip']) : '';
		$soc->country_id  = !empty($data['country_id']) ? (int) $data['country_id'] : 0;
		$soc->typent_id   = getDolGlobalInt('B2CSTORE_CUSTOMER_TYPENT_ID', 8);
		$soc->client      = 1; // mark as customer
		$soc->fournisseur = 0;
		$soc->status      = $accountStatus; // 0=pending, 1=active

		$soc_id = $soc->create($internalUser);
		if ($soc_id <= 0) {
			$this->errors = array_merge($this->errors, $soc->errors);
			$this->db->rollback();
			return -1;
		}

		// Assign default category if configured
		$defaultCategory = getDolGlobalInt('B2CSTORE_DEFAULT_CUSTOMER_CATEGORY', 0);
		if ($defaultCategory > 0) {
			require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
			$cat = new Categorie($this->db);
			if ($cat->fetch($defaultCategory) > 0) {
				$cat->add_type($soc, 'customer');
			}
		}

		// 2. Create portal account
		$account = new SocieteAccount($this->db);
		$account->fk_soc     = $soc_id;
		$account->login      = $this->db->escape($data['email']); // login = email
		$account->pass_crypted = password_hash($data['password'], PASSWORD_BCRYPT);
		$account->pass_encoding = 'bcrypt';
		$account->site       = 'b2cstore_portal';
		$account->status     = $accountStatus;
		$account->entity     = $soc->entity;

		$acc_id = $account->create($internalUser);
		if ($acc_id <= 0) {
			$this->errors = array_merge($this->errors, $account->errors);
			$this->db->rollback();
			return -2;
		}

		// Track IP for rate limiting
		$this->recordRegistrationAttempt();

		$this->db->commit();
		return $acc_id;
	}

	/**
	 * Check if an email is already registered in b2cstore_portal.
	 *
	 * @param string $email
	 * @return bool
	 */
	public function emailExists($email)
	{
		$sql = "SELECT COUNT(*) as n FROM ".$this->db->prefix()."societe_account";
		$sql .= " WHERE login = '".$this->db->escape($email)."'";
		$sql .= " AND site = 'b2cstore_portal'";
		$sql .= " AND entity IN (".getEntity('societe').")";

		$res = $this->db->query($sql);
		if ($res) {
			return (int) $this->db->fetch_object($res)->n > 0;
		}
		return false;
	}

	/**
	 * Check registration rate limit: max 3 per IP per hour.
	 * Uses session-stored counts as a lightweight mechanism.
	 *
	 * @return bool True if registration is allowed
	 */
	private function checkRateLimit()
	{
		if (!isset($_SESSION['b2cstore_reg_attempts'])) {
			$_SESSION['b2cstore_reg_attempts'] = array();
		}

		$now = time();
		$hourAgo = $now - 3600;

		// Remove old entries
		$_SESSION['b2cstore_reg_attempts'] = array_filter(
			$_SESSION['b2cstore_reg_attempts'],
			function ($t) use ($hourAgo) { return $t > $hourAgo; }
		);

		return count($_SESSION['b2cstore_reg_attempts']) < 3;
	}

	/**
	 * Record a registration attempt for rate limiting.
	 *
	 * @return void
	 */
	private function recordRegistrationAttempt()
	{
		if (!isset($_SESSION['b2cstore_reg_attempts'])) {
			$_SESSION['b2cstore_reg_attempts'] = array();
		}
		$_SESSION['b2cstore_reg_attempts'][] = time();
	}
}
