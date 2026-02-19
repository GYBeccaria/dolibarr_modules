<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2CStoreContext $context */
/** @var Conf $conf */
/** @var Translate $langs */

$portalTitle  = getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$cssPath      = dol_buildpath('/custom/b2cstore/public/css/b2cstore.css', 1);
$jsPath       = dol_buildpath('/custom/b2cstore/public/js/b2cstore.js', 1);
$baseUrl      = dol_buildpath('/custom/b2cstore/public/index.php', 1);
$getfileUrl   = dol_buildpath('/custom/b2cstore/public/getfile.php', 1);
$metaDesc     = getDolGlobalString('B2CSTORE_META_DESCRIPTION', '');

// Branding
$bsFavicon    = getDolGlobalString('B2CSTORE_FAVICON');
$bsPrimary    = preg_replace('/[^#a-fA-F0-9]/', '', getDolGlobalString('B2CSTORE_PRIMARY_COLOR', '#2563eb'));
$bsSecondary  = preg_replace('/[^#a-fA-F0-9]/', '', getDolGlobalString('B2CSTORE_SECONDARY_COLOR', '#1e40af'));
$bsAccent     = preg_replace('/[^#a-fA-F0-9]/', '', getDolGlobalString('B2CSTORE_ACCENT_COLOR', '#f59e0b'));
$bsBg         = preg_replace('/[^#a-fA-F0-9]/', '', getDolGlobalString('B2CSTORE_BG_COLOR', '#ffffff'));
$bsText       = preg_replace('/[^#a-fA-F0-9]/', '', getDolGlobalString('B2CSTORE_TEXT_COLOR', '#1f2937'));
$bsFont       = getDolGlobalString('B2CSTORE_FONT_FAMILY', 'Inter, system-ui, sans-serif');
$bsCustomCSS  = getDolGlobalString('B2CSTORE_CUSTOM_CSS', '');
?>
<!DOCTYPE html>
<html lang="<?php echo substr($langs->defaultlang, 0, 2); ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo dol_escape_htmltag($title ?? $portalTitle); ?></title>
	<?php if ($metaDesc) { ?><meta name="description" content="<?php echo dol_escape_htmltag($metaDesc); ?>"><?php } ?>
	<link rel="stylesheet" href="<?php echo $cssPath; ?>">
	<?php if ($bsFavicon) { ?>
	<link rel="icon" href="<?php echo $getfileUrl; ?>?f=favicon" type="image/<?php echo (strtolower(substr($bsFavicon, -3)) === 'ico') ? 'x-icon' : 'png'; ?>">
	<?php } ?>
	<style>
	:root {
		--b2cs-primary:   <?php echo $bsPrimary ?: '#2563eb'; ?>;
		--b2cs-secondary: <?php echo $bsSecondary ?: '#1e40af'; ?>;
		--b2cs-accent:    <?php echo $bsAccent ?: '#f59e0b'; ?>;
		--b2cs-bg:        <?php echo $bsBg ?: '#ffffff'; ?>;
		--b2cs-text:      <?php echo $bsText ?: '#1f2937'; ?>;
		--b2cs-font:      <?php echo dol_escape_htmltag($bsFont ?: 'Inter, system-ui, sans-serif'); ?>;
	}
	<?php if ($bsCustomCSS) { echo $bsCustomCSS."\n"; } ?>
	</style>
</head>
<body class="b2cs-portal<?php echo $context->isAuthenticated() ? ' b2cs-logged' : ' b2cs-guest'; ?>">

<?php if (!empty($context->errors)) { ?>
<div class="b2cs-alerts b2cs-alerts--error" role="alert">
	<?php foreach ($context->errors as $msg) { ?>
		<div class="b2cs-alert"><?php echo dol_escape_htmltag($msg); ?></div>
	<?php } ?>
</div>
<?php } ?>

<?php if (!empty($context->messages)) { ?>
<div class="b2cs-alerts b2cs-alerts--success" role="status">
	<?php foreach ($context->messages as $msg) { ?>
		<div class="b2cs-alert"><?php echo dol_escape_htmltag($msg); ?></div>
	<?php } ?>
</div>
<?php } ?>
