/**
 * HamiShop Offer — rail controls and countdowns.
 *
 * One interval updates every timer on the page. Elementor re-renders widgets in
 * the editor, so initialization is idempotent and hooked into Elementor's own
 * frontend event when it is available.
 */
(function () {
	'use strict';

	var persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
	var timers = new Set();

	function fa(value) {
		return String(value).replace(/[0-9]/g, function (digit) {
			return persian[Number(digit)];
		});
	}

	function pad(value) {
		return String(value).padStart(2, '0');
	}

	function initRail(root) {
		var rail = root.querySelector('[data-hsw-offer-rail]');
		if (!rail) {
			return;
		}

		var buttons = root.querySelectorAll('[data-hsw-offer-dir]');
		buttons.forEach(function (button) {
			button.addEventListener('click', function () {
				var card = rail.querySelector('.hsw-offer__card');
				var gap = parseFloat(getComputedStyle(rail).columnGap || '14') || 14;
				var step = card ? card.getBoundingClientRect().width + gap : 260;
				var direction = Number(button.getAttribute('data-hsw-offer-dir')) || 1;
				var rtl = getComputedStyle(rail).direction === 'rtl';
				var delta = direction * step * (rtl ? -1 : 1);

				rail.scrollBy({ left: delta, behavior: 'smooth' });
			});
		});
	}

	function initTimer(root) {
		root.querySelectorAll('[data-hsw-offer-end]').forEach(function (element) {
			timers.add(element);
		});
	}

	function tick() {
		var now = Math.floor(Date.now() / 1000);

		timers.forEach(function (element) {
			if (!element.isConnected) {
				timers.delete(element);
				return;
			}

			var end = parseInt(element.getAttribute('data-hsw-offer-end'), 10) || 0;
			var remaining = end - now;

			if (remaining <= 0) {
				element.classList.add('is-ended');

				if (element.classList.contains('hsw-offer__countdown')) {
					var endedText = element.getAttribute('data-hsw-offer-ended-text') || '';
					element.textContent = endedText;
				} else {
					element.hidden = true;
				}
				return;
			}

			var hours = Math.floor(remaining / 3600);
			var minutes = Math.floor((remaining % 3600) / 60);
			var seconds = remaining % 60;
			var values = { h: hours, m: minutes, s: seconds };

			Object.keys(values).forEach(function (unit) {
				var node = element.querySelector('[data-hsw-offer-unit="' + unit + '"]');
				if (node) {
					node.textContent = fa(pad(values[unit]));
				}
			});
		});
	}

	function init(root) {
		if (!root || root.getAttribute('data-hsw-offer-ready') === '1') {
			return;
		}

		root.setAttribute('data-hsw-offer-ready', '1');
		initRail(root);
		initTimer(root);
	}

	function initAll() {
		document.querySelectorAll('[data-hsw-offer]').forEach(init);
	}

	window.addEventListener('load', initAll);
	document.addEventListener('DOMContentLoaded', initAll);

	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction('frontend/element_ready/hsw-offer.default', function (scope) {
			init(scope && scope[0] ? scope[0] : scope);
		});
	}

	window.setInterval(tick, 1000);
	tick();
})();
