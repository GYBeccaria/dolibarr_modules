<?php
/* Copyright (C) 2025 Henaxis
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

/**
 * B2C Store - Product catalog queries.
 * Mirrors B2BOrderProduct but uses B2CSTORE_* constants.
 */
class B2CStoreProduct
{
	/** @var DoliDB */
	private $db;

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
	 * Get paginated product list for catalog with search and category filter.
	 *
	 * @param int    $price_level  Price level
	 * @param int    $limit        Products per page
	 * @param int    $offset       Pagination offset
	 * @param string $search       Search text (ref, label, description)
	 * @param int    $category_id  Filter by category ID (0 = all allowed)
	 * @param string $sortfield    Sort field
	 * @param string $sortorder    Sort order ASC/DESC
	 * @return array               Array('products'=>array, 'total'=>int)
	 */
	public function getCatalogProducts($price_level = 1, $limit = 12, $offset = 0, $search = '', $category_id = 0, $sortfield = 'p.label', $sortorder = 'ASC')
	{
		$catFilter = $this->buildAllowedFilter();

		if ($category_id > 0) {
			$catFilter .= " AND p.rowid IN (SELECT fk_product FROM ".$this->db->prefix()."categorie_product WHERE fk_categorie = ".(int) $category_id.")";
		}

		$searchFilter = '';
		if (!empty($search)) {
			$s = $this->db->escape($search);
			$searchFilter = " AND (p.ref LIKE '%{$s}%' OR p.label LIKE '%{$s}%' OR p.description LIKE '%{$s}%')";
		}

		$allowed = array('p.label', 'p.ref', 'p.rowid', 'p.datec');
		if (!in_array($sortfield, $allowed)) {
			$sortfield = 'p.label';
		}
		$sortorder = strtoupper($sortorder) === 'DESC' ? 'DESC' : 'ASC';

		$base = " FROM ".$this->db->prefix()."product p WHERE p.tosell = 1 AND p.entity IN (".getEntity('product').")".$catFilter.$searchFilter;

		$res = $this->db->query("SELECT COUNT(DISTINCT p.rowid) as total".$base);
		$total = $res ? (int) $this->db->fetch_object($res)->total : 0;

		if ($total === 0) {
			return array('products' => array(), 'total' => 0);
		}

		$sql = "SELECT DISTINCT p.rowid".$base." ORDER BY {$sortfield} {$sortorder}".$this->db->plimit($limit, $offset);
		$res = $this->db->query($sql);

		$products = array();
		if ($res) {
			while ($obj = $this->db->fetch_object($res)) {
				$product = new Product($this->db);
				$product->fetch($obj->rowid);
				$priceInfo = $this->getProductPrice($product, $price_level);
				$products[] = array(
					'id'             => $product->id,
					'ref'            => $product->ref,
					'label'          => $product->label,
					'description'    => $product->description,
					'price_ht'       => $priceInfo['price_ht'],
					'price_ttc'      => $priceInfo['price_ttc'],
					'tva_tx'         => $priceInfo['tva_tx'],
					'price_base_type'=> $priceInfo['price_base_type'],
					'in_stock'       => $this->isInStock($product),
					'product_type'   => $product->type,
					'photo'          => $this->getMainPhoto($product),
				);
			}
		}

		return array('products' => $products, 'total' => $total);
	}

	/**
	 * Get featured products for homepage preview.
	 *
	 * @param int $count      Number of products to return
	 * @param int $price_level Price level
	 * @return array
	 */
	public function getFeaturedProducts($count = 6, $price_level = 1)
	{
		$result = $this->getCatalogProducts($price_level, $count, 0, '', 0, 'p.rowid', 'DESC');
		return $result['products'];
	}

	/**
	 * Get single product details (checks tosell=1 and category filter).
	 *
	 * @param int $product_id  Product rowid
	 * @param int $price_level Price level
	 * @return array|null      Product data or null if not accessible
	 */
	public function getProductDetail($product_id, $price_level = 1)
	{
		$product = new Product($this->db);
		if ($product->fetch($product_id) <= 0 || $product->status != 1) {
			return null;
		}

		$allowFilter = $this->buildAllowedFilter();
		if (!empty($allowFilter)) {
			$sql = "SELECT p.rowid FROM ".$this->db->prefix()."product p WHERE p.rowid = ".(int) $product_id.$allowFilter;
			$res = $this->db->query($sql);
			if (!$res || $this->db->num_rows($res) == 0) {
				return null;
			}
		}

		$priceInfo = $this->getProductPrice($product, $price_level);

		return array(
			'id'             => $product->id,
			'ref'            => $product->ref,
			'label'          => $product->label,
			'description'    => $product->description,
			'note_public'    => $product->note_public,
			'price_ht'       => $priceInfo['price_ht'],
			'price_ttc'      => $priceInfo['price_ttc'],
			'tva_tx'         => $priceInfo['tva_tx'],
			'price_base_type'=> $priceInfo['price_base_type'],
			'in_stock'       => $this->isInStock($product),
			'product_type'   => $product->type,
			'photo'          => $this->getMainPhoto($product),
			'weight'         => $product->weight,
			'weight_units'   => $product->weight_units,
			'barcode'        => $product->barcode,
		);
	}

