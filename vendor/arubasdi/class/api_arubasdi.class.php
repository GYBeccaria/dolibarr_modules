<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

use Luracast\Restler\RestException;

dol_include_once('/efattita/class/efattita.class.php');



/**
 * \file    htdocs/modulebuilder/template/class/api_arubasdi.class.php
 * \ingroup arubasdi
 * \brief   File for API management of myobject.
 */

/**
 * API class for arubasdi myobject
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class ArubaSDI extends DolibarrApi
{

	/**
	 * Constructor
	 *
	 * @url     GET /
	 *
	 */
	public function __construct()
	{
		global $db, $conf;
		$this->db = $db;
	}


	/**
	 * Create invoice
	 *
	 * @param array $request_data   Request data
	 * @return int  ID of myobject
	 *
	 * @throws RestException
	 *
	 * @url	POST createInvoice/
	 */
	public function createInvoice($request_data = null)
	{
		global $conf, $langs, $user, $mysoc;
		$user = DolibarrApiAccess::$user;
		dol_syslog(get_class($this)."::createInvoice", LOG_DEBUG);
		if (!$user->rights->arubasdi->write) {
			throw new RestException(401);
		}

        $fatturaElettronica = new Electronicfacture($this->db);
		$result = $fatturaElettronica->load_invoice_xml((object)$request_data);
		return $result;
	}

	/**
	 * Create Notification
	 *
	 * @param array $request_data   Request datas
	 * @return int  ID of myobject
	 *
	 * @throws RestException
	 *
	 * @url	POST createNotification/
	 */
	public function createNotification($request_data = null)
	{
		global $conf, $langs, $user, $mysoc;
		$user = DolibarrApiAccess::$user;
		dol_syslog(__METHOD__, LOG_DEBUG);
		if (!DolibarrApiAccess::$user->rights->arubasdi->write) {
			throw new RestException(401);
		}
		// Check mandatory fields
		$result = $this->_validateNotification($request_data);

		foreach ($request_data as $field => $value) {
			$this->myobject->$field = $value;
		}
		if (!$this->myobject->create(DolibarrApiAccess::$user)) {
			throw new RestException(500, "Error creating MyObject", array_merge(array($this->myobject->error), $this->myobject->errors));
		}
		return $this->myobject->id;
	}

	/**
	 * Update Invoice Status
	 *
	 * @param array $request_data   Request datas
	 * @return int  ID of myobject
	 *
	 * @throws RestException
	 *
	 * @url	POST updateInvoiceStatus/
	 */
	public function updateInvoiceStatus($request_data = null)
	{
		if (!DolibarrApiAccess::$user->rights->arubasdi->write) {
			throw new RestException(401);
		}
		// Check mandatory fields
		$result = $this->_validate($request_data);

		foreach ($request_data as $field => $value) {
			$this->myobject->$field = $value;
		}
		if (!$this->myobject->create(DolibarrApiAccess::$user)) {
			throw new RestException(500, "Error creating MyObject", array_merge(array($this->myobject->error), $this->myobject->errors));
		}
		return $this->myobject->id;
	}


	/**
	 * Update Update Usage
	 *
	 * @param array $request_data   Request datas
	 * @return int  ID of myobject
	 *
	 * @throws RestException
	 *
	 * @url	POST updateUsage/
	 */
	public function updateUsage($request_data = null)
	{
		if (!DolibarrApiAccess::$user->rights->arubasdi->write) {
			throw new RestException(401);
		}
		// Check mandatory fields
		$result = $this->_validate($request_data);

		foreach ($request_data as $field => $value) {
			$this->myobject->$field = $value;
		}
		if (!$this->myobject->create(DolibarrApiAccess::$user)) {
			throw new RestException(500, "Error creating MyObject", array_merge(array($this->myobject->error), $this->myobject->errors));
		}
		return $this->myobject->id;
	}

	/**
	 * Validate fields before create or update object
	 *
	 * @param	array		$data   Array of data to validate
	 * @return	array
	 *
	 * @throws	RestException
	 */
	private function _validateInvoice($data)
	{
		$object = array();
		foreach ($this->myobject->fields as $field => $propfield) {
			if (in_array($field, array('username', 'countryCode', 'vatCode', 'fiscalCode', 'sdiIdentification', 'sdiInvoiceFileName', 'invoiceXmlBase64', 'sdiMetadataFileName', 'metadataXmlBase64')) || $propfield['notnull'] != 1) continue; // Not a mandatory field
			if (!isset($data[$field]))
				throw new RestException(400, "$field field missing");
			$object[$field] = $data[$field];
		}
		return $object;
	}
	/**
	 * Validate fields before create or update object
	 *
	 * @param	array		$data   Array of data to validate
	 * @return	array
	 *
	 * @throws	RestException
	 */
	private function _validateNotification($data)
	{
		$object = array();
		foreach ($this->myobject->fields as $field => $propfield) {
			if (in_array($field, array('username', 'countryCode', 'vatCode', 'fiscalCode', 'sdiIdentification', 'sdiInvoiceFileName', 'invoiceXmlBase64', 'sdiMetadataFileName', 'metadataXmlBase64')) || $propfield['notnull'] != 1) continue; // Not a mandatory field
			if (!isset($data[$field]))
				throw new RestException(400, "$field field missing");
			$object[$field] = $data[$field];
		}
		return $object;
	}
	/**
	 * Validate fields before create or update object
	 *
	 * @param	array		$data   Array of data to validate
	 * @return	array
	 *
	 * @throws	RestException
	 */
	private function _validateUsage($data)
	{
		$object = array();
		foreach ($this->myobject->fields as $field => $propfield) {
			if (in_array($field, array('username', 'maxSpaceKB', 'usedSpaceKB')) || $propfield['notnull'] != 1) continue; // Not a mandatory field
			if (!isset($data[$field]))
				throw new RestException(400, "$field field missing");
			$object[$field] = $data[$field];
		}
		return $object;
	}
	/**
	 * Validate fields before create or update object
	 *
	 * @param	array		$data   Array of data to validate
	 * @return	array
	 *
	 * @throws	RestException
	 */
	private function _validateInvoiceStatus($data)
	{
		$object = array();
		foreach ($this->myobject->fields as $field => $propfield) {
			if (in_array($field, array('username', 'countryCode', 'vatCode', 'fiscalCode', 'sdiIdentification', 'status', 'errorDescription')) || $propfield['notnull'] != 1) continue; // Not a mandatory field
			if (!isset($data[$field]))
				throw new RestException(400, "$field field missing");
			$object[$field] = $data[$field];
		}
		return $object;
	}
}
