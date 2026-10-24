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
		if (!states[pid]) { states[pid] = { qty: 1, color: '', storage: '', chips: {}, variation: null, data: null, cartQty: 0, cartVariationId: 0 }; }
		if (!states[pid].chips) { states[pid].chips = {}; }
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
	/*
	 * One selection map covers every picker: the colour role, the primary chip
	 * group (legacy `storage`) and any number of extra chip groups (`chips`).
	 * The variation with the most matching attributes wins; a variation that
	 * contradicts a selection is never chosen.
	 */
	function matchVariation(list, map, st) {
		if (!list || !list.length) { return null; }
		st = st || {};

		var sel = {};
		if (map.color) { sel[map.color] = st.color; }
		if (map.storage) { sel[map.storage] = st.storage; }
		(map.chips || []).forEach(function (tax) {
			var value = st.chips && st.chips[tax];
			if (value) { sel[tax] = value; }
		});

		var keys = Object.keys(sel).filter(function (k) { return !!sel[k]; });
		var best = null, bestScore = -1;

		for (var i = 0; i < list.length; i++) {
			var v = list[i], score = 0, ok = true;
			for (var k = 0; k < keys.length; k++) {
				var want = sel[keys[k]];
				var have = v.attrs && v.attrs[keys[k]];
				if (!have) { continue; }            // wildcard / not part of this variation
				if (have === want) { score++; } else { ok = false; break; }
			}
			if (!ok) { continue; }
			if (score > bestScore) { bestScore = score; best = v; }
			if (score === keys.length) { break; }
		}
		return best || list[0] || null;
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
					if (variation.save_html) { save.textContent = variation.save_html; }
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

			/*
			 * The stepper belongs to the selected variation, not to the
			 * product: switching to a variation that is not in the cart must
			 * close it again (and seed it with the real line quantity when it
			 * is).
			 */
			if (variation) {
				/*
				 * The line belongs to one variation. Switching to a different
				 * one must close the stepper even if the product (or another
				 * variation) is in the cart.
				 */
				var own = (st.cartVariationId && variation.id === st.cartVariationId) ? (st.cartQty || 0) : 0;
				var cq  = Math.max(parseInt(variation.cart_qty || 0, 10) || 0, own);
				root.classList.toggle('is-not-in-cart', cq <= 0);
				root.setAttribute('data-hsw-in-cart', String(cq));
				/* The input and the bin/minus state must follow the variation too. */
				setQty(pid, cq > 0 ? cq : 1);
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
		applyVariant(pid, matchVariation(list, map, st));
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
			/* Keep the chosen thumbnail centred in its own carousel row. */
			if (thumb.classList.contains('hsw-pgal__thumb') && thumb.scrollIntoView) {
				thumb.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
			}
			return;
		}

		/* --- zoom / lightbox --- */
		var zoomBtn = t.closest('[data-hsw-zoom]');
		if (zoomBtn) {
			var scope = zoomBtn.closest('.hsw-p');
			var gal   = scope && scope.querySelector('.hsw-pgal');
			var main  = scope && scope.querySelector('.hsw-pgal__img');
			var src   = main ? (main.currentSrc || main.src) : '';
			var list  = [];
			try {
				list = JSON.parse(gal ? (gal.getAttribute('data-hsw-images') || '[]') : '[]');
			} catch (e) { list = []; }
			if (!Array.isArray(list) || !list.length) { list = src ? [src] : []; }
			var idx = list.indexOf(src);
			openZoom(list, idx < 0 ? 0 : idx);
			return;
		}

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
			var st = state(pid);
			st.color = sw.getAttribute('data-hsw-color') || '';
			var colorLabel = sw.getAttribute('data-hsw-label') || st.color;
			/* Desktop and mobile render the same list: keep both in sync. */
			vr.querySelectorAll('[data-hsw-color]').forEach(function (b) {
				b.classList.toggle('is-active', b.getAttribute('data-hsw-color') === st.color);
			});
			vr.querySelectorAll('[data-hsw-selname="color"]').forEach(function (el) { el.textContent = colorLabel; });
			refreshVariant(pid);
			return;
		}

		/* --- attribute chips (ram, ssd, size, …) --- */
		var chip = t.closest('.hsw-pvar__chip, .hsw-pvar__mchip');
		if (chip) {
			var vr2 = chip.closest('.hsw-pvar');
			var pid2 = vr2.getAttribute('data-hsw-variants') || vr2.getAttribute('data-hsw-product');
			var tax = chip.getAttribute('data-hsw-attr') || 'storage';
			var val = chip.getAttribute('data-hsw-val') || chip.getAttribute('data-hsw-storage') || '';

			vr2.querySelectorAll('[data-hsw-attr]').forEach(function (b) {
				if (b.getAttribute('data-hsw-attr') !== tax) { return; }
				b.classList.toggle('is-active', (b.getAttribute('data-hsw-val') || b.getAttribute('data-hsw-storage')) === val);
			});

			var st2 = state(pid2);
			st2.chips[tax] = val;
			if (attrMap(vr2).storage === tax) { st2.storage = val; }
			vr2.querySelectorAll('[data-hsw-selname]').forEach(function (el) {
				if (el.getAttribute('data-hsw-selname') === tax || el.getAttribute('data-hsw-selname-alias') === tax) {
					el.textContent = val;
				}
			});
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
			var next = setQty(pid3, st3.qty + delta);
			if (!scope2.classList.contains('is-not-in-cart')) {
				pushQty(pid3, next);
			}
			return;
		}

		/* --- bin (only shown at quantity 1) --- */
		var delBtn = t.closest('[data-hsw-del]');
		if (delBtn) {
			var scopeD = delBtn.closest('.hsw-p');
			var pidD   = scopeD ? (scopeD.getAttribute('data-hsw-product') || scopeD.getAttribute('data-hsw-variants')) : '';
			if (pidD && D.qtyAjax && scopeD && !scopeD.classList.contains('is-not-in-cart')) {
				pushQty(pidD, 0).then(function () {
					setRootInCart(pidD, false);
					setQty(pidD, 1);
				});
				return;
			}
			var href = delBtn.getAttribute('data-hsw-del') || '';
			if (href) { window.location.href = href; }
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

	/* First paint: mark every stepper that starts at 1. */
	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.hsw-psticky__stepper, .hsw-ppurch__stepper').forEach(function (box) {
			var val = box.querySelector('[data-hsw-qval]');
			var n = val ? parseInt(String(val.value).replace(/[^0-9]/g, ''), 10) : 1;
			box.classList.toggle('is-min', !(n > 1));
		});
	});

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
			/* Quantity 1: the minus becomes the bin (see the stylesheet). */
			r.querySelectorAll('.hsw-psticky__stepper, .hsw-ppurch__stepper').forEach(function (box) {
				box.classList.toggle('is-min', st.qty <= 1);
			});
			if (!r.classList.contains('is-not-in-cart')) {
				r.setAttribute('data-hsw-in-cart', String(st.qty));
			}
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
		qty = setQty(pid, qty);
		if (!scope.classList.contains('is-not-in-cart')) {
			pushQty(pid, qty);
		}
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

	/* ================= CART SYNC ================= */

	/*
	 * The stepper edits a real cart line. Before this, pressing + only changed
	 * the number on screen — the customer picked 3 and received 1.
	 */
	function applyFragments(fragments) {
		if (!fragments) { return; }
		Object.keys(fragments).forEach(function (key) {
			document.querySelectorAll(key).forEach(function (n) { n.outerHTML = fragments[key]; });
		});
		/* Keep the dimmed "empty cart" styling in sync with the new count. */
		document.querySelectorAll('.hsw-hd__cart').forEach(function (cart) {
			var badge = cart.querySelector('[data-hsw-cart-count]');
			var n = badge ? parseInt(badge.getAttribute('data-count') || '0', 10) : 0;
			cart.classList.toggle('is-empty', !n);
		});
		document.body.dispatchEvent(new Event('wc_fragment_refresh'));
	}

	function setRootInCart(pid, inCart, qty) {
		var n = parseInt(qty, 10);
		if (isNaN(n) || n < 0) { n = 1; }
		/* Mirror the real quantity onto the selected variation. */
		var st = state(pid);
		if (st.variation) { st.variation.cart_qty = inCart ? n : 0; }
		st.cartQty = inCart ? n : 0;
		st.cartVariationId = inCart && st.variation ? st.variation.id : 0;
		roots(pid).forEach(function (r) {
			r.classList.toggle('is-not-in-cart', !inCart);
			/* The real cart quantity, never a boolean: addToCart() reads it back. */
			r.setAttribute('data-hsw-in-cart', inCart ? String(n) : '0');
			/* A page cached before the first add may carry a disabled bin. */
			r.querySelectorAll('[data-hsw-del][disabled]').forEach(function (b) { b.removeAttribute('disabled'); });
		});
	}

	function pushQty(pid, qty) {
		if (!D.qtyAjax) { return Promise.resolve(null); }
		var st = state(pid);
		var body = new URLSearchParams();
		body.set('product_id', pid);
		if (st.variation && st.variation.id) { body.set('variation_id', st.variation.id); }
		body.set('quantity', qty);
		body.set('security', D.qtyNonce || '');

		return fetch(D.qtyAjax, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (r) { return r.json(); }).then(function (res) {
			if (!res || !res.success) { return null; }
			applyFragments(res.data && res.data.fragments);
			var qty = res.data && typeof res.data.quantity === 'number' ? res.data.quantity : 0;
			setRootInCart(pid, !!(res.data && res.data.in_cart), qty);
			if (qty > 0) { setQty(pid, qty); }
			return res.data;
		}).catch(function () { return null; });
	}

	/* ================= ADD TO CART ================= */

	function addToCart(btn, pid, st) {
		if (document.body.classList.contains('elementor-editor-active')) { flashAdded(btn); return; }
		if (!pid || btn.classList.contains('is-busy')) { return; }
		btn.classList.add('is-busy');

		var scope     = btn.closest('.hsw-p');
		var inCart    = !!(scope && !scope.classList.contains('is-not-in-cart'));
		var beforeQty = scope ? (parseInt(scope.getAttribute('data-hsw-in-cart') || '0', 10) || 0) : 0;
		/* Already in the cart? «خرید آنلاین» adds one more, not the whole stepper. */
		var addQty    = inCart ? 1 : (st.qty || 1);

		var body = new URLSearchParams();
		body.set('action', 'hsw_add_to_cart'); /* unused; wc-ajax endpoint ignores action */
		body.set('product_id', st.variation && st.variation.id ? st.variation.id : pid);
		if (st.variation && st.variation.id) { body.set('variation_id', st.variation.id); }
		body.set('quantity', addQty);

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

			/*
			 * The item is in the cart now: reveal the stepper (it is hidden
			 * before the first add) and seed it with the new line quantity.
			 * WooCommerce adds the requested quantity to an existing line, so
			 * the stepper, data-hsw-in-cart and the header badge stay identical.
			 */
			var newQty = inCart ? beforeQty + 1 : addQty;
			setRootInCart(pid, true, newQty);
			setQty(pid, newQty);

			applyFragments(res && res.fragments);
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
			var titleEl = document.querySelector('.hsw-ptitle__h1, .hsw-ptitle__h2');
			window.HSWFeedback.addedToCart({
				fragments: res && res.fragments,
				cartUrl: D.cartUrl,
				message: D.addedText,
				productName: titleEl ? titleEl.textContent.trim() : '',
				style: D.feedbackStyle || 'modal',
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

	/* ================= LIGHTBOX ================= */

	/*
	 * Full gallery lightbox: counter, arrows, keyboard, touch swipe, a thumbnail
	 * strip, and click-to-zoom (2.5x) with drag-to-pan for details.
	 */
	var zoomEl = null;
	var zoomList = [];
	var zoomIndex = 0;
	var zoomScale = 1;
	var panX = 0, panY = 0;
	/* True while the pointer actually dragged: the click that follows a pan
	   must not be treated as "toggle zoom" (the image jumped back to 1x). */
	var panMoved = false;

	function zoomBuild() {
		if (zoomEl) { return zoomEl; }
		zoomEl = document.createElement('div');
		zoomEl.className = 'hsw-zoom';
		var svgZoomIn = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"></circle><path d="M20 20l-3.6-3.6M11 8v6M8 11h6"></path></svg>';
		var svgClose = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"></path></svg>';
		var svgPrev = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"></path></svg>';
		var svgNext = '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"></path></svg>';

		zoomEl.innerHTML =
			'<div class="hsw-zoom__bar">' +
				'<span class="hsw-zoom__count" data-hsw-zoom-count></span>' +
				'<span class="hsw-zoom__tools">' +
					'<button type="button" class="hsw-zoom__btn" data-hsw-zoom-scale aria-label="بزرگ‌نمایی">' + svgZoomIn + '</button>' +
					'<button type="button" class="hsw-zoom__btn" data-hsw-zoom-close aria-label="بستن">' + svgClose + '</button>' +
				'</span>' +
			'</div>' +
			'<button type="button" class="hsw-zoom__nav hsw-zoom__nav--prev" data-hsw-zoom-prev aria-label="قبلی">' + svgPrev + '</button>' +
			'<button type="button" class="hsw-zoom__nav hsw-zoom__nav--next" data-hsw-zoom-next aria-label="بعدی">' + svgNext + '</button>' +
			'<div class="hsw-zoom__stage"><img class="hsw-zoom__img" alt=""></div>' +
			'<div class="hsw-zoom__thumbs" data-hsw-zoom-thumbs></div>';
		document.body.appendChild(zoomEl);

		zoomEl.addEventListener('click', function (e) {
			if (e.target.closest('[data-hsw-zoom-close]') || e.target === zoomEl) { closeZoom(); return; }
			/* Click / tap anywhere outside the picture closes the lightbox. */
			if (e.target.closest('.hsw-zoom__stage') && !e.target.closest('.hsw-zoom__img')) { closeZoom(); return; }
			if (e.target.closest('[data-hsw-zoom-prev]')) { zoomGo(-1); return; }
			if (e.target.closest('[data-hsw-zoom-next]')) { zoomGo(1); return; }
			if (e.target.closest('[data-hsw-zoom-scale]')) {
				zoomScale = zoomScale > 1 ? 1 : 2.5;
				panX = 0; panY = 0;
				zoomApply();
				return;
			}
			if (e.target.closest('.hsw-zoom__img')) {
				if (panMoved) { panMoved = false; return; }
				zoomScale = zoomScale > 1 ? 1 : 2.5;
				panX = 0; panY = 0;
				zoomApply();
				return;
			}
			var thumb = e.target.closest('[data-hsw-zoom-thumb]');
			if (thumb) { zoomShow(parseInt(thumb.getAttribute('data-hsw-zoom-thumb'), 10) || 0); }
		});
		return zoomEl;
	}

	function zoomApply() {
		var img = zoomEl.querySelector('.hsw-zoom__img');
		img.style.transform = 'translate(' + panX + 'px,' + panY + 'px) scale(' + zoomScale + ')';
		img.style.cursor = zoomScale > 1 ? 'grab' : 'zoom-in';
		zoomEl.classList.toggle('is-zoomed', zoomScale > 1);
	}

	function zoomShow(i) {
		if (!zoomList.length) { return; }
		zoomIndex = (i + zoomList.length) % zoomList.length;
		var img = zoomEl.querySelector('.hsw-zoom__img');
		img.src = zoomList[zoomIndex];
		zoomScale = 1; panX = 0; panY = 0;
		zoomApply();
		zoomEl.querySelector('[data-hsw-zoom-count]').textContent = fa(zoomIndex + 1) + ' / ' + fa(zoomList.length);
		zoomEl.querySelectorAll('[data-hsw-zoom-thumb]').forEach(function (b) {
			b.classList.toggle('is-active', parseInt(b.getAttribute('data-hsw-zoom-thumb'), 10) === zoomIndex);
		});
	}

	function zoomGo(dir) { zoomShow(zoomIndex + dir); }

	function openZoom(list, index) {
		zoomBuild();
		zoomList = Array.isArray(list) ? list : [list];
		var thumbs = zoomEl.querySelector('[data-hsw-zoom-thumbs]');
		thumbs.innerHTML = zoomList.map(function (src, i) {
			return '<button type="button" class="hsw-zoom__thumb" data-hsw-zoom-thumb="' + i + '"><img src="' + src + '" alt=""></button>';
		}).join('');
		thumbs.hidden = zoomList.length < 2;
		zoomShow(index || 0);
		zoomEl.classList.add('is-open');
		document.documentElement.classList.add('hsw-zoom-open');
	}

	function closeZoom() {
		if (!zoomEl) { return; }
		zoomEl.classList.remove('is-open');
		document.documentElement.classList.remove('hsw-zoom-open');
		zoomScale = 1; panX = 0; panY = 0;
	}

	document.addEventListener('keydown', function (e) {
		if (!zoomEl || !zoomEl.classList.contains('is-open')) { return; }
		if ('Escape' === e.key) { closeZoom(); }
		if ('ArrowLeft' === e.key) { zoomGo(-1); }
		if ('ArrowRight' === e.key) { zoomGo(1); }
	});

	/* Touch swipe + drag-to-pan inside the lightbox. */
	(function () {
		var sx = 0, sy = 0, dragging = false, moved = false;
		document.addEventListener('touchstart', function (e) {
			if (!zoomEl || !zoomEl.classList.contains('is-open') || e.touches.length !== 1) { return; }
			sx = e.touches[0].clientX; sy = e.touches[0].clientY;
			dragging = true; moved = false; panMoved = false;
		}, { passive: true });
		document.addEventListener('touchmove', function (e) {
			if (!dragging || !zoomEl || e.touches.length !== 1) { return; }
			var dx = e.touches[0].clientX - sx;
			var dy = e.touches[0].clientY - sy;
			if (Math.abs(dx) > 6 || Math.abs(dy) > 6) { moved = true; panMoved = true; }
			if (zoomScale > 1) {
				panX += e.touches[0].clientX - sx;
				panY += e.touches[0].clientY - sy;
				sx = e.touches[0].clientX; sy = e.touches[0].clientY;
				zoomApply();
			}
		}, { passive: true });
		document.addEventListener('touchend', function (e) {
			if (!dragging) { return; }
			dragging = false;
			if (zoomScale > 1 || !moved || !e.changedTouches.length) { return; }
			var dx = e.changedTouches[0].clientX - sx;
			if (Math.abs(dx) > 45) { zoomGo(dx > 0 ? -1 : 1); }
		}, { passive: true });

		/* Mouse drag to pan when zoomed. */
		var mx = 0, my = 0, mdrag = false, mdist = 0;
		document.addEventListener('mousedown', function (e) {
			if (!zoomEl || !zoomEl.classList.contains('is-open') || zoomScale <= 1) { return; }
			if (!e.target.closest('.hsw-zoom__img')) { return; }
			mdrag = true; mdist = 0; panMoved = false;
			mx = e.clientX; my = e.clientY;
			e.preventDefault();
		});
		document.addEventListener('mousemove', function (e) {
			if (!mdrag) { return; }
			var dx = e.clientX - mx;
			var dy = e.clientY - my;
			mdist += Math.abs(dx) + Math.abs(dy);
			if (mdist > 4) { panMoved = true; }
			panX += dx; panY += dy;
			mx = e.clientX; my = e.clientY;
			zoomApply();
		});
		document.addEventListener('mouseup', function () { mdrag = false; });
	})();

	/* ================= INIT ================= */
	function init() {
		/* seed variation payload roots */
		document.querySelectorAll('.hsw-p[data-hsw-variations]').forEach(function (root) {
			var pid = root.getAttribute('data-hsw-product') || root.getAttribute('data-hsw-variants');
			var st = state(pid);
			st.data = payload(root);

			var map = attrMap(root);
			/* preselect first active swatch / one active chip per group */
			var sw = root.querySelector('.hsw-pvar__swatch.is-active, .hsw-pvar__mswatch.is-active');
			if (sw) { st.color = sw.getAttribute('data-hsw-color') || ''; }

			st.chips = {};
			root.querySelectorAll('.hsw-pvar__chip.is-active, .hsw-pvar__mchip.is-active').forEach(function (b) {
				var tax = b.getAttribute('data-hsw-attr') || 'storage';
				var val = b.getAttribute('data-hsw-val') || b.getAttribute('data-hsw-storage') || '';
				if (!val) { return; }
				if (!st.chips[tax]) { st.chips[tax] = val; }
				if (map.storage === tax) { st.storage = val; }
			});

			applyVariant(pid, matchVariation(st.data, map, st));
		});

		/* Carousels (related products, gallery thumbs). init() runs on load and
		   again after every Elementor re-render, so this cannot be missed. */
		document.querySelectorAll('[data-hsw-carousel], .hsw-pgal__wrap').forEach(initCarousel);

		/* Product tabs: one strip, scrolls sideways on narrow screens. */
		document.querySelectorAll('[data-hsw-ptabs]').forEach(initTabs);
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

	/* ================= PRODUCT TABS ================= */

	/*
	 * The same tab strip on every screen. When it does not fit, it scrolls and a
	 * thin double-chevron appears at the reading edge; it fades away once the
	 * last tab is reached. Clicking a tab also centres it in the strip.
	 */
	function initTabs(root) {
		if (!root || root.getAttribute('data-hsw-tabs-ready') === '1') { return; }
		var nav = root.querySelector('[data-hsw-tabs-scroll]');
		if (!nav) { return; }
		root.setAttribute('data-hsw-tabs-ready', '1');

		var wrap = nav.closest('.hsw-ptabs__navwrap');
		var more = root.querySelector('[data-hsw-tabs-more]');

		function rtl() {
			return 'rtl' === (getComputedStyle(nav).direction || 'rtl');
		}

		function sync() {
			var max = nav.scrollWidth - nav.clientWidth;
			var scrollable = max > 4;
			if (wrap) { wrap.classList.toggle('is-scrollable', scrollable); }
			if (!more) { return; }
			var off = Math.abs(nav.scrollLeft);
			more.classList.toggle('is-off', !scrollable || off >= max - 4);
		}

		nav.addEventListener('scroll', sync, { passive: true });
		window.addEventListener('resize', sync);

		if (more) {
			more.addEventListener('click', function () {
				var step = Math.max(120, Math.round(nav.clientWidth * 0.6));
				nav.scrollBy({ left: rtl() ? -step : step, behavior: 'smooth' });
				setTimeout(sync, 340);
			});
		}

		root.querySelectorAll('[data-hsw-tab]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (btn.scrollIntoView) {
					btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
				}
				setTimeout(sync, 340);
			});
		});

		sync();
		setTimeout(sync, 200);
	}

	/* ================= CAROUSEL ================= */

	/*
	 * RTL-aware horizontal carousel: arrow buttons scroll by one card, the edge
	 * fade only shows while there is more content in that direction, and mouse
	 * drag works on desktop (touch scrolls natively).
	 */
	function initCarousel(root) {
		if (!root || root.getAttribute('data-hsw-carousel-ready') === '1') { return; }
		var track = root.querySelector('[data-hsw-track]') || root.querySelector('.hsw-pgal__thumbs');
		if (!track) { return; }
		root.setAttribute('data-hsw-carousel-ready', '1');

		var prev = root.querySelector('[data-hsw-nav="prev"]');
		var next = root.querySelector('[data-hsw-nav="next"]');
		var fadeNext = root.querySelector('[data-hsw-fade="next"]');
		var fadePrev = root.querySelector('[data-hsw-fade="prev"]');

		function rtl() {
			return 'rtl' === (getComputedStyle(track).direction || 'rtl');
		}

		/* In RTL scrollLeft goes negative; normalise to "distance from start". */
		function offset() {
			return rtl() ? Math.abs(track.scrollLeft) : track.scrollLeft;
		}

		function maxOffset() {
			return Math.max(0, track.scrollWidth - track.clientWidth);
		}

		function sync() {
			var atStart = offset() <= 2;
			var atEnd = offset() >= maxOffset() - 2;
			/* «next» = further along the reading direction (left in RTL). */
			if (fadeNext) { fadeNext.classList.toggle('is-on', !atEnd); }
			if (fadePrev) { fadePrev.classList.toggle('is-on', !atStart); }
			if (next) { next.disabled = atEnd; }
			if (prev) { prev.disabled = atStart; }
			/* The right fade only matters when we can go back. */
			if (fadePrev) { fadePrev.hidden = atStart; }
		}

		function step(dir) {
			var card = track.firstElementChild;
			var w = card ? card.getBoundingClientRect().width + 16 : track.clientWidth * 0.8;
			var delta = Math.max(w, 60) * (dir === 'next' ? 1 : -1);
			/* RTL: scrolling left means a negative scrollLeft. */
			var sign = rtl() ? -1 : 1;
			track.scrollBy({ left: delta * sign, behavior: 'smooth' });
			setTimeout(sync, 350);
		}

		if (next) { next.addEventListener('click', function () { step('next'); }); }
		if (prev) { prev.addEventListener('click', function () { step('prev'); }); }
		track.addEventListener('scroll', sync, { passive: true });
		window.addEventListener('resize', sync);
		sync();
		/* Flex widths are only final after layout/images settle. */
		setTimeout(sync, 150);
		track.querySelectorAll('img').forEach(function (img) {
			if (img.complete) { return; }
			img.addEventListener('load', sync, { once: true });
		});

		/* Mouse drag (desktop). Touch already scrolls natively. */
		var down = false, startX = 0, startScroll = 0, moved = 0;
		track.addEventListener('mousedown', function (e) {
			if (e.button !== 0) { return; }
			down = true; moved = 0;
			startX = e.pageX;
			startScroll = track.scrollLeft;
			track.style.cursor = 'grabbing';
		});
		window.addEventListener('mousemove', function (e) {
			if (!down) { return; }
			var dx = e.pageX - startX;
			if (Math.abs(dx) > 3) { moved = 1; }
			track.scrollLeft = startScroll - dx;
		});
		window.addEventListener('mouseup', function () {
			if (!down) { return; }
			down = false;
			track.style.cursor = '';
			if (moved) {
				/* Suppress the click that follows a drag. */
				track.addEventListener('click', function stop(ev) {
					ev.preventDefault();
					ev.stopPropagation();
					track.removeEventListener('click', stop, true);
				}, true);
			}
			sync();
		});
	}
})();
