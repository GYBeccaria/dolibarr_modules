<?php /* Copyright (C) 2025 Henaxis */
$bgStyle = !empty($section['bg_color']) ? ' style="background-color:'.dol_escape_htmltag($section['bg_color']).'"' : '';
$extraClass = !empty($section['css_class']) ? ' '.dol_escape_htmltag($section['css_class']) : '';
$baseUrl = dol_buildpath('/custom/b2cstore/public/index.php', 1);
?>
<section id="contact" class="b2cs-section b2cs-section--contact<?php echo $extraClass; ?>"<?php echo $bgStyle; ?>>
	<div class="container">
		<?php if (!empty($section['title'])) { ?><h2 class="b2cs-section__title b2cs-section__title--center"><?php echo dol_escape_htmltag($section['title']); ?></h2><?php } ?>
		<?php if (!empty($section['content'])) { ?><p class="b2cs-section__subtitle"><?php echo dol_escape_htmltag($section['content']); ?></p><?php } ?>
		<form class="b2cs-form b2cs-contact-form" method="post" action="<?php echo $baseUrl; ?>?controller=contact">
			<input type="hidden" name="action" value="sendmessage">
			<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">
			<!-- Honeypot anti-spam -->
			<div class="b2cs-honeypot" aria-hidden="true"><input type="text" name="company" tabindex="-1" autocomplete="off"></div>
			<div class="b2cs-form__row">
				<div class="b2cs-form__field">
					<label for="cnt-name"><?php echo $langs->trans('B2CStoreName'); ?> *</label>
					<input type="text" id="cnt-name" name="name" required autocomplete="name">
				</div>
				<div class="b2cs-form__field">
					<label for="cnt-email"><?php echo $langs->trans('B2CStoreEmail'); ?> *</label>
					<input type="email" id="cnt-email" name="email" required autocomplete="email">
				</div>
			</div>
			<div class="b2cs-form__row">
				<div class="b2cs-form__field">
					<label for="cnt-phone"><?php echo $langs->trans('B2CStorePhone'); ?></label>
					<input type="tel" id="cnt-phone" name="phone" autocomplete="tel">
				</div>
				<div class="b2cs-form__field">
					<label for="cnt-subject"><?php echo $langs->trans('B2CStoreSubject'); ?></label>
					<input type="text" id="cnt-subject" name="subject">
				</div>
			</div>
			<div class="b2cs-form__field">
				<label for="cnt-message"><?php echo $langs->trans('B2CStoreMessage'); ?> *</label>
				<textarea id="cnt-message" name="message" rows="5" required></textarea>
			</div>
			<button type="submit" class="b2cs-btn b2cs-btn--primary"><?php echo $langs->trans('B2CStoreSend'); ?></button>
		</form>
	</div>
</section>
