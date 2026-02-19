<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2BOrderContext $context */
/** @var Translate $langs */

$baseUrl = dol_buildpath('/custom/b2border/public/index.php', 1);
$logoutUrl = dol_buildpath('/custom/b2border/public/logout.php', 1);
$portalTitle = getDolGlobalString('B2BORDER_PORTAL_TITLE', 'B2B Order Portal');
$b2bLogo = getDolGlobalString('B2BORDER_LOGO');
$getfileUrl = dol_buildpath('/custom/b2border/public/getfile.php', 1);

// Cart item count
$cartCount = 0;
if (!empty($_SESSION['b2border_cart']['items'])) {
	$cartCount = count($_SESSION['b2border_cart']['items']);
}

$currentController = GETPOST('controller', 'alphanohtml');
if (empty($currentController)) {
	$currentController = 'catalog';
}
?>
<header class="b2b-header">
	<div class="b2b-container">
		<div class="b2b-header-inner">
			<a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2b-logo">
				<?php if ($b2bLogo) { ?>
				<img src="<?php echo $getfileUrl; ?>?f=logo" alt="<?php echo dol_escape_htmltag($portalTitle); ?>" class="b2b-logo-img">
				<?php } else { echo dol_escape_htmltag($portalTitle); } ?>
			</a>
			<button class="b2b-menu-toggle" aria-label="Menu" onclick="document.querySelector('.b2b-nav').classList.toggle('open')">&#9776;</button>
			<nav class="b2b-nav">
				<a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2b-nav-link<?php echo $currentController == 'catalog' ? ' active' : ''; ?>">
					<?php echo $langs->trans("B2BOrderCatalog"); ?>
				</a>
				<a href="<?php echo $baseUrl; ?>?controller=cart" class="b2b-nav-link b2b-cart-link<?php echo $currentController == 'cart' ? ' active' : ''; ?>">
					<?php echo $langs->trans("B2BOrderCart"); ?>
					<?php if ($cartCount > 0) { ?>
						<span class="b2b-cart-badge" id="cart-badge"><?php echo $cartCount; ?></span>
					<?php } ?>
				</a>
				<span class="b2b-nav-user">
					<?php echo dol_escape_htmltag($context->logged_thirdparty->name); ?>
				</span>
				<a href="<?php echo $logoutUrl; ?>" class="b2b-nav-link b2b-logout"><?php echo $langs->trans("Logout"); ?></a>
			</nav>
		</div>
	</div>
</header>
<main class="b2b-main">
<div class="b2b-container">
