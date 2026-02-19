<?php
/* Copyright (C) 2025 Henaxis */

require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

/**
 * B2B Order Portal - Product catalog queries
 */
class B2BOrderProduct
{
	/** @var DoliDB */
	private $db;

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Get products for catalog display with pagination and filters
	 *
	 * @param	int		$price_level	Price level for this customer
	 * @param	int		$limit			Number of products per page
	 * @param	int		$offset			Offset for pagination
	 * @param	string	$search			Search text (ref, label, description)
	 * @param	int		$category_id	Filter by category ID (0 = all allowed)
	 * @param	string	$sortfield		Sort field
	 * @param	string	$sortorder		Sort order
	 * @return	array					Array('products' => array, 'total' => int)
	 */
	public function getCatalogProducts($price_level = 1, $limit = 12, $offset = 0, $search = '', $category_id = 0, $sortfield = 'p.label', $sortorder = 'ASC')
	{
		global $conf;

		$products = array();
		$total = 0;

		// Build allowed filter (categories OR tags - product must match at least one)
		$catFilter = $this->buildAllowedFilter();

		// Additional category filter from user selection in portal
		if ($category_id > 0) {
			$catFilter .= " AND p.rowid IN (SELECT fk_product FROM ".$this->db->prefix()."categorie_product WHERE fk_categorie = ".(int) $category_id.")";
		}

		// Search filter
		$searchFilter = '';
		if (!empty($search)) {
			$searchEsc = $this->db->escape($search);
			$searchFilter = " AND (p.ref LIKE '%".$searchEsc."%' OR p.label LIKE '%".$searchEsc."%' OR p.description LIKE '%".$searchEsc."%')";
		}

		// Sanitize sort
		$allowedSortFields = array('p.label', 'p.ref', 'p.rowid', 'p.datec');
		if (!in_array($sortfield, $allowedSortFields)) {
			$sortfield = 'p.label';
		}
		$sortorder = strtoupper($sortorder) == 'DESC' ? 'DESC' : 'ASC';

		// Count total
		$sqlCount = "SELECT COUNT(DISTINCT p.rowid) as total";
		$sqlCount .= " FROM ".$this->db->prefix()."product as p";
		$sqlCount .= " WHERE p.tosell = 1";
		$sqlCount .= " AND p.entity IN (".getEntity('product').")";
		$sqlCount .= $catFilter;
		$sqlCount .= $searchFilter;

		$resql = $this->db->query($sqlCount);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$total = (int) $obj->total;
		}

		if ($total == 0) {
			return array('products' => array(), 'total' => 0);
		}

