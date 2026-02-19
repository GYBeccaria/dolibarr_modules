<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2BOrderContext $context */
/** @var Conf $conf */
/** @var Translate $langs */

$portalTitle = getDolGlobalString('B2BORDER_PORTAL_TITLE', 'B2B Order Portal');
$cssPath = dol_buildpath('/custom/b2border/public/css/b2border.css', 1);
$jsPath = dol_buildpath('/custom/b2border/public/js/b2border.js', 1);
$baseUrl = dol_buildpath('/custom/b2border/public/index.php', 1);
$getfileUrl = dol_buildpath('/custom/b2border/public/getfile.php', 1);

// Branding
$b2bFavicon = getDolGlobalString('B2BORDER_FAVICON');
$b2bPrimaryColor = getDolGlobalString('B2BORDER_PRIMARY_COLOR');
$b2bPrimaryDarkColor = getDolGlobalString('B2BORDER_PRIMARY_DARK_COLOR');
$b2bCustomCSS = getDolGlobalString('B2BORDER_CUSTOM_CSS');
?>
<!DOCTYPE html>
<html lang="<?php echo substr($langs->defaultlang, 0, 2); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo dol_escape_htmltag($title ?? $portalTitle); ?></title>
	<link rel="stylesheet" href="<?php echo $cssPath; ?>">
	<?php if ($b2bFavicon) { ?>
	<link rel="icon" href="<?php echo $getfileUrl; ?>?f=favicon" type="image/<?php echo (substr($b2bFavicon, -3) == 'ico') ? 'x-icon' : 'png'; ?>">
	<?php } ?>
	<?php if ($b2bPrimaryColor || $b2bPrimaryDarkColor || $b2bCustomCSS) { ?>
	<style>
	<?php if ($b2bPrimaryColor || $b2bPrimaryDarkColor) { ?>
	:root {
		<?php if ($b2bPrimaryColor) { echo '--b2b-primary: '.preg_replace('/[^#a-fA-F0-9]/', '', $b2bPrimaryColor).';'."\n"; } ?>
		<?php if ($b2bPrimaryDarkColor) { echo '--b2b-primary-dark: '.preg_replace('/[^#a-fA-F0-9]/', '', $b2bPrimaryDarkColor).';'."\n"; } ?>
	}
	<?php } ?>
	<?php if ($b2bCustomCSS) { echo $b2bCustomCSS."\n"; } ?>
	</style>
	<?php } ?>
</head>
<body class="b2b-portal<?php echo $context->isAuthenticated() ? '' : ' b2b-login-page'; ?>">

<?php if (!empty($context->errors)) { ?>
<div class="b2b-messages b2b-errors">
	<?php foreach ($context->errors as $msg) { ?>
		<div class="b2b-msg b2b-msg-error"><?php echo dol_escape_htmltag($msg); ?></div>
	<?php } ?>
</div>
<?php } ?>

<?php if (!empty($context->messages)) { ?>
<div class="b2b-messages b2b-success">
	<?php foreach ($context->messages as $msg) { ?>
		<div class="b2b-msg b2b-msg-success"><?php echo dol_escape_htmltag($msg); ?></div>
	<?php } ?>
</div>
<?php } ?>
