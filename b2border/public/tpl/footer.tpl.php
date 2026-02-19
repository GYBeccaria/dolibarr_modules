<?php
/* Copyright (C) 2025 Henaxis */
/** @var B2BOrderContext $context */
$jsPath = dol_buildpath('/custom/b2border/public/js/b2border.js', 1);

$footerText = getDolGlobalString('B2BORDER_FOOTER_TEXT');
$hidePoweredBy = getDolGlobalInt('B2BORDER_HIDE_POWERED_BY');
$portalTitleFooter = getDolGlobalString('B2BORDER_PORTAL_TITLE', 'B2B Order Portal');

if ($context->isAuthenticated()) {
	// Close main and container opened in menu.tpl.php
	echo '</div>'; // .b2b-container
	echo '</main>'; // .b2b-main
}
?>
<footer class="b2b-footer">
	<div class="b2b-container">
		<?php if ($footerText) { ?>
		<p><?php echo dol_escape_htmltag($footerText); ?></p>
		<?php } else { ?>
		<p>&copy; <?php echo date('Y'); ?> - <?php echo dol_escape_htmltag($portalTitleFooter); ?></p>
		<?php } ?>
		<?php if (!$hidePoweredBy) { ?>
		<p class="b2b-footer-powered">Powered by <a href="https://www.dolibarr.org" target="_blank" rel="noopener">Dolibarr</a></p>
		<?php } ?>
	</div>
</footer>
<?php if ($context->isAuthenticated()) { ?>
<script src="<?php echo $jsPath; ?>"></script>
<?php } ?>
</body>
</html>
