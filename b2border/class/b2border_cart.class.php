<?php
/* Copyright (C) 2025 Henaxis */

/**
 * B2B Order Portal - Session-based shopping cart
 * Prices are always recalculated from DB, never trusted from client-side
 */
class B2BOrderCart
{
	/** @var DoliDB */
	private $db;

	/** @var B2BOrderProduct */
	private $productHelper;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
		$this->productHelper = new B2BOrderProduct($db);
		$this->initCart();
	}

	/**
	 * Initialize cart in session if not present
	 */
	private function initCart()
	{
		if (!isset($_SESSION['b2border_cart']) || !is_array($_SESSION['b2border_cart'])) {
			$_SESSION['b2border_cart'] = array('items' => array());
		}
		if (!isset($_SESSION['b2border_cart']['items'])) {
			$_SESSION['b2border_cart']['items'] = array();
		}
	}

	/**
	 * Add product to cart or update quantity if already present
	 *
	 * @param	int		$fk_product		Product ID
	 * @param	int		$qty			Quantity to add
	 * @param	int		$price_level	Customer price level
	 * @return	int						1 on success, -1 on error
	 */
	public function addItem($fk_product, $qty, $price_level = 1)
	{
		$fk_product = (int) $fk_product;
		$qty = max(1, (int) $qty);

		// Get product details from DB
		$prodData = $this->productHelper->getProductDetail($fk_product, $price_level);
		if (!$prodData) {
			return -1;
		}

		// If already in cart, add quantity
		if (isset($_SESSION['b2border_cart']['items'][$fk_product])) {
			$_SESSION['b2border_cart']['items'][$fk_product]['qty'] += $qty;
		} else {
			$_SESSION['b2border_cart']['items'][$fk_product] = array(
				'fk_product' => $fk_product,
				'ref' => $prodData['ref'],
				'label' => $prodData['label'],
				'qty' => $qty,
				'pu_ht' => $prodData['price_ht'],
				'pu_ttc' => $prodData['price_ttc'],
				'tva_tx' => $prodData['tva_tx'],
				'price_base_type' => $prodData['price_base_type'],
				'product_type' => $prodData['product_type'],
			);
		}

		return 1;
	}

	/**
	 * Update item quantity
	 *
	 * @param	int		$fk_product		Product ID
	 * @param	int		$qty			New quantity (0 = remove)
	 * @return	int						1 on success
	 */
	public function updateItemQty($fk_product, $qty)
	{
		$fk_product = (int) $fk_product;
		$qty = (int) $qty;

		if ($qty <= 0) {
			return $this->removeItem($fk_product);
		}

		if (isset($_SESSION['b2border_cart']['items'][$fk_product])) {
			$_SESSION['b2border_cart']['items'][$fk_product]['qty'] = $qty;
		}

		return 1;
	}

	/**
	 * Remove item from cart
	 *
	 * @param	int		$fk_product		Product ID
	 * @return	int						1 on success
	 */
	public function removeItem($fk_product)
	{
		$fk_product = (int) $fk_product;
		unset($_SESSION['b2border_cart']['items'][$fk_product]);
		return 1;
	}

	/**
	 * Clear entire cart
	 *
	 * @return	void
	 */
	public function clear()
	{
		$_SESSION['b2border_cart'] = array('items' => array());
	}

	/**
	 * Get all items, with prices recalculated from DB
	 *
	 * @param	int		$price_level	Customer price level
	 * @return	array					Cart items with refreshed prices
	 */
	public function getItems($price_level = 1)
	{
		$items = array();

		if (empty($_SESSION['b2border_cart']['items'])) {
			return $items;
		}

		foreach ($_SESSION['b2border_cart']['items'] as $fk_product => $item) {
			// Recalculate prices from DB
			$prodData = $this->productHelper->getProductDetail($fk_product, $price_level);
			if (!$prodData) {
				// Product no longer available, remove from cart
				unset($_SESSION['b2border_cart']['items'][$fk_product]);
				continue;
			}

			// Update prices in session
			$_SESSION['b2border_cart']['items'][$fk_product]['pu_ht'] = $prodData['price_ht'];
			$_SESSION['b2border_cart']['items'][$fk_product]['pu_ttc'] = $prodData['price_ttc'];
			$_SESSION['b2border_cart']['items'][$fk_product]['tva_tx'] = $prodData['tva_tx'];
			$_SESSION['b2border_cart']['items'][$fk_product]['price_base_type'] = $prodData['price_base_type'];

			$item['pu_ht'] = $prodData['price_ht'];
			$item['pu_ttc'] = $prodData['price_ttc'];
			$item['tva_tx'] = $prodData['tva_tx'];
			$item['price_base_type'] = $prodData['price_base_type'];

			$items[$fk_product] = $item;
		}

		return $items;
	}

	/**
	 * Get cart totals
	 *
	 * @param	int		$price_level	Customer price level
	 * @return	array					total_ht, total_vat, total_ttc
	 */
	public function getTotals($price_level = 1)
	{
		global $mysoc;

		$total_ht = 0;
		$total_vat = 0;
		$total_ttc = 0;

		$items = $this->getItems($price_level);

		foreach ($items as $item) {
			$qty = $item['qty'];
			$pu_ht = $item['pu_ht'];
			$pu_ttc = $item['pu_ttc'];
			$tva_tx = $item['tva_tx'];
			$price_base_type = $item['price_base_type'];

			$tabprice = calcul_price_total($qty, ($price_base_type == 'HT' ? $pu_ht : $pu_ttc), 0, $tva_tx, -1, -1, 0, $price_base_type, 0, $item['product_type'], $mysoc);

			$total_ht += $tabprice[0];
			$total_vat += $tabprice[1];
			$total_ttc += $tabprice[2];
		}

		return array(
			'total_ht' => $total_ht,
			'total_vat' => $total_vat,
			'total_ttc' => $total_ttc,
		);
	}

	/**
	 * Get number of items in cart
	 *
	 * @return	int
	 */
	public function getCount()
	{
		if (empty($_SESSION['b2border_cart']['items'])) {
			return 0;
		}
		return count($_SESSION['b2border_cart']['items']);
	}

	/**
	 * Check if cart is empty
	 *
	 * @return	bool
	 */
	public function isEmpty()
	{
		return $this->getCount() == 0;
	}
}
