<?php /* Copyright (C) 2025 Henaxis */
$bgStyle    = !empty($pageData['bg_color'])  ? ' style="background-color:'.dol_escape_htmltag($pageData['bg_color']).'"' : '';
$extraClass = !empty($pageData['css_class']) ? ' '.dol_escape_htmltag($pageData['css_class']) : '';
?>
<div class="b2cs-page b2cs-page--static<?php echo $extraClass; ?>"<?php echo $bgStyle; ?>>
	<div class="container">
		<?php if (!empty($pageData['title'])) { ?>
		<h1 class="b2cs-page__title"><?php echo dol_escape_htmltag($pageData['title']); ?></h1>
		<?php } ?>
		<?php if (!empty($pageData['image'])) { ?>
		<img src="<?php echo $getfileUrl.'?f='.urlencode($pageData['image']); ?>" alt="" loading="lazy" class="b2cs-img b2cs-img--centered">
		<?php } ?>
		<?php if (!empty($pageData['content'])) { ?>
		<div class="b2cs-page__content"><?php echo $pageData['content']; ?></div>
		<?php } ?>
		<?php if (!empty($extraContent)) { ?>
		<div class="b2cs-page__extra"><?php echo $extraContent; ?></div>
		<?php } ?>
	</div>
</div>
