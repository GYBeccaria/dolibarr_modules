<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * B2C Store - Session-based shopping cart.
 * Prices are always recalculated from DB on getItems() — never trusted from client.
 */
class B2CStoreCart
{
	/** @var DoliDB */
	private $db;

	/** @var B2CStoreProduct */
	private $productHelper;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->productHelper = new B2CStoreProduct($db);
		$this->initCart();
	}

	/**
	 * Initialize cart in session if not present.
	 *
	 * @return void
	 */
	private function initCart()
	{
		if (!isset($_SESSION['b2cstore_cart']) || !is_array($_SESSION['b2cstore_cart'])) {
			$_SESSION['b2cstore_cart'] = array('items' => array());
		}
		if (!isset($_SESSION['b2cstore_cart']['items'])) {
			$_SESSION['b2cstore_cart']['items'] = array();
		}
	}

	/**
	 * Add a product to the cart (or increment quantity if already present).
	 *
	 * @param int $fk_product  Product rowid
	 * @param int $qty         Quantity to add
	 * @param int $price_level Customer price level
	 * @return int             1 on success, -1 if product not available
	 */
	public function addItem($fk_product, $qty, $price_level = 1)
	{
		$fk_product = (int) $fk_product;
		$qty = max(1, (int) $qty);

		$prodData = $this->productHelper->getProductDetail($fk_product, $price_level);
		if (!$prodData) {
			return -1;
		}

		if (isset($_SESSION['b2cstore_cart']['items'][$fk_product])) {
			$_SESSION['b2cstore_cart']['items'][$fk_product]['qty'] += $qty;
		} else {
			$_SESSION['b2cstore_cart']['items'][$fk_product] = array(
				'fk_product'      => $fk_product,
				'ref'             => $prodData['ref'],
				'label'           => $prodData['label'],
				'qty'             => $qty,
				'pu_ht'           => $prodData['price_ht'],
				'pu_ttc'          => $prodData['price_ttc'],
				'tva_tx'          => $prodData['tva_tx'],
				'price_base_type' => $prodData['price_base_type'],
				'product_type'    => $prodData['product_type'],
			);
		}

		return 1;
	}

	/**
	 * Update quantity for a cart item (0 = remove).
	 *
	 * @param int $fk_product Product rowid
	 * @param int $qty        New quantity
	 * @return int            1 on success
	 */
	public function updateItemQty($fk_product, $qty)
	{
		$fk_product = (int) $fk_product;
		$qty = (int) $qty;

		if ($qty <= 0) {
			return $this->removeItem($fk_product);
		}

		if (isset($_SESSION['b2cstore_cart']['items'][$fk_product])) {
			$_SESSION['b2cstore_cart']['items'][$fk_product]['qty'] = $qty;
		}

		return 1;
	}

	/**
	 * Remove a specific item from the cart.
	 *
	 * @param int $fk_product Product rowid
	 * @return int            1
	 */
	public function removeItem($fk_product)
	{
		unset($_SESSION['b2cstore_cart']['items'][(int) $fk_product]);
		return 1;
	}

	/**
	 * Clear the entire cart.
	 *
	 * @return void
	 */
	public function clear()
	{
		$_SESSION['b2cstore_cart'] = array('items' => array());
	}

	/**
	 * Get all cart items with prices refreshed from DB.
	 *
	 * @param int $price_level Customer price level
	 * @return array           Cart items with up-to-date prices
	 */
	public function getItems($price_level = 1)
	{
		$items = array();
		if (empty($_SESSION['b2cstore_cart']['items'])) {
			return $items;
		}

		foreach ($_SESSION['b2cstore_cart']['items'] as $fk_product => $item) {
			$prodData = $this->productHelper->getProductDetail($fk_product, $price_level);
			if (!$prodData) {
				unset($_SESSION['b2cstore_cart']['items'][$fk_product]);
				continue;
			}
			// Refresh prices from DB
			$_SESSION['b2cstore_cart']['items'][$fk_product]['pu_ht']           = $prodData['price_ht'];
			$_SESSION['b2cstore_cart']['items'][$fk_product]['pu_ttc']          = $prodData['price_ttc'];
			$_SESSION['b2cstore_cart']['items'][$fk_product]['tva_tx']          = $prodData['tva_tx'];
			$_SESSION['b2cstore_cart']['items'][$fk_product]['price_base_type'] = $prodData['price_base_type'];

			$item['pu_ht']           = $prodData['price_ht'];
			$item['pu_ttc']          = $prodData['price_ttc'];
			$item['tva_tx']          = $prodData['tva_tx'];
			$item['price_base_type'] = $prodData['price_base_type'];

			$items[$fk_product] = $item;
		}

		return $items;
	}

	/**
	 * Calculate cart totals.
	 *
	 * @param int $price_level Customer price level
	 * @return array           total_ht, total_vat, total_ttc
	 */
	public function getTotals($price_level = 1)
	{
		global $mysoc;

		$total_ht = 0;
		$total_vat = 0;
		$total_ttc = 0;

		foreach ($this->getItems($price_level) as $item) {
			$tabprice = calcul_price_total(
				$item['qty'],
				($item['price_base_type'] == 'HT' ? $item['pu_ht'] : $item['pu_ttc']),
				0, $item['tva_tx'], -1, -1, 0, $item['price_base_type'], 0,
				$item['product_type'], $mysoc
			);
			$total_ht  += $tabprice[0];
			$total_vat += $tabprice[1];
			$total_ttc += $tabprice[2];
		}

		return array('total_ht' => $total_ht, 'total_vat' => $total_vat, 'total_ttc' => $total_ttc);
	}

	/**
	 * Get number of distinct items in cart.
	 *
	 * @return int
	 */
	public function getCount()
	{
		return empty($_SESSION['b2cstore_cart']['items']) ? 0 : count($_SESSION['b2cstore_cart']['items']);
	}

	/**
	 * Check if cart is empty.
	 *
	 * @return bool
	 */
	public function isEmpty()
	{
		return $this->getCount() === 0;
	}
}