		// Fetch products
		$sql = "SELECT DISTINCT p.rowid";
		$sql .= " FROM ".$this->db->prefix()."product as p";
		$sql .= " WHERE p.tosell = 1";
		$sql .= " AND p.entity IN (".getEntity('product').")";
		$sql .= $catFilter;
		$sql .= $searchFilter;
		$sql .= " ORDER BY ".$sortfield." ".$sortorder;
		$sql .= $this->db->plimit($limit, $offset);

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			for ($i = 0; $i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$product = new Product($this->db);
				$product->fetch($obj->rowid);

				// Get price for this customer's level
				$priceInfo = $this->getProductPrice($product, $price_level);

				// Get stock availability
				$stockAvailable = $this->isInStock($product);

				$products[] = array(
					'id' => $product->id,
					'ref' => $product->ref,
					'label' => $product->label,
					'description' => $product->description,
					'price_ht' => $priceInfo['price_ht'],
					'price_ttc' => $priceInfo['price_ttc'],
					'tva_tx' => $priceInfo['tva_tx'],
					'price_base_type' => $priceInfo['price_base_type'],
					'in_stock' => $stockAvailable,
					'product_type' => $product->type,
					'photo' => $this->getMainPhoto($product),
				);
			}
		}

		return array('products' => $products, 'total' => $total);
	}

	/**
	 * Get single product details
	 *
	 * @param	int		$product_id		Product ID
	 * @param	int		$price_level	Price level
	 * @return	array|null				Product data or null
	 */
	public function getProductDetail($product_id, $price_level = 1)
	{
		$product = new Product($this->db);
		$result = $product->fetch($product_id);
		if ($result <= 0 || $product->status != 1) {
			return null;
		}

		// Check if product passes allowed categories/tags filter
		$allowFilter = $this->buildAllowedFilter();
		if (!empty($allowFilter)) {
			$sqlCheck = "SELECT p.rowid FROM ".$this->db->prefix()."product p WHERE p.rowid = ".(int) $product_id.$allowFilter;
			$resCheck = $this->db->query($sqlCheck);
			if (!$resCheck || $this->db->num_rows($resCheck) == 0) {
				return null;
			}
		}

		$priceInfo = $this->getProductPrice($product, $price_level);
		$stockAvailable = $this->isInStock($product);

		return array(
			'id' => $product->id,
			'ref' => $product->ref,
			'label' => $product->label,
			'description' => $product->description,
			'note_public' => $product->note_public,
			'price_ht' => $priceInfo['price_ht'],
			'price_ttc' => $priceInfo['price_ttc'],
			'tva_tx' => $priceInfo['tva_tx'],
			'price_base_type' => $priceInfo['price_base_type'],
			'in_stock' => $stockAvailable,
			'product_type' => $product->type,
			'photo' => $this->getMainPhoto($product),
			'weight' => $product->weight,
			'weight_units' => $product->weight_units,
			'barcode' => $product->barcode,
		);
	}

	/**
	 * Get product price for given price level (multiprices)
	 *
	 * @param	Product	$product		Product object
	 * @param	int		$price_level	Price level
	 * @return	array					price_ht, price_ttc, tva_tx, price_base_type
	 */
	public function getProductPrice($product, $price_level = 1)
	{
		global $conf;

		$price_ht = 0;
		$price_ttc = 0;
		$tva_tx = 0;
		$price_base_type = 'HT';

		// Check if multiprices is enabled
		if (getDolGlobalString('PRODUIT_MULTIPRICES')) {
			if (isset($product->multiprices[$price_level])) {
				$price_ht = $product->multiprices[$price_level];
				$price_ttc = $product->multiprices_ttc[$price_level];
				$tva_tx = $product->multiprices_tva_tx[$price_level];
				$price_base_type = $product->multiprices_base_type[$price_level];
			} else {
				// Fallback to level 1
				$price_ht = isset($product->multiprices[1]) ? $product->multiprices[1] : $product->price;
				$price_ttc = isset($product->multiprices_ttc[1]) ? $product->multiprices_ttc[1] : $product->price_ttc;
				$tva_tx = isset($product->multiprices_tva_tx[1]) ? $product->multiprices_tva_tx[1] : $product->tva_tx;
				$price_base_type = isset($product->multiprices_base_type[1]) ? $product->multiprices_base_type[1] : $product->price_base_type;
			}
		} else {
			$price_ht = $product->price;
			$price_ttc = $product->price_ttc;
			$tva_tx = $product->tva_tx;
			$price_base_type = $product->price_base_type;
		}

		return array(
			'price_ht' => (float) $price_ht,
			'price_ttc' => (float) $price_ttc,
			'tva_tx' => (float) $tva_tx,
			'price_base_type' => $price_base_type,
		);
	}

	/**
	 * Check if product is in stock (simple boolean)
	 *
	 * @param	Product	$product	Product object
	 * @return	bool				True if stock > 0 or stock tracking disabled
	 */
	public function isInStock($product)
	{
		global $conf;

		if (!getDolGlobalInt('B2BORDER_SHOW_STOCK', 1)) {
			return true; // If stock display disabled, always show as available
		}

		if (!isModEnabled('stock')) {
			return true;
		}

		// Services are always available
		if ($product->type == Product::TYPE_SERVICE) {
			return true;
		}

		$product->load_stock();
		return ($product->stock_reel > 0);
	}

	/**
	 * Get main photo filename for a product
	 *
	 * @param	Product	$product	Product object
	 * @return	string				Photo filename or empty
	 */
	public function getMainPhoto($product)
	{
		global $conf;

		$dir = $conf->product->multidir_output[$product->entity].'/'.$product->ref;
		if (!is_dir($dir)) {
			// Try with get_exdir
			$dir = $conf->product->multidir_output[$product->entity].'/'.get_exdir(0, 0, 0, 1, $product, 'product');
		}

		if (is_dir($dir)) {
			$photos = array();
			$handle = opendir($dir);
			if ($handle) {
				while (($file = readdir($handle)) !== false) {
					if ($file == '.' || $file == '..' || $file == 'thumbs') {
						continue;
					}
					if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file)) {
						$photos[] = $file;
					}
				}
				closedir($handle);
			}
			sort($photos);
			if (!empty($photos)) {
				return $photos[0];
			}
		}

		return '';
	}

	/**
	 * Build the WHERE clause fragment that restricts products to allowed categories/tags.
	 * If both are set, a product passes if it matches categories OR tags (union logic).
	 * Returns empty string if no restrictions are configured.
	 *
	 * @return	string	SQL fragment starting with " AND ..."
	 */
	public function buildAllowedFilter()
	{
		$catIds = array();
		$tagIds = array();

		$allowedCats = getDolGlobalString('B2BORDER_ALLOWED_CATEGORIES');
		if (!empty($allowedCats)) {
			$catIds = array_values(array_filter(array_map('intval', explode(',', $allowedCats))));
		}

		$allowedTags = getDolGlobalString('B2BORDER_ALLOWED_TAGS');
		if (!empty($allowedTags)) {
			$tagIds = array_values(array_filter(array_map('intval', explode(',', $allowedTags))));
		}

		if (empty($catIds) && empty($tagIds)) {
			return ''; // No restriction
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
	 * Get available categories for filter menu
	 * Only returns categories (and tag-based categories) that have at least one visible product.
	 *
	 * @return	array	Array of id => label
	 */
	public function getAvailableCategories()
	{
		$categories = array();

		$allowedCats = getDolGlobalString('B2BORDER_ALLOWED_CATEGORIES');
		$allowedTags = getDolGlobalString('B2BORDER_ALLOWED_TAGS');

		$catIds = array();
		if (!empty($allowedCats)) {
			$catIds = array_values(array_filter(array_map('intval', explode(',', $allowedCats))));
		}
		$tagIds = array();
		if (!empty($allowedTags)) {
			$tagIds = array_values(array_filter(array_map('intval', explode(',', $allowedTags))));
		}

		// Build union of category IDs to show in the portal filter bar
		$visibleCatIds = array_unique(array_merge($catIds, $tagIds));

		$sql = "SELECT c.rowid, c.label";
		$sql .= " FROM ".$this->db->prefix()."categorie as c";
		$sql .= " WHERE c.entity IN (".getEntity('category').")";
		if (!empty($visibleCatIds)) {
			$sql .= " AND c.rowid IN (".implode(',', $visibleCatIds).")";
		} elseif (!empty($catIds) || !empty($tagIds)) {
			// Should not reach here, but guard against empty filter
			return $categories;
		}
		$sql .= " ORDER BY c.label ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			for ($i = 0; $i < $num; $i++) {
				$obj = $this->db->fetch_object($resql);
				$categories[$obj->rowid] = $obj->label;
			}
		}

		return $categories;
	}
}
