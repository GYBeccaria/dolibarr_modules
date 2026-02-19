<?php
/* Copyright (C) 2025 Henaxis */
/** @var array  $sections         Ordered array of active sections */
/** @var array  $featuredProducts Featured products for PRODUCTS_PREVIEW */
/** @var string $b2bLinkUrl       URL for B2B portal */
/** @var string $b2bLinkText      Link text for B2B section */
/** @var B2CStoreContext $context */
/** @var Translate $langs */

$tplDir    = dirname(__FILE__).'/';
$getfileUrl = dol_buildpath('/custom/b2cstore/public/getfile.php', 1);
$baseUrl    = dol_buildpath('/custom/b2cstore/public/index.php', 1);
?>
<div class="b2cs-home">
<?php foreach ($sections as $section) {
	$type       = $section['type'];
	$sectionFile = $tplDir.'section_'.strtolower($type).'.tpl.php';
	if (file_exists($sectionFile)) {
		include $sectionFile;
	}
} ?>
</div>
