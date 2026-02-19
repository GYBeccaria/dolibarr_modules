<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2BOrderContext $context */
/** @var Translate $langs */

$baseUrl = dol_buildpath('/custom/b2border/public/index.php', 1);
$portalTitle = getDolGlobalString('B2BORDER_PORTAL_TITLE', 'B2B Order Portal');
$b2bLogo = getDolGlobalString('B2BORDER_LOGO');
$getfileUrl = dol_buildpath('/custom/b2border/public/getfile.php', 1);
?>
<div class="b2b-login-wrapper">
	<div class="b2b-login-box">
		<?php if ($b2bLogo) { ?>
		<div class="b2b-login-logo">
			<img src="<?php echo $getfileUrl; ?>?f=logo" alt="<?php echo dol_escape_htmltag($portalTitle); ?>">
		</div>
		<?php } ?>
		<h1 class="b2b-login-title"><?php echo dol_escape_htmltag($portalTitle); ?></h1>
		<p class="b2b-login-subtitle"><?php echo $langs->trans("B2BOrderLoginSubtitle"); ?></p>

		<form method="POST" action="<?php echo $baseUrl; ?>?controller=login" class="b2b-login-form">
			<input type="hidden" name="action" value="login">
			<input type="hidden" name="token" value="<?php echo $context->getToken(); ?>">

			<div class="b2b-form-group">
				<label for="login"><?php echo $langs->trans("Login"); ?></label>
				<input type="text" id="login" name="login" required autocomplete="username"
					value="<?php echo dol_escape_htmltag(GETPOST('login', 'alphanohtml')); ?>"
					placeholder="<?php echo dol_escape_htmltag($langs->trans("Login")); ?>">
			</div>

			<div class="b2b-form-group">
				<label for="password"><?php echo $langs->trans("Password"); ?></label>
				<input type="password" id="password" name="password" required autocomplete="current-password"
					placeholder="<?php echo dol_escape_htmltag($langs->trans("Password")); ?>">
			</div>

			<button type="submit" class="b2b-btn b2b-btn-primary b2b-btn-block">
				<?php echo $langs->trans("B2BOrderSignIn"); ?>
			</button>
		</form>
	</div>
</div>
