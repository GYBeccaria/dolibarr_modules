<?php /* Copyright (C) 2025 Henaxis */
$baseUrl = dol_buildpath('/custom/b2cstore/public/index.php', 1);
?>
<div class="b2cs-page b2cs-page--auth">
	<div class="container">
		<div class="b2cs-auth-box">
			<h1 class="b2cs-auth-box__title"><?php echo $langs->trans('B2CStoreLogin'); ?></h1>
			<?php if (!empty($error)) { ?>
			<div class="b2cs-alert b2cs-alert--error"><?php echo dol_escape_htmltag($error); ?></div>
			<?php } ?>
			<?php if (!empty($info)) { ?>
			<div class="b2cs-alert b2cs-alert--info"><?php echo dol_escape_htmltag($info); ?></div>
			<?php } ?>
			<form class="b2cs-form" method="post" action="<?php echo $baseUrl; ?>?controller=login">
				<input type="hidden" name="action" value="login">
				<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">
				<?php if (!empty($redirectController)) { ?>
				<input type="hidden" name="redirect" value="<?php echo dol_escape_htmltag($redirectController); ?>">
				<?php } ?>
				<div class="b2cs-form__field">
					<label for="login-email"><?php echo $langs->trans('B2CStoreEmail'); ?> *</label>
					<input type="email" id="login-email" name="login" required autocomplete="email" autofocus>
				</div>
				<div class="b2cs-form__field">
					<label for="login-pass"><?php echo $langs->trans('B2CStorePassword'); ?> *</label>
					<input type="password" id="login-pass" name="password" required autocomplete="current-password">
				</div>
				<button type="submit" class="b2cs-btn b2cs-btn--primary b2cs-btn--full"><?php echo $langs->trans('B2CStoreLogin'); ?></button>
			</form>
			<?php if (!empty($registrationEnabled)) { ?>
			<p class="b2cs-auth-box__footer">
				<?php echo $langs->trans('B2CStoreNoAccount'); ?>
				<a href="<?php echo $baseUrl; ?>?controller=register"><?php echo $langs->trans('B2CStoreRegister'); ?></a>
			</p>
			<?php } ?>
		</div>
	</div>
</div>
