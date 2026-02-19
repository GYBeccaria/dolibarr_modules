<?php /* Copyright (C) 2025 Henaxis */
$bgStyle = !empty($section['bg_color']) ? ' style="background-color:'.dol_escape_htmltag($section['bg_color']).'"' : '';
$extraClass = !empty($section['css_class']) ? ' '.dol_escape_htmltag($section['css_class']) : '';
?>
<section id="b2b_link" class="b2cs-section b2cs-section--b2b-link<?php echo $extraClass; ?>"<?php echo $bgStyle; ?>>
	<div class="container b2cs-b2b-link__inner">
		<?php if (!empty($section['title'])) { ?><h2 class="b2cs-section__title"><?php echo dol_escape_htmltag($section['title']); ?></h2><?php } ?>
		<?php if (!empty($section['content'])) { ?><p class="b2cs-b2b-link__text"><?php echo dol_escape_htmltag($section['content']); ?></p><?php } ?>
		<?php if (!empty($b2bLinkUrl)) { ?>
		<a href="<?php echo dol_escape_htmltag($b2bLinkUrl); ?>" class="b2cs-btn b2cs-btn--secondary b2cs-b2b-link__btn">
			<?php echo dol_escape_htmltag($b2bLinkText ?: $langs->trans('B2CStoreB2BLink')); ?>
		</a>
		<?php } ?>
	</div>
</section>
