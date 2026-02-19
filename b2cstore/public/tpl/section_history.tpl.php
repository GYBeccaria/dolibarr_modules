<?php /* Copyright (C) 2025 Henaxis */
$bgStyle = !empty($section['bg_color']) ? ' style="background-color:'.dol_escape_htmltag($section['bg_color']).'"' : '';
$extraClass = !empty($section['css_class']) ? ' '.dol_escape_htmltag($section['css_class']) : '';
?>
<section id="history" class="b2cs-section b2cs-section--history<?php echo $extraClass; ?>"<?php echo $bgStyle; ?>>
	<div class="container">
		<?php if (!empty($section['title'])) { ?><h2 class="b2cs-section__title b2cs-section__title--center"><?php echo dol_escape_htmltag($section['title']); ?></h2><?php } ?>
		<?php if (!empty($section['image'])) { ?><img src="<?php echo $getfileUrl.'?f='.urlencode($section['image']); ?>" alt="" loading="lazy" class="b2cs-img b2cs-img--centered"><?php } ?>
		<?php if (!empty($section['content'])) { ?><div class="b2cs-timeline b2cs-section__content"><?php echo $section['content']; ?></div><?php } ?>
	</div>
</section>
