<?php /* Copyright (C) 2025 Henaxis */
$baseUrl = dol_buildpath('/custom/b2cstore/public/index.php', 1);
$fields  = !empty($registrationFields) ? $registrationFields : ['firstname','lastname','email','password','phone','address','zip','town'];
?>
<div class="b2cs-page b2cs-page--auth">
	<div class="container">
		<div class="b2cs-auth-box b2cs-auth-box--wide">
			<h1 class="b2cs-auth-box__title"><?php echo $langs->trans('B2CStoreRegister'); ?></h1>
			<?php if (!empty($error)) { ?>
			<div class="b2cs-alert b2cs-alert--error"><?php echo dol_escape_htmltag($error); ?></div>
			<?php } ?>
			<?php if (!empty($info)) { ?>
			<div class="b2cs-alert b2cs-alert--info"><?php echo dol_escape_htmltag($info); ?></div>
			<?php } ?>
			<form class="b2cs-form" method="post" action="<?php echo $baseUrl; ?>?controller=register">
				<input type="hidden" name="action" value="register">
				<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">
				<!-- Honeypot anti-spam -->
				<div class="b2cs-honeypot" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
				<div class="b2cs-form__row">
					<?php if (in_array('firstname', $fields)) { ?>
					<div class="b2cs-form__field">
						<label for="reg-firstname"><?php echo $langs->trans('B2CStoreFirstname'); ?> *</label>
						<input type="text" id="reg-firstname" name="firstname" required autocomplete="given-name" value="<?php echo dol_escape_htmltag($formData['firstname'] ?? ''); ?>">
					</div>
					<?php } ?>
					<?php if (in_array('lastname', $fields)) { ?>
					<div class="b2cs-form__field">
						<label for="reg-lastname"><?php echo $langs->trans('B2CStoreLastname'); ?> *</label>
						<input type="text" id="reg-lastname" name="lastname" required autocomplete="family-name" value="<?php echo dol_escape_htmltag($formData['lastname'] ?? ''); ?>">
					</div>
					<?php } ?>
				</div>
				<?php if (in_array('company', $fields)) { ?>
				<div class="b2cs-form__field">
					<label for="reg-company"><?php echo $langs->trans('B2CStoreCompany'); ?></label>
					<input type="text" id="reg-company" name="company_name" autocomplete="organization" value="<?php echo dol_escape_htmltag($formData['company_name'] ?? ''); ?>">
				</div>
				<?php } ?>
				<div class="b2cs-form__field">
					<label for="reg-email"><?php echo $langs->trans('B2CStoreEmail'); ?> *</label>
					<input type="email" id="reg-email" name="email" required autocomplete="email" value="<?php echo dol_escape_htmltag($formData['email'] ?? ''); ?>">
				</div>
				<?php if (in_array('phone', $fields)) { ?>
				<div class="b2cs-form__field">
					<label for="reg-phone"><?php echo $langs->trans('B2CStorePhone'); ?></label>
					<input type="tel" id="reg-phone" name="phone" autocomplete="tel" value="<?php echo dol_escape_htmltag($formData['phone'] ?? ''); ?>">
				</div>
				<?php } ?>
				<?php if (in_array('address', $fields)) { ?>
				<div class="b2cs-form__field">
					<label for="reg-address"><?php echo $langs->trans('B2CStoreAddress'); ?></label>
					<input type="text" id="reg-address" name="address" autocomplete="street-address" value="<?php echo dol_escape_htmltag($formData['address'] ?? ''); ?>">
				</div>
				<?php } ?>
				<?php if (in_array('zip', $fields) || in_array('town', $fields)) { ?>
				<div class="b2cs-form__row">
					<?php if (in_array('zip', $fields)) { ?>
					<div class="b2cs-form__field b2cs-form__field--sm">
						<label for="reg-zip"><?php echo $langs->trans('B2CStoreZip'); ?></label>
						<input type="text" id="reg-zip" name="zip" autocomplete="postal-code" value="<?php echo dol_escape_htmltag($formData['zip'] ?? ''); ?>">
					</div>
					<?php } ?>
					<?php if (in_array('town', $fields)) { ?>
					<div class="b2cs-form__field">
						<label for="reg-town"><?php echo $langs->trans('B2CStoreTown'); ?></label>
						<input type="text" id="reg-town" name="town" autocomplete="address-level2" value="<?php echo dol_escape_htmltag($formData['town'] ?? ''); ?>">
					</div>
					<?php } ?>
				</div>
				<?php } ?>
				<div class="b2cs-form__row">
					<div class="b2cs-form__field">
						<label for="reg-pass"><?php echo $langs->trans('B2CStorePassword'); ?> * <small>(min 8)</small></label>
						<input type="password" id="reg-pass" name="password" required autocomplete="new-password" minlength="8">
					</div>
					<div class="b2cs-form__field">
						<label for="reg-pass2"><?php echo $langs->trans('B2CStorePasswordConfirm'); ?> *</label>
						<input type="password" id="reg-pass2" name="password2" required autocomplete="new-password" minlength="8">
					</div>
				</div>
				<button type="submit" class="b2cs-btn b2cs-btn--primary b2cs-btn--full"><?php echo $langs->trans('B2CStoreRegister'); ?></button>
			</form>
			<p class="b2cs-auth-box__footer">
				<?php echo $langs->trans('B2CStoreAlreadyAccount'); ?>
				<a href="<?php echo $baseUrl; ?>?controller=login"><?php echo $langs->trans('B2CStoreLogin'); ?></a>
			</p>
		</div>
	</div>
</div>
