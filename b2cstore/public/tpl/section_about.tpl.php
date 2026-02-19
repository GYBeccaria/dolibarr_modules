<?php /* Copyright (C) 2025 Henaxis */
$bgStyle = !empty($section['bg_color']) ? ' style="background-color:'.dol_escape_htmltag($section['bg_color']).'"' : '';
$extraClass = !empty($section['css_class']) ? ' '.dol_escape_htmltag($section['css_class']) : '';
?>
<section id="about" class="b2cs-section b2cs-section--about<?php echo $extraClass; ?>"<?php echo $bgStyle; ?>>
	<div class="container b2cs-about__inner<?php echo !empty($section['image']) ? ' b2cs-about__inner--split' : ''; ?>">
		<div class="b2cs-about__text">
			<?php if (!empty($section['title'])) { ?><h2 class="b2cs-section__title"><?php echo dol_escape_htmltag($section['title']); ?></h2><?php } ?>
			<?php if (!empty($section['content'])) { ?><div class="b2cs-section__content"><?php echo $section['content']; ?></div><?php } ?>
		</div>
		<?php if (!empty($section['image'])) { ?>
		<div class="b2cs-about__image">
			<img src="<?php echo $getfileUrl.'?f='.urlencode($section['image']); ?>" alt="" loading="lazy" class="b2cs-img">
		</div>
		<?php } ?>
	</div>
</section>
