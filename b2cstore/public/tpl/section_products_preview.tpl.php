<?php /* Copyright (C) 2025 Henaxis */
/** @var array $featuredProducts */
$bgStyle = !empty($section['bg_color']) ? ' style="background-color:'.dol_escape_htmltag($section['bg_color']).'"' : '';
$extraClass = !empty($section['css_class']) ? ' '.dol_escape_htmltag($section['css_class']) : '';
$imageUrl = dol_buildpath('/custom/b2cstore/public/image.php', 1);
$baseUrl  = dol_buildpath('/custom/b2cstore/public/index.php', 1);
$pricesHidden = $context->arePricesHidden();
?>
<section id="products_preview" class="b2cs-section b2cs-section--products-preview<?php echo $extraClass; ?>"<?php echo $bgStyle; ?>>
	<div class="container">
		<?php if (!empty($section['title'])) { ?><h2 class="b2cs-section__title b2cs-section__title--center"><?php echo dol_escape_htmltag($section['title']); ?></h2><?php } ?>
		<?php if (!empty($section['content'])) { ?><p class="b2cs-section__subtitle"><?php echo dol_escape_htmltag($section['content']); ?></p><?php } ?>
		<?php if (!empty($featuredProducts)) { ?>
		<div class="b2cs-grid b2cs-grid--products">
			<?php foreach ($featuredProducts as $prod) { ?>
			<article class="b2cs-product-card">
				<a href="<?php echo $baseUrl; ?>?controller=product&id=<?php echo (int) $prod['id']; ?>" class="b2cs-product-card__link">
					<div class="b2cs-product-card__img-wrap">
						<?php if (!empty($prod['photo'])) { ?>
						<img src="<?php echo $imageUrl; ?>?id=<?php echo (int) $prod['id']; ?>&thumb=1" alt="<?php echo dol_escape_htmltag($prod['label']); ?>" loading="lazy" class="b2cs-product-card__img">
						<?php } else { ?>
						<div class="b2cs-product-card__no-img">📦</div>
						<?php } ?>
					</div>
					<div class="b2cs-product-card__body">
						<h3 class="b2cs-product-card__name"><?php echo dol_escape_htmltag($prod['label']); ?></h3>
						<?php if (!$pricesHidden) { ?>
						<p class="b2cs-product-card__price"><?php echo price($prod['price_ttc']); ?></p>
						<?php } ?>
					</div>
				</a>
			</article>
			<?php } ?>
		</div>
		<div class="b2cs-section__cta">
			<a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2cs-btn b2cs-btn--primary"><?php echo $langs->trans('B2CStoreViewAllProducts'); ?></a>
		</div>
		<?php } ?>
	</div>
</section>
