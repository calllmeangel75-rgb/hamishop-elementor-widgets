/**
 * HamiShop Elementor Widgets — frontend behavior.
 * Vanilla JS, no dependencies. ~4KB. Event delegation only.
 */
(function () {
	'use strict';

	var trackSel = '.hsw-track';

	/* ---------- Persian digits for countdown ---------- */
	var FA = { '0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴', '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹' };
	function fa(n) { return String(n).replace(/[0-9]/g, function (d) { return FA[d]; }); }
	function pad(n) { return (n < 10 ? '0' : '') + n; }

	/* ---------- Carousel arrows ---------- */
	function scrollStep(track) {
		var card = track.querySelector('.hsw-card, .hsw-lead');
		var gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap) || 12;
		var w = card ? card.getBoundingClientRect().width : 200;
		return (w + gap) * (window.innerWidth >= 768 ? 2 : 1);
	}
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.hsw-arrow');
		if (!btn) { return; }
		var root = btn.closest('.hsw-loop');
		var track = root && root.querySelector(trackSel);
		if (!track) { return; }
		var rtl = getComputedStyle(track).direction === 'rtl';
		var dir = parseInt(btn.getAttribute('data-hsw-dir'), 10) || 1; // 1 = next
		var delta = scrollStep(track) * dir * (rtl ? -1 : 1);
		track.scrollBy({ left: delta, behavior: 'smooth' });
	});

	/* ---------- Countdown ---------- */
	function tickAll() {
		var nodes = document.querySelectorAll('[data-hsw-end]');
		if (!nodes.length) { return; }
		var now = Date.now();
		nodes.forEach(function (el) {
			var end = parseInt(el.getAttribute('data-hsw-end'), 10) * 1000;
			var diff = Math.max(0, Math.floor((end - now) / 1000));
			var h = Math.floor(diff / 3600);
			var m = Math.floor((diff % 3600) / 60);
			var s = diff % 60;
			var faMode = el.getAttribute('data-hsw-fa') === '1';
			var fmt = function (v) { return faMode ? fa(pad(v)) : pad(v); };
			el.querySelectorAll('[data-hsw-cd]').forEach(function (out) {
				var k = out.getAttribute('data-hsw-cd');
				out.textContent = fmt(k === 'h' ? h : k === 'm' ? m : s);
			});
			var full = el.querySelector('.hsw-cd__val');
			if (full) { full.textContent = fmt(h) + ':' + fmt(m) + ':' + fmt(s); }
		});
	}
	tickAll();
	/* Tick only while countdowns exist and the tab is visible. */
	var tickTimer = null;
	function startTick() {
		if (tickTimer || !document.querySelector('[data-hsw-end]')) { return; }
		tickTimer = setInterval(tickAll, 1000);
	}
	function stopTick() {
		if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
	}
	document.addEventListener('visibilitychange', function () {
		if (document.hidden) { stopTick(); } else { tickAll(); startTick(); }
	});
	if (!document.hidden) { startTick(); }

	/* ---------- Toast ---------- */
	var toastEl = null;
	function toast(msg) {
		if (!toastEl) {
			toastEl = document.createElement('div');
			toastEl.className = 'hsw-toast';
			document.body.appendChild(toastEl);
		}
		toastEl.textContent = msg;
		toastEl.classList.add('is-show');
		clearTimeout(toastEl._t);
		toastEl._t = setTimeout(function () { toastEl.classList.remove('is-show'); }, 2200);
	}

	/* ---------- Tabs (Section 3) ---------- */
	var fragCache = {};
	document.addEventListener('click', function (e) {
		var tab = e.target.closest('.hsw-tab');
		if (!tab) { return; }
		var root = tab.closest('.hsw-loop');
		if (!root) { return; }
		var tabs = root.querySelectorAll('.hsw-tab');
		tabs.forEach(function (t) { t.classList.remove('is-active'); });
		tab.classList.add('is-active');

		var track = root.querySelector(trackSel);
		if (!track) { return; }
		var payload = tab.getAttribute('data-hsw-query');
		if (!payload) { return; }

		if (fragCache[payload]) {
			track.innerHTML = fragCache[payload];
			return;
		}
		var cfg = root.getAttribute('data-hsw-endpoint');
		if (!cfg) { return; }
		track.classList.add('is-busy');
		fetch(cfg + (cfg.indexOf('?') > -1 ? '&' : '?') + payload)
			.then(function (r) { return r.json(); })
			.then(function (json) {
				track.classList.remove('is-busy');
				if (json && json.html) {
					fragCache[payload] = json.html;
					track.innerHTML = json.html;
				}
			})
			.catch(function () { track.classList.remove('is-busy'); });
	});

	/* ---------- AJAX add to cart ---------- */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.hsw-add[data-product_id]');
		if (!btn) { return; }
		e.preventDefault();
		var id = btn.getAttribute('data-product_id');
		if (!id || btn.classList.contains('is-busy')) { return; }
		btn.classList.add('is-busy');

		var body = 'product_id=' + encodeURIComponent(id) + '&quantity=1';
		fetch(hswData && hswData.wcAjax ? hswData.wcAjax : '/?wc-ajax=add_to_cart', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body
		})
			.then(function (r) { return r.json(); })
			.then(function (res) {
				btn.classList.remove('is-busy');
				if (res && res.error && res.product_url) { window.location.href = res.product_url; return; }
				/* Let WooCommerce core refresh cart fragments everywhere */
				if (window.jQuery) { window.jQuery(document.body).trigger('wc_fragment_refresh'); }
				if (hswData && hswData.addedText) { toast(hswData.addedText); }
				document.dispatchEvent(new CustomEvent('hsw_added_to_cart', { detail: { productId: id } }));
			})
			.catch(function () { btn.classList.remove('is-busy'); });
	});
})();
