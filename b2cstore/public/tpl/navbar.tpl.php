<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2CStoreContext $context */
/** @var Translate $langs */

$portalTitle = getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$baseUrl     = dol_buildpath('/custom/b2cstore/public/index.php', 1);
$getfileUrl  = dol_buildpath('/custom/b2cstore/public/getfile.php', 1);
$logoFile    = getDolGlobalString('B2CSTORE_LOGO');

// Cart count for badge
$cartCount = 0;
if ($context->isAuthenticated() || !$context->cartRequiresLogin()) {
	$cart = new B2CStoreCart($db);
	$cartCount = $cart->getCount();
}
?>
<header class="b2cs-header">
	<nav class="b2cs-nav container">
		<a href="<?php echo $baseUrl; ?>?controller=home" class="b2cs-logo" aria-label="<?php echo dol_escape_htmltag($portalTitle); ?>">
			<?php if ($logoFile) { ?>
				<img src="<?php echo $getfileUrl; ?>?f=logo" alt="<?php echo dol_escape_htmltag($portalTitle); ?>" class="b2cs-logo__img">
			<?php } else { ?>
				<span class="b2cs-logo__text"><?php echo dol_escape_htmltag($portalTitle); ?></span>
			<?php } ?>
		</a>

		<button class="b2cs-nav__toggle" aria-label="Menu" aria-expanded="false" aria-controls="b2cs-menu">
			<span></span><span></span><span></span>
		</button>

		<ul class="b2cs-nav__menu" id="b2cs-menu" role="menubar">
			<li role="none"><a href="<?php echo $baseUrl; ?>?controller=home" class="b2cs-nav__link" role="menuitem"><?php echo $langs->trans('B2CStoreHome'); ?></a></li>
			<li role="none"><a href="<?php echo $baseUrl; ?>?controller=catalog" class="b2cs-nav__link" role="menuitem"><?php echo $langs->trans('B2CStoreCatalog'); ?></a></li>
			<?php if (getDolGlobalInt('B2CSTORE_SECTION_ABOUT_ENABLED', 1)) { ?>
			<li role="none"><a href="<?php echo $baseUrl; ?>?controller=page&slug=about" class="b2cs-nav__link" role="menuitem"><?php echo dol_escape_htmltag(getDolGlobalString('B2CSTORE_SECTION_ABOUT_TITLE') ?: $langs->trans('B2CStoreAbout')); ?></a></li>
			<?php } ?>
			<li role="none"><a href="<?php echo $baseUrl; ?>?controller=contact" class="b2cs-nav__link" role="menuitem"><?php echo $langs->trans('B2CStoreContact'); ?></a></li>
			<?php if ($context->isAuthenticated()) { ?>
			<li role="none">
				<a href="<?php echo $baseUrl; ?>?controller=cart" class="b2cs-nav__link b2cs-nav__cart" role="menuitem">
					<span class="b2cs-cart-icon">🛒</span>
					<?php if ($cartCount > 0) { ?><span class="b2cs-cart-badge"><?php echo $cartCount; ?></span><?php } ?>
				</a>
			</li>
			<li role="none" class="b2cs-nav__user">
				<span class="b2cs-nav__username"><?php echo dol_escape_htmltag($context->logged_thirdparty->name ?? ''); ?></span>
				<a href="<?php echo dol_buildpath('/custom/b2cstore/public/logout.php', 1); ?>" class="b2cs-nav__link"><?php echo $langs->trans('B2CStoreLogout'); ?></a>
			</li>
			<?php } else { ?>
			<li role="none"><a href="<?php echo $baseUrl; ?>?controller=login" class="b2cs-nav__link"><?php echo $langs->trans('B2CStoreLogin'); ?></a></li>
			<?php if (getDolGlobalInt('B2CSTORE_ENABLE_REGISTRATION', 1)) { ?>
			<li role="none"><a href="<?php echo $baseUrl; ?>?controller=register" class="b2cs-nav__link b2cs-nav__link--cta"><?php echo $langs->trans('B2CStoreRegister'); ?></a></li>
			<?php } } ?>
		</ul>
	</nav>
</header>
<main class="b2cs-main">
