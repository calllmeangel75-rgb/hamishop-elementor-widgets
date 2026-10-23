/**
 * HamiShop Elementor Widgets — product page behavior.
 * Vanilla JS, no dependencies, event delegation only.
 * State engine: color/storage selection → matching variation → price/stock
 * sync across purchase box + sticky bar → AJAX add-to-cart with fragments.
 */
(function () {
	'use strict';

	var D = window.hswProductData || {};
	var FA = { '0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴', '۵': '۵', '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹' };
	function fa(n) { return D.faDigits === false ? String(n) : String(n).replace(/[0-9]/g, function (d) { return FA[d]; }); }

	/* ---------- per-product state ---------- */
	var states = {};
	function state(pid) {
		if (!states[pid]) { states[pid] = { qty: 1, color: '', storage: '', variation: null, data: null }; }
		return states[pid];
	}
	function payload(root) {
		var raw = root.getAttribute('data-hsw-variations');
		if (!raw) { return null; }
		try { return JSON.parse(raw); } catch (e) { return null; }
	}
	function attrMap(root) {
		var raw = root.getAttribute('data-hsw-attrs');
		try { return raw ? JSON.parse(raw) : {}; } catch (e) { return {}; }
	}
	function roots(pid) {
		return document.querySelectorAll('.hsw-p[data-hsw-product="' + pid + '"], .hsw-p[data-hsw-variants="' + pid + '"]');
	}

	/* ---------- variation matching ---------- */
	function matchVariation(list, map, color, storage) {
		if (!list || !list.length) { return null; }
		var keys = [];
		if (map.color) { keys.push(map.color); }
		if (map.storage) { keys.push(map.storage); }
		var sel = {};
		if (map.color) { sel[map.color] = color; }
		if (map.storage) { sel[map.storage] = storage; }
		for (var i = 0; i < list.length; i++) {
			var v = list[i], ok = true;
			for (var k = 0; k < keys.length; k++) {
				var want = sel[keys[k]];
				var have = v.attrs && v.attrs[keys[k]];
				if (want && have && have !== want) { ok = false; break; }
			}
			if (ok) { return v; }
		}
		return list[0] || null;
	}

	/* ---------- render variant-dependent fields ---------- */
	function applyVariant(pid, variation) {
		var st = state(pid);
		st.variation = variation || null;
		roots(pid).forEach(function (root) {
			var cfp = root.hasAttribute('data-hsw-cfp') || root.getAttribute('data-hsw-cfp') === '1';
			var price = root.querySelector('[data-hsw-price]');
			var old = root.querySelector('[data-hsw-old]');
			var save = root.querySelector('[data-hsw-save]');
			var off = root.querySelector('[data-hsw-off]');
			if (variation && !cfp) {
				if (price) { price.textContent = variation.price; }
				if (old) {
					old.textContent = variation.regular;
					old.style.display = (variation.discount > 0) ? '' : 'none';
				}
				if (save) {
					save.style.display = (variation.discount > 0) ? '' : 'none';
				}
				if (off) {
					off.textContent = '٪' + fa(variation.discount) + '-';
					off.style.display = (variation.discount > 0) ? '' : 'none';
				}
				root.classList.toggle('is-oos', !variation.in_stock);
			}
			/* gallery image swap */
			if (variation && variation.image) {
				var img = root.querySelector('.hsw-pgal__img');
				if (img) { img.src = variation.image; }
			}
		});
		document.dispatchEvent(new CustomEvent('hsw:variant', { detail: { product: pid, variation: variation, state: st } }));
	}

	function refreshVariant(pid) {
		var st = state(pid);
		var any = document.querySelector('.hsw-p[data-hsw-variations][data-hsw-product="' + pid + '"], .hsw-p[data-hsw-variations][data-hsw-variants="' + pid + '"]');
		if (!any) { return; }
		var list = payload(any);
		var map = attrMap(any);
		applyVariant(pid, matchVariation(list, map, st.color, st.storage));
	}

	/* ---------- reviews ---------- */
	function openReviews() {
		var tabs = document.querySelector('.hsw-ptabs');
		if (tabs && window.getComputedStyle(tabs).display !== 'none') {
			var btn = tabs.querySelector('.hsw-ptabs__btn[data-hsw-tab="comments"]');
			var panel = tabs.querySelector('.hsw-ptabs__panel[data-hsw-panel="comments"]');
			if (btn && panel) {
				tabs.querySelectorAll('.hsw-ptabs__btn').forEach(function (b) { b.classList.toggle('is-active', b === btn); });
				tabs.querySelectorAll('.hsw-ptabs__panel').forEach(function (p) { p.classList.toggle('is-active', p === panel); });
				scrollTo(panel);
				return;
			}
		}
		var item = document.querySelector('.hsw-pacc [data-hsw-acc-item="reviews"]');
		if (item) {
			item.classList.add('is-open');
			scrollTo(item);
			return;
		}
		var fallback = document.querySelector('#reviews');
		if (fallback) { scrollTo(fallback); }
	}
	function focusReviewForm(trigger) {
		var scope = trigger.closest('[data-hsw-panel="comments"], [data-hsw-acc-item="reviews"]') || document;
		var form = scope.querySelector('.hsw-review-form');
		if (!form) { openReviews(); return; }
		scrollTo(form);
		var textarea = form.querySelector('textarea[name="comment"]');
		if (textarea) {
			setTimeout(function () {
				try { textarea.focus({ preventScroll: true }); } catch (e) { textarea.focus(); }
			}, 250);
		}
	}
	function scrollTo(el) {
		if (!el) { return; }
		if (el.scrollIntoView) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
	}

	/* ================= CLICK DELEGATION ================= */
	document.addEventListener('click', function (e) {
		var t = e.target;

		/* --- tabs --- */
		var tabBtn = t.closest('.hsw-ptabs__btn');
		if (tabBtn) {
			var tabsRoot = tabBtn.closest('.hsw-ptabs');
			var id = tabBtn.getAttribute('data-hsw-tab');
			tabsRoot.querySelectorAll('.hsw-ptabs__btn').forEach(function (b) { b.classList.toggle('is-active', b === tabBtn); });
			tabsRoot.querySelectorAll('.hsw-ptabs__panel').forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-hsw-panel') === id); });
			return;
		}

		/* --- product rating link → open the reviews tab / accordion --- */
		var reviewLink = t.closest('a[href="#reviews"], .hsw-ptitle__revlink');
		if (reviewLink) {
			e.preventDefault();
			openReviews();
			return;
		}

		/* --- "add a review" link → scroll to the real form --- */
		var addReview = t.closest('[data-hsw-add-review]');
		if (addReview) {
			e.preventDefault();
			focusReviewForm(addReview);
			return;
		}

		/* --- accordion --- */
		var acc = t.closest('[data-hsw-acc]');
		if (acc) {
			acc.closest('.hsw-pacc__item').classList.toggle('is-open');
			return;
		}

		/* --- gallery thumbs / dots --- */
		var thumb = t.closest('.hsw-pgal__thumb, .hsw-pgal__dot');
		if (thumb) {
			var gal = thumb.closest('.hsw-pgal');
			var src = thumb.getAttribute('data-hsw-src');
			var img = gal && gal.querySelector('.hsw-pgal__img');
			gal.querySelectorAll('.hsw-pgal__thumb, .hsw-pgal__dot').forEach(function (b) { b.classList.toggle('is-active', b === thumb); });
			if (img && src) { img.src = src; }
			return;
		}

		/* --- zoom --- */
		var zoomBtn = t.closest('[data-hsw-zoom]');
		if (zoomBtn) {
			var scope = zoomBtn.closest('.hsw-p');
			var main = scope && scope.querySelector('.hsw-pgal__img');
			if (main) { openZoom(main.currentSrc || main.src); }
			return;
		}
		if (t.closest('.hsw-zoom')) { closeZoom(); return; }

		/* --- wishlist (visual toggle) --- */
		var wish = t.closest('[data-hsw-wish]');
		if (wish) {
			var ic = wish.querySelector('.hsw-ic');
			var on = wish.classList.toggle('is-active');
			if (ic) { window.hswFill(ic, on); ic.style.color = on ? 'var(--hsw-secondary)' : ''; }
			return;
		}

		/* --- color swatch --- */
		var sw = t.closest('.hsw-pvar__swatch, .hsw-pvar__mswatch');
		if (sw) {
			var vr = sw.closest('.hsw-pvar');
			var pid = vr.getAttribute('data-hsw-variants') || vr.getAttribute('data-hsw-product');
			vr.querySelectorAll('.hsw-pvar__swatch, .hsw-pvar__mswatch').forEach(function (b) { b.classList.toggle('is-active', b === sw); });
			var st = state(pid);
			st.color = sw.getAttribute('data-hsw-color') || '';
			var colorLabel = sw.getAttribute('data-hsw-label') || st.color;
			vr.querySelectorAll('[data-hsw-selname="color"]').forEach(function (el) { el.textContent = colorLabel; });
			refreshVariant(pid);
			return;
		}

		/* --- storage chip --- */
		var chip = t.closest('.hsw-pvar__chip, .hsw-pvar__mchip');
		if (chip) {
			var vr2 = chip.closest('.hsw-pvar');
			var pid2 = vr2.getAttribute('data-hsw-variants') || vr2.getAttribute('data-hsw-product');
			vr2.querySelectorAll('.hsw-pvar__chip, .hsw-pvar__mchip').forEach(function (b) { b.classList.toggle('is-active', b === chip); });
			var st2 = state(pid2);
			st2.storage = chip.getAttribute('data-hsw-storage') || '';
			vr2.querySelectorAll('[data-hsw-selname="storage"]').forEach(function (el) { el.textContent = st2.storage; });
			refreshVariant(pid2);
			return;
		}

		/* --- quantity --- */
		var qb = t.closest('[data-hsw-qty]');
		if (qb) {
			var scope2 = qb.closest('.hsw-p');
			var pid3 = scope2.getAttribute('data-hsw-product');
			var st3 = state(pid3);
			var delta = parseInt(qb.getAttribute('data-hsw-qty'), 10) || 0;
			setQty(pid3, st3.qty + delta);
			return;
		}

		/* --- add to cart --- */
		var addBtn = t.closest('[data-hsw-add]');
		if (addBtn) {
			var scope3 = addBtn.closest('.hsw-p');
			if (!scope3 || scope3.hasAttribute('data-hsw-cfp')) { return; }
			var pid4 = scope3.getAttribute('data-hsw-product');
			var st4 = state(pid4);
			addToCart(addBtn, pid4, st4);
			return;
		}
	});

	/* ---------- quantity ---------- */

	/* Persian/Arabic digits are what a Persian keyboard produces. */
	function toLatin(value) {
		return String(value)
			.replace(/[\u06F0-\u06F9]/g, function (d) { return String(d.charCodeAt(0) - 0x06F0); })
			.replace(/[\u0660-\u0669]/g, function (d) { return String(d.charCodeAt(0) - 0x0660); })
			.replace(/[^\d]/g, '');
	}

	/* The stepper and the typed field share one source of truth: state().qty. */
	function setQty(pid, value) {
		var st = state(pid);
		var qty = parseInt(value, 10);
		if (isNaN(qty)) { qty = 1; }
		st.qty = Math.max(1, Math.min(99, qty));

		roots(pid).forEach(function (r) {
			r.querySelectorAll('[data-hsw-qval]').forEach(function (el) {
				if ('INPUT' === el.tagName) {
					el.value = fa(st.qty);
				} else {
					el.textContent = fa(st.qty);
				}
			});
		});
		return st.qty;
	}

	function commitQty(el) {
		var scope = el.closest('.hsw-p');
		if (!scope) { return; }
		var pid = scope.getAttribute('data-hsw-product') || scope.getAttribute('data-hsw-variants');
		if (!pid) { return; }
		var raw = toLatin(el.value);
		var qty = raw === '' ? 1 : parseInt(raw, 10);
		if (isNaN(qty) || qty < 1) { qty = 1; }
		setQty(pid, qty);
	}

	document.addEventListener('keydown', function (e) {
		var el = e.target;
		if (!el || !el.hasAttribute || !el.hasAttribute('data-hsw-qval')) { return; }
		if ('Enter' === e.key) {
			e.preventDefault();
			commitQty(el);
			el.blur();
		}
	});

	document.addEventListener('change', function (e) {
		var el = e.target;
		if (!el || !el.hasAttribute || !el.hasAttribute('data-hsw-qval')) { return; }
		commitQty(el);
	});

	document.addEventListener('focusin', function (e) {
		var el = e.target;
		if (el && el.hasAttribute && el.hasAttribute('data-hsw-qval') && 'INPUT' === el.tagName) {
			el.select();
		}
	});

	/* ================= ADD TO CART ================= */

	function addToCart(btn, pid, st) {
		if (document.body.classList.contains('elementor-editor-active')) { flashAdded(btn); return; }
		if (!pid || btn.classList.contains('is-busy')) { return; }
		btn.classList.add('is-busy');

		var body = new URLSearchParams();
		body.set('action', 'hsw_add_to_cart'); /* unused; wc-ajax endpoint ignores action */
		body.set('product_id', st.variation && st.variation.id ? st.variation.id : pid);
		if (st.variation && st.variation.id) { body.set('variation_id', st.variation.id); }
		body.set('quantity', st.qty);

		var url = D.wcAjax || '/?wc-ajax=add_to_cart';
		fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (r) { return r.json(); }).then(function (res) {
			btn.classList.remove('is-busy');
			if (res && res.error && res.product_url) { window.location = res.product_url; return; }
			flashAdded(btn);
			showAdded(btn, pid, res);
			if (res && res.fragments) {
				Object.keys(res.fragments).forEach(function (key) {
					var nodes = document.querySelectorAll(key);
					nodes.forEach(function (n) { n.outerHTML = res.fragments[key]; });
				});
				/* Keep the dimmed "empty cart" styling in sync with the new count. */
				document.querySelectorAll('.hsw-hd__cart').forEach(function (cart) {
					var badge = cart.querySelector('[data-hsw-cart-count]');
					var n = badge ? parseInt(badge.getAttribute('data-count') || '0', 10) : 0;
					cart.classList.toggle('is-empty', !n);
				});
				document.body.dispatchEvent(new Event('wc_fragment_refresh'));
			}
		}).catch(function () {
			btn.classList.remove('is-busy');
		});
	}
	function flashAdded(btn) {
		btn.classList.add('is-added');
		setTimeout(function () { btn.classList.remove('is-added'); }, 2000);
	}
	/*
	 * Success feedback. The in-widget toast only exists inside the sticky bar,
	 * so on desktop (where that bar is hidden) adding to the cart gave no
	 * feedback at all. A page-level toast is created on demand as a fallback.
	 */
	/*
	 * Feedback is owned by the shared module (assets/js/hsw-feedback.js).
	 * The product page used to carry its own toast plus an injected stylesheet;
	 * that is now one system, so the archive, the loop and the product page
	 * cannot disagree and the toast and the cart modal never both appear.
	 */
	function showAdded(btn, pid, res) {
		/*
		 * The cart modal is opt-in on this page (HamiShop → کنترل ویجت‌ها):
		 * on a product page the visitor is already looking at what they added,
		 * so a dialog over the gallery is noise. Default is a plain toast.
		 */
		if (window.HSWFeedback && D.modal) {
			window.HSWFeedback.addedToCart({
				fragments: res && res.fragments,
				cartUrl: D.cartUrl,
				message: D.addedText,
				labels: {
					modalTitle: D.modalTitle,
					viewCart: D.viewCart,
					continue: D.continueText,
					close: D.closeText,
					added: D.addedText
				}
			});
			return;
		}
		if (btn) { flashAdded(btn); }
		if (window.HSWFeedback && D.addedText) {
			window.HSWFeedback.toast(D.addedText);
		}
	}

	/* ================= ZOOM OVERLAY ================= */
	var zoomEl = null;
	function openZoom(src) {
		if (!zoomEl) {
			zoomEl = document.createElement('div');
			zoomEl.className = 'hsw-zoom';
			zoomEl.innerHTML = '<img alt="">';
			document.body.appendChild(zoomEl);
		}
		zoomEl.querySelector('img').src = src;
		zoomEl.classList.add('is-open');
	}
	function closeZoom() { if (zoomEl) { zoomEl.classList.remove('is-open'); } }
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeZoom(); } });

	/* ================= INIT ================= */
	function init() {
		/* seed variation payload roots */
		document.querySelectorAll('.hsw-p[data-hsw-variations]').forEach(function (root) {
			var pid = root.getAttribute('data-hsw-product') || root.getAttribute('data-hsw-variants');
			var st = state(pid);
			st.data = payload(root);
			/* preselect first active swatch/chip labels */
			var sw = root.querySelector('.hsw-pvar__swatch.is-active, .hsw-pvar__mswatch.is-active');
			var ch = root.querySelector('.hsw-pvar__chip.is-active, .hsw-pvar__mchip.is-active');
			if (sw) { st.color = sw.getAttribute('data-hsw-color') || ''; }
			if (ch) { st.storage = ch.getAttribute('data-hsw-storage') || ''; }
			applyVariant(pid, matchVariation(st.data, attrMap(root), st.color, st.storage));
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
	/* Elementor editor re-renders */
	if (window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', function () {
			window.elementorFrontend && window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function () { init(); });
		});
	}
})();
