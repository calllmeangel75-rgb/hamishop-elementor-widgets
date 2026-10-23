/**
 * HamiShop — unified add-to-cart feedback.
 *
 * One system, two presentations: a toast for messages that need no action, and
 * a modal for "here is your cart". The rule that matters is that they can never
 * appear together — `addedToCart()` picks exactly one, and opening either one
 * dismisses the other.
 *
 * Deliberately dependency-free (no jQuery, no Elementor frontend): the archive
 * and product widgets already ship vanilla JS and Lighthouse 100 is a hard
 * requirement.
 *
 * Accessibility:
 *  - toast   → role="status" aria-live="polite", never takes focus, never locks
 *              the page, auto-dismisses, ESC and the close button both work;
 *  - modal   → role="dialog" aria-modal="true", labelled by its title, focus
 *              moves in on open and returns to the trigger on close, ESC and a
 *              backdrop click close it, and the page scroll is locked only while
 *              it is open.
 */
(function () {
	'use strict';

	var LOCK_CLASS = 'hsw-scroll-lock';
	var toastEl = null;
	var modalEl = null;
	var modalHost = null;
	var lastFocus = null;
	var toastTimer = null;

	function el(tag, cls, attrs) {
		var node = document.createElement(tag);
		if (cls) { node.className = cls; }
		if (attrs) {
			Object.keys(attrs).forEach(function (key) {
				node.setAttribute(key, attrs[key]);
			});
		}
		return node;
	}

	/* ---------- scroll lock ---------- */

	function lockScroll(on) {
		var body = document.body;
		if (on) {
			if (body.classList.contains(LOCK_CLASS)) { return; }
			/* Reserve the scrollbar width so the page does not jump. */
			var gap = window.innerWidth - document.documentElement.clientWidth;
			if (gap > 0) {
				body.style.paddingInlineEnd = gap + 'px';
			}
			body.classList.add(LOCK_CLASS);
		} else {
			body.classList.remove(LOCK_CLASS);
			body.style.paddingInlineEnd = '';
		}
	}

	/* ---------- toast ---------- */

	function closeToast() {
		if (!toastEl) { return; }
		var node = toastEl;
		toastEl = null;
		clearTimeout(toastTimer);
		node.classList.remove('is-show');
		setTimeout(function () {
			if (node.parentNode) { node.parentNode.removeChild(node); }
		}, 220);
	}

	function toast(message, opts) {
		opts = opts || {};
		if (!message) { return; }

		/* A toast never sits under a modal. */
		if (modalEl) { return; }

		closeToast();

		/*
		 * `thin` is the wishlist line: same strip as the add-to-cart notice but
		 * without the cart link or the timer bar, because there is nowhere to
		 * send the customer.
		 */
		toastEl = el('div', 'hsw-toast hsw-toast--center' + (opts.thin ? ' hsw-toast--line hsw-toast--thin' : ''), {
			role: 'status',
			'aria-live': 'polite',
			'aria-atomic': 'true'
		});

		var iconName = opts.tone === 'error' ? 'error' : 'check_circle';
		var icon = document.createElement('span');
		icon.className = 'hsw-toast__ic';
		icon.setAttribute('aria-hidden', 'true');
		icon.innerHTML = window.hswIcon ? window.hswIcon(iconName, 'hsw-toast__ic__glyph') : '';

		var text = el('span', 'hsw-toast__msg');
		text.textContent = message;

		var close = el('button', 'hsw-toast__close', { type: 'button', 'aria-label': opts.closeLabel || 'بستن' });
		close.innerHTML = window.hswIcon ? window.hswIcon('close') : '';
		close.addEventListener('click', closeToast);

		toastEl.appendChild(icon);
		toastEl.appendChild(text);
		toastEl.appendChild(close);

		if (opts.tone === 'error') {
			toastEl.classList.add('is-error');
		}

		document.body.appendChild(toastEl);
		/* Next frame so the transition runs. */
		requestAnimationFrame(function () {
			if (toastEl) { toastEl.classList.add('is-show'); }
		});

		toastTimer = setTimeout(closeToast, opts.duration || 4000);
	}

	/* ---------- modal ---------- */

	function closeModal() {
		if (!modalEl) { return; }
		var node = modalEl;
		modalEl = null;
		lockScroll(false);
		node.classList.remove('is-show');
		setTimeout(function () {
			if (node.parentNode) { node.parentNode.removeChild(node); }
		}, 240);
		if (lastFocus && lastFocus.focus) {
			lastFocus.focus();
			lastFocus = null;
		}
	}

	function modal(config) {
		config = config || {};

		/* Only one at a time; re-opening replaces the content. */
		if (modalEl) { closeModal(); }
		closeToast();

		lastFocus = document.activeElement;

		modalHost = el('div', 'hsw-modal', {
			role: 'dialog',
			'aria-modal': 'true',
			'aria-label': config.title || ''
		});

		var backdrop = el('div', 'hsw-modal__backdrop');
		backdrop.addEventListener('click', closeModal);

		var panel = el('div', 'hsw-modal__panel');

		var head = el('div', 'hsw-modal__head');
		var title = el('h2', 'hsw-modal__title');
		title.textContent = config.title || '';
		var close = el('button', 'hsw-modal__close', {
			type: 'button',
			'aria-label': config.closeLabel || 'بستن'
		});
		close.innerHTML = window.hswIcon ? window.hswIcon('close') : '';
		close.addEventListener('click', closeModal);

		head.appendChild(title);
		head.appendChild(close);

		var body = el('div', 'hsw-modal__body');
		if (config.html) {
			body.innerHTML = config.html;
		} else if (config.node) {
			body.appendChild(config.node);
		}

		panel.appendChild(head);
		panel.appendChild(body);

		if (config.actions) {
			var foot = el('div', 'hsw-modal__foot');
			config.actions.forEach(function (action) {
				var node = el('a', 'hsw-modal__btn ' + (action.primary ? 'is-primary' : 'is-ghost'), {
					href: action.url || '#'
				});
				node.textContent = action.label || '';
				foot.appendChild(node);
			});
			panel.appendChild(foot);
		}

		modalHost.appendChild(backdrop);
		modalHost.appendChild(panel);
		document.body.appendChild(modalHost);

		modalEl = modalHost;
		lockScroll(true);

		requestAnimationFrame(function () {
			if (modalEl) { modalEl.classList.add('is-show'); }
		});

		/* Focus goes into the dialog, not to the page behind it. */
		close.focus({ preventScroll: true });

		return modalHost;
	}

	/* ---------- keyboard ---------- */

	document.addEventListener('keydown', function (event) {
		if ('Escape' !== event.key) { return; }
		if (modalEl) {
			event.preventDefault();
			closeModal();
			return;
		}
		if (toastEl) {
			closeToast();
		}
	});

	/*
	 * Click outside the toast dismisses it — the requirement asks for it, and it
	 * costs one listener. The modal has its own backdrop, so this only ever
	 * fires for the toast.
	 */
	document.addEventListener('pointerdown', function (event) {
		if (!toastEl || modalEl) { return; }
		if (toastEl.contains(event.target)) { return; }
		closeToast();
	}, true);

	/* ---------- the single entry point ---------- */

	/**
	 * Extract the cart subtotal from WooCommerce's mini-cart fragment.
	 *
	 * The fragment is real markup, so parsing it is more reliable than guessing
	 * at a JSON field WooCommerce does not send. If the theme has no mini-cart
	 * the total is simply omitted.
	 */
	function fragmentTotal(fragments) {
		if (!fragments || !window.DOMParser) { return ''; }

		var html = Object.keys(fragments).map(function (key) {
			return fragments[key];
		}).join('');

		if (!html) { return ''; }

		try {
			var doc = new DOMParser().parseFromString(html, 'text/html');
			var total = doc.querySelector('.woocommerce-mini-cart__total .amount, .widget_shopping_cart_content .total .amount');
			return total ? total.textContent.trim() : '';
		} catch (err) {
			return '';
		}
	}

	function fragmentBody(fragments) {
		if (!fragments) { return ''; }
		var key = 'div.widget_shopping_cart_content';
		if (fragments[key]) { return fragments[key]; }
		return Object.keys(fragments).map(function (k) { return fragments[k]; }).join('');
	}

	/**
	 * Report a successful add.
	 *
	 * Modal when we have cart markup (the useful case), toast otherwise. Never
	 * both, and never two modals for two fast clicks.
	 */
	/* ---------- one-line notice ---------- */

	/*
	 * The alternative to the modal. One strip, the product name, a link to the
	 * cart and a green bar that empties over five seconds — the bar doubles as
	 * the close timer, so the customer can see how long it stays.
	 */
	function lineNotice(payload, cfg) {
		if (modalEl) { return; }

		closeToast();

		var name = payload.productName || '';
		var message = payload.message || cfg.added || 'به سبد خرید اضافه شد';
		var line = name ? (name + ' — ' + message) : message;

		toastEl = el('div', 'hsw-toast hsw-toast--line', {
			role: 'status',
			'aria-live': 'polite',
			'aria-atomic': 'true'
		});

		var icon = document.createElement('span');
		icon.className = 'hsw-toast__ic';
		icon.setAttribute('aria-hidden', 'true');
		icon.innerHTML = window.hswIcon ? window.hswIcon('check_circle') : '';

		var text = el('span', 'hsw-toast__msg');
		text.textContent = line;

		var link = el('a', 'hsw-toast__link', { href: payload.cartUrl || '/cart/' });
		link.textContent = cfg.viewCart || 'مشاهده سبد خرید';

		var close = el('button', 'hsw-toast__close', { type: 'button', 'aria-label': cfg.close || 'بستن' });
		close.innerHTML = window.hswIcon ? window.hswIcon('close') : '';
		close.addEventListener('click', closeToast);

		var bar = el('span', 'hsw-toast__bar', { 'aria-hidden': 'true' });

		toastEl.appendChild(icon);
		toastEl.appendChild(text);
		toastEl.appendChild(link);
		toastEl.appendChild(close);
		toastEl.appendChild(bar);

		document.body.appendChild(toastEl);
		requestAnimationFrame(function () {
			if (toastEl) { toastEl.classList.add('is-show'); }
		});

		/* Five seconds, matching the bar's animation. */
		toastTimer = setTimeout(closeToast, 5000);
	}

	/* ---------- cart summary ---------- */

	/*
	 * The dialog used to inject the theme's whole mini-cart. Woodmart (and a
	 * few other themes) ship their own classes and buttons, so inside a 420px
	 * dialog the thumbnail filled the width and the theme's own «مشاهده سبد
	 * خرید / تسویه حساب» stacked on top of ours. We now read the fragment and
	 * print a compact list of our own — image, name, quantity, price — so the
	 * dialog looks the same on every theme.
	 */
	function cartItems(html) {
		if (!html) { return []; }
		var doc;
		try {
			doc = new DOMParser().parseFromString(html, 'text/html');
		} catch (err) {
			return [];
		}
		var nodes = doc.querySelectorAll('.woocommerce-mini-cart-item, li[data-key], .wd-cart-item, .mini-cart-item');
		var out = [];
		nodes.forEach(function (node) {
			var img = node.querySelector('img');
			/*
			 * The first link in a mini-cart row is usually the «×» remove
			 * button, so pick the link that actually carries text.
			 */
			var links = Array.prototype.slice.call(node.querySelectorAll('a[href]')).filter(function (a) {
				return !a.classList.contains('remove') && !a.classList.contains('wd-cart-remove');
			});
			links.sort(function (a, b) {
				return (b.textContent || '').trim().length - (a.textContent || '').trim().length;
			});
			var link = links[0] || null;
			var title = link ? (link.textContent || '').trim() : '';
			if (!title) {
				var nameEl = node.querySelector('.product-name, [class*="product-name"], [class*="item-name"]');
				title = nameEl ? (nameEl.textContent || '').trim() : '';
			}
			if (!title) {
				var clone = node.cloneNode(true);
				clone.querySelectorAll('.amount, .quantity, .remove, del, ins').forEach(function (n) {
					if (n.parentNode) { n.parentNode.removeChild(n); }
				});
				title = (clone.textContent || '').replace(/[×✕]/g, ' ').replace(/\s+/g, ' ').trim();
			}
			if (!title) { return; }

			var qtyEl = node.querySelector('.quantity, [class*="quantity"], [class*="qty"]');
			var qty = qtyEl ? (qtyEl.textContent || '').replace(/\s+/g, ' ').trim() : '';
			var priceEls = node.querySelectorAll('.amount, [class*="price"]');
			var price = priceEls.length ? (priceEls[priceEls.length - 1].textContent || '').replace(/\s+/g, ' ').trim() : '';

			out.push({
				img: img ? (img.getAttribute('src') || '') : '',
				title: title,
				qty: qty,
				price: price,
				url: link ? link.getAttribute('href') : ''
			});
		});
		return out;
	}

	function cartSummary(items, total, fallbackName) {
		var wrap = el('div', 'hsw-cartlist');
		var list = el('ul', 'hsw-cartlist__items');

		if (!items.length && fallbackName) {
			items = [{ img: '', title: fallbackName, qty: '', price: '', url: '' }];
		}

		items.forEach(function (item) {
			var row = el('li', 'hsw-cartlist__item');
			if (item.img) {
				var im = el('img', 'hsw-cartlist__img');
				im.src = item.img;
				im.alt = '';
				im.loading = 'lazy';
				row.appendChild(im);
			}
			var body = el('div', 'hsw-cartlist__body');
			var name = el('a', 'hsw-cartlist__name', { href: item.url || '#' });
			name.textContent = item.title;
			body.appendChild(name);

			var meta = [item.qty, item.price].filter(Boolean).join(' — ');
			if (meta) {
				var line = el('span', 'hsw-cartlist__meta');
				line.textContent = meta;
				body.appendChild(line);
			}

			row.appendChild(body);
			list.appendChild(row);
		});

		wrap.appendChild(list);

		if (total) {
			var foot = el('div', 'hsw-cartlist__total');
			var label = el('span', 'hsw-cartlist__totallabel');
			label.textContent = 'جمع سبد خرید';
			var value = el('span', 'hsw-cartlist__totalvalue');
			value.textContent = total;
			foot.appendChild(label);
			foot.appendChild(value);
			wrap.appendChild(foot);
		}

		return wrap.outerHTML;
	}

	function addedToCart(payload) {
		payload = payload || {};

		var html = fragmentBody(payload.fragments);
		var cfg = payload.labels || {};
		var style = payload.style || window.hswFeedbackStyle || 'modal';

		if ('toast' === style) {
			lineNotice(payload, cfg);
			return;
		}

		if (html) {
			modal({
				title: cfg.modalTitle || 'به سبد خرید اضافه شد',
				html: cartSummary(cartItems(html), fragmentTotal(html), payload.productName || ''),
				closeLabel: cfg.close || 'بستن',
				actions: [
					{ label: cfg.viewCart || 'مشاهده سبد خرید', url: payload.cartUrl || '/cart/', primary: true },
					{ label: cfg.continue || 'ادامه خرید', url: '#' }
				]
			});

			/* "ادامه خرید" just closes the dialog. */
			var cont = modalEl && modalEl.querySelectorAll('.hsw-modal__btn');
			if (cont && cont.length > 1) {
				cont[cont.length - 1].addEventListener('click', function (event) {
					event.preventDefault();
					closeModal();
				});
			}
			return;
		}

		toast(payload.message || cfg.added || 'به سبد خرید اضافه شد');
	}

	window.HSWFeedback = {
		toast: toast,
		modal: modal,
		closeModal: closeModal,
		closeToast: closeToast,
		addedToCart: addedToCart,
		fragmentTotal: fragmentTotal
	};
})();
