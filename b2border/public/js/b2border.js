/**
 * B2B Order Portal - Cart interactions
 * Copyright (C) 2025 Henaxis
 */
(function() {
	'use strict';

	// Auto-close messages after 5 seconds
	var messages = document.querySelectorAll('.b2b-msg');
	messages.forEach(function(msg) {
		setTimeout(function() {
			msg.style.transition = 'opacity 0.3s';
			msg.style.opacity = '0';
			setTimeout(function() { msg.remove(); }, 300);
		}, 5000);
	});

	// Close mobile menu when clicking a link
	var navLinks = document.querySelectorAll('.b2b-nav-link');
	navLinks.forEach(function(link) {
		link.addEventListener('click', function() {
			var nav = document.querySelector('.b2b-nav');
			if (nav) nav.classList.remove('open');
		});
	});

	// Close mobile menu when clicking outside
	document.addEventListener('click', function(e) {
		var nav = document.querySelector('.b2b-nav');
		var toggle = document.querySelector('.b2b-menu-toggle');
		if (nav && nav.classList.contains('open') && !nav.contains(e.target) && e.target !== toggle) {
			nav.classList.remove('open');
		}
	});
})();
