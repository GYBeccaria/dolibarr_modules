<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2CStoreContext $context */
/** @var Translate $langs */

$footerText    = getDolGlobalString('B2CSTORE_FOOTER_TEXT', '');
$hidePoweredBy = getDolGlobalInt('B2CSTORE_HIDE_POWERED_BY', 0);
$year          = date('Y');
$portalTitle   = getDolGlobalString('B2CSTORE_PORTAL_TITLE', 'Il Nostro Negozio');
$jsPath        = dol_buildpath('/custom/b2cstore/public/js/b2cstore.js', 1);
?>
</main><!-- /.b2cs-main -->

<footer class="b2cs-footer">
	<div class="container b2cs-footer__inner">
		<p class="b2cs-footer__copy">
			<?php if ($footerText) {
				echo $footerText; // HTML allowed (admin-configured)
			} else {
				echo '&copy; '.dol_escape_htmltag($year.' '.$portalTitle);
			} ?>
		</p>
		<?php if (!$hidePoweredBy) { ?>
		<p class="b2cs-footer__powered">Powered by <a href="https://www.dolibarr.org" target="_blank" rel="noopener">Dolibarr</a></p>
		<?php } ?>
	</div>
</footer>

<script src="<?php echo $jsPath; ?>"></script>
</body>
</html>
