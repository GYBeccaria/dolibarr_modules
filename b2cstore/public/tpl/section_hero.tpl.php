<?php
/* Copyright (C) 2025 Henaxis */
/** @var array $section Hero section data */

$bgStyle = '';
if (!empty($section['bg_color'])) {
	$bgStyle = ' style="background-color:'.dol_escape_htmltag($section['bg_color']).'"';
}
if (!empty($section['image'])) {
	$imgUrl = $getfileUrl.'?f='.urlencode($section['image']);
	$bgStyle = ' style="background-image:url('.dol_escape_htmltag($imgUrl).');'.(!empty($section['bg_color']) ? 'background-color:'.dol_escape_htmltag($section['bg_color']).';' : '').'"';
}
$extraClass = !empty($section['css_class']) ? ' '.dol_escape_htmltag($section['css_class']) : '';
?>
<section id="hero" class="b2cs-section b2cs-section--hero<?php echo $extraClass; ?>"<?php echo $bgStyle; ?>>
	<div class="container b2cs-hero__inner">
		<?php if (!empty($section['title'])) { ?>
		<h1 class="b2cs-hero__title"><?php echo dol_escape_htmltag($section['title']); ?></h1>
		<?php } ?>
		<?php if (!empty($section['content'])) { ?>
		<div class="b2cs-hero__text"><?php echo $section['content']; /* Admin-configured HTML */ ?></div>
		<?php } ?>
		<div class="b2cs-hero__cta">
			<a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2cs-btn b2cs-btn--accent"><?php echo $langs->trans('B2CStoreShopNow'); ?></a>
		</div>
	</div>
</section>
