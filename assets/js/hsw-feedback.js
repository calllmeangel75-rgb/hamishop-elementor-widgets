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

		toastEl = el('div', 'hsw-toast hsw-toast--center', {
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
	function addedToCart(payload) {
		payload = payload || {};

		var html = fragmentBody(payload.fragments);
		var cfg = payload.labels || {};

		if (html) {
			modal({
				title: cfg.modalTitle || 'به سبد خرید اضافه شد',
				html: html,
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