	/**
	 * Get product price for a given level (supports multiprices).
	 *
	 * @param Product $product    Product object
	 * @param int     $price_level Price level
	 * @return array              price_ht, price_ttc, tva_tx, price_base_type
	 */
	public function getProductPrice($product, $price_level = 1)
	{
		if (getDolGlobalString('PRODUIT_MULTIPRICES')) {
			$level = $price_level;
			if (isset($product->multiprices[$level])) {
				return array(
					'price_ht'        => (float) $product->multiprices[$level],
					'price_ttc'       => (float) $product->multiprices_ttc[$level],
					'tva_tx'          => (float) $product->multiprices_tva_tx[$level],
					'price_base_type' => $product->multiprices_base_type[$level],
				);
			}
			// Fallback to level 1
			return array(
				'price_ht'        => (float) (isset($product->multiprices[1]) ? $product->multiprices[1] : $product->price),
				'price_ttc'       => (float) (isset($product->multiprices_ttc[1]) ? $product->multiprices_ttc[1] : $product->price_ttc),
				'tva_tx'          => (float) (isset($product->multiprices_tva_tx[1]) ? $product->multiprices_tva_tx[1] : $product->tva_tx),
				'price_base_type' => isset($product->multiprices_base_type[1]) ? $product->multiprices_base_type[1] : $product->price_base_type,
			);
		}

		return array(
			'price_ht'        => (float) $product->price,
			'price_ttc'       => (float) $product->price_ttc,
			'tva_tx'          => (float) $product->tva_tx,
			'price_base_type' => $product->price_base_type,
		);
	}

	/**
	 * Check if a product has stock available.
	 *
	 * @param Product $product Product object
	 * @return bool
	 */
	public function isInStock($product)
	{
		if (!getDolGlobalInt('B2CSTORE_SHOW_STOCK', 0)) {
			return true;
		}
		if (!isModEnabled('stock')) {
			return true;
		}
		if ($product->type == Product::TYPE_SERVICE) {
			return true;
		}
		$product->load_stock();
		return ($product->stock_reel > 0);
	}

	/**
	 * Get the first photo filename for a product.
	 *
	 * @param Product $product Product object
	 * @return string Filename or empty string
	 */
	public function getMainPhoto($product)
	{
		global $conf;

		$dir = $conf->product->multidir_output[$product->entity].'/'.$product->ref;
		if (!is_dir($dir)) {
			$dir = $conf->product->multidir_output[$product->entity].'/'.get_exdir(0, 0, 0, 1, $product, 'product');
		}

		if (is_dir($dir)) {
			$photos = array();
			$handle = opendir($dir);
			if ($handle) {
				while (($file = readdir($handle)) !== false) {
					if ($file === '.' || $file === '..' || $file === 'thumbs') {
						continue;
					}
					if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
						$photos[] = $file;
					}
				}
				closedir($handle);
				sort($photos);
			}
			if (!empty($photos)) {
				return $photos[0];
			}
		}
		return '';
	}

	/**
	 * Build WHERE clause fragment restricting to allowed categories/tags (OR logic).
	 *
	 * @return string SQL fragment starting with " AND ..." or empty string
	 */
	public function buildAllowedFilter()
	{
		$catIds = array();
		$tagIds = array();

		$allowedCats = getDolGlobalString('B2CSTORE_ALLOWED_CATEGORIES');
		if (!empty($allowedCats)) {
			$catIds = array_values(array_filter(array_map('intval', explode(',', $allowedCats))));
		}

		$allowedTags = getDolGlobalString('B2CSTORE_ALLOWED_TAGS');
		if (!empty($allowedTags)) {
			$tagIds = array_values(array_filter(array_map('intval', explode(',', $allowedTags))));
		}

		if (empty($catIds) && empty($tagIds)) {
			return '';
		}

		$parts = array();
		if (!empty($catIds)) {
			$parts[] = "p.rowid IN (SELECT fk_product FROM ".$this->db->prefix()."categorie_product WHERE fk_categorie IN (".implode(',', $catIds)."))";
		}
		if (!empty($tagIds)) {
			$parts[] = "p.rowid IN (SELECT fk_element FROM ".$this->db->prefix()."element_tag WHERE fk_categorie IN (".implode(',', $tagIds)."))";
		}

		return " AND (".implode(" OR ", $parts).")";
	}

	/**
	 * Get available categories for the catalog filter menu.
	 *
	 * @return array id => label
	 */
	public function getAvailableCategories()
	{
		$categories = array();

		$allowedCats = getDolGlobalString('B2CSTORE_ALLOWED_CATEGORIES');
		$allowedTags = getDolGlobalString('B2CSTORE_ALLOWED_TAGS');

		$catIds = !empty($allowedCats) ? array_values(array_filter(array_map('intval', explode(',', $allowedCats)))) : array();
		$tagIds = !empty($allowedTags) ? array_values(array_filter(array_map('intval', explode(',', $allowedTags)))) : array();

		$visibleIds = array_unique(array_merge($catIds, $tagIds));

		$sql = "SELECT c.rowid, c.label FROM ".$this->db->prefix()."categorie c WHERE c.entity IN (".getEntity('category').")";
		if (!empty($visibleIds)) {
			$sql .= " AND c.rowid IN (".implode(',', $visibleIds).")";
		}
		$sql .= " ORDER BY c.label ASC";

		$res = $this->db->query($sql);
		if ($res) {
			while ($obj = $this->db->fetch_object($res)) {
				$categories[$obj->rowid] = $obj->label;
			}
		}

		return $categories;
	}
}
