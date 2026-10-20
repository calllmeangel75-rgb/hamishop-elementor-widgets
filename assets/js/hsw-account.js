/**
 * HamiShop account UI - no jQuery, no framework, no external requests.
 *
 * Progressive enhancement only: login/register and ticket forms work without
 * this file. It adds auth tabs, password visibility, membership tabs, the
 * notification bell behaviour and the local-storage wishlist page.
 */
(function () {
	'use strict';

	var WISH_KEY = 'hsw_wishlist';

	function data() {
		return window.hswAccountData || {};
	}

	function faNum(value) {
		var text = String(value == null ? '' : value);
		if (!data().faDigits) {
			return text;
		}
		return text.replace(/\d/g, function (digit) {
			return '۰۱۲۳۴۵۶۷۸۹'.charAt(Number(digit));
		});
	}

	function wishList() {
		try {
			var value = JSON.parse(localStorage.getItem(WISH_KEY) || '[]');
			return Array.isArray(value) ? value.map(String) : [];
		} catch (error) {
			return [];
		}
	}

	function saveWish(list) {
		try {
			localStorage.setItem(WISH_KEY, JSON.stringify(list));
		} catch (error) {
			/* storage may be disabled */
		}
	}

	function updateCounts() {
		var count = wishList().length;
		document.querySelectorAll('[data-hsw-wish-count]').forEach(function (badge) {
			badge.textContent = count ? faNum(count) : '';
			badge.hidden = !count;
		});
	}

	function setHidden(element, hidden) {
		if (!element) {
			return;
		}
		element.hidden = hidden;
		element.style.display = hidden ? 'none' : '';
	}

	function wishlistEmpty(page) {
		var grid = page.querySelector('[data-hsw-wish-grid]');
		var empty = page.querySelector('[data-hsw-wish-empty]');
		var clear = page.querySelector('[data-hsw-wish-clear]');
		if (grid) {
			grid.innerHTML = '';
		}
		setHidden(empty, false);
		setHidden(clear, true);
	}

	function renderWishlist(page) {
		if (!page || page.getAttribute('data-hsw-ready') === '1') {
			return;
		}
		page.setAttribute('data-hsw-ready', '1');

		var grid = page.querySelector('[data-hsw-wish-grid]');
		var empty = page.querySelector('[data-hsw-wish-empty]');
		var clear = page.querySelector('[data-hsw-wish-clear]');
		var list = wishList();

		updateCounts();

		if (!list.length) {
			wishlistEmpty(page);
			return;
		}

		var rest = data().rest || '/wp-json/hsw/v1';
		var url = rest.replace(/\/$/, '') + '/account/wishlist?ids=' + encodeURIComponent(list.join(','));

		setHidden(clear, false);

		fetch(url, {
			credentials: 'same-origin',
			headers: data().nonce ? { 'X-WP-Nonce': data().nonce } : {}
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}
				return response.json();
			})
			.then(function (payload) {
				if (!grid) {
					return;
				}
				var valid = payload && Array.isArray(payload.ids) ? payload.ids.map(String) : list;
				if (valid.length !== list.length) {
					saveWish(valid);
				}
				updateCounts();
				if (!payload || !payload.html || !valid.length) {
					wishlistEmpty(page);
					return;
				}
				grid.innerHTML = payload.html;
				setHidden(empty, true);
				setHidden(clear, false);
			})
			.catch(function () {
				if (!grid) {
					return;
				}
				grid.innerHTML = '<div class="hsw-ac__empty hsw-ac__empty--inline"><span class="hsw-ac__empty-ic">!</span><h3>' + (data().errorText || 'خطا در بارگذاری علاقه‌مندی‌ها') + '</h3><p>' + (data().retryText || 'اتصال اینترنت را بررسی کنید و صفحه را دوباره باز کنید.') + '</p></div>';
				setHidden(clear, false);
			});
	}

	function init(root) {
		var scope = root || document;
		scope.querySelectorAll('[data-hsw-wishlist]').forEach(renderWishlist);
		updateCounts();
	}

	function switchAuth(tab) {
		var auth = tab.closest('[data-hsw-auth]');
		if (!auth) {
			return;
		}
		var name = tab.getAttribute('data-hsw-auth-tab');
		auth.setAttribute('data-active', name);
		auth.querySelectorAll('[data-hsw-auth-tab]').forEach(function (button) {
			var active = button === tab;
			button.classList.toggle('is-active', active);
			button.setAttribute('aria-selected', active ? 'true' : 'false');
			button.setAttribute('tabindex', active ? '0' : '-1');
		});
		auth.querySelectorAll('[data-hsw-auth-panel]').forEach(function (panel) {
			var active = panel.getAttribute('data-hsw-auth-panel') === name;
			panel.classList.toggle('is-active', active);
			panel.hidden = !active;
		});
	}

	function switchMember(tab) {
		var member = tab.closest('[data-hsw-member]');
		if (!member) {
			return;
		}
		var name = tab.getAttribute('data-hsw-member-tab');
		member.querySelectorAll('[data-hsw-member-tab]').forEach(function (button) {
			var active = button === tab;
			button.classList.toggle('is-active', active);
			button.setAttribute('aria-selected', active ? 'true' : 'false');
		});
		member.querySelectorAll('[data-hsw-member-panel]').forEach(function (panel) {
			var active = panel.getAttribute('data-hsw-member-panel') === name;
			panel.hidden = !active;
		});
	}

	function togglePassword(button) {
		var wrap = button.closest('.hsw-ac-input-wrap');
		var input = wrap ? wrap.querySelector('input') : null;
		if (!input) {
			return;
		}
		var show = input.type === 'password';
		input.type = show ? 'text' : 'password';
		button.classList.toggle('is-visible', show);
		button.setAttribute('aria-pressed', show ? 'true' : 'false');
		button.setAttribute('aria-label', show ? (data().hidePassword || 'پنهان کردن رمز عبور') : (data().showPassword || 'نمایش رمز عبور'));
	}

	function removeWish(id, button) {
		var list = wishList().filter(function (item) {
			return String(item) !== String(id);
		});
		saveWish(list);
		var card = button ? button.closest('.hsw-ac__wish') : null;
		if (card) {
			card.remove();
		}
		updateCounts();
		var page = document.querySelector('[data-hsw-wishlist]');
		if (page && !list.length) {
			wishlistEmpty(page);
		}
	}

	document.addEventListener('click', function (event) {
		var authTab = event.target.closest('[data-hsw-auth-tab]');
		if (authTab) {
			event.preventDefault();
			switchAuth(authTab);
			return;
		}

		var memberTab = event.target.closest('[data-hsw-member-tab]');
		if (memberTab) {
			event.preventDefault();
			switchMember(memberTab);
			return;
		}

		var passwordToggle = event.target.closest('[data-hsw-pass-toggle]');
		if (passwordToggle) {
			event.preventDefault();
			togglePassword(passwordToggle);
			return;
		}

		var remove = event.target.closest('[data-hsw-wish-remove]');
		if (remove) {
			event.preventDefault();
			removeWish(remove.getAttribute('data-hsw-wish-remove'), remove);
			return;
		}

		var clear = event.target.closest('[data-hsw-wish-clear]');
		if (clear) {
			event.preventDefault();
			if (window.confirm(data().clearConfirm || 'همهٔ محصولات از علاقه‌مندی‌ها حذف شوند؟')) {
				saveWish([]);
				updateCounts();
				var page = clear.closest('[data-hsw-wishlist]');
				if (page) {
					wishlistEmpty(page);
				}
			}
			return;
		}

		document.querySelectorAll('.hsw-ac__bell[open]').forEach(function (bell) {
			if (!bell.contains(event.target)) {
				bell.removeAttribute('open');
			}
		});
	});

	document.addEventListener('keydown', function (event) {
		if ('Escape' === event.key) {
			document.querySelectorAll('.hsw-ac__bell[open]').forEach(function (bell) {
				bell.removeAttribute('open');
				var summary = bell.querySelector('summary');
				if (summary) {
					summary.focus();
				}
			});
		}
	});

	window.addEventListener('storage', function (event) {
		if (event.key && event.key !== WISH_KEY) {
			return;
		}
		updateCounts();
		var page = document.querySelector('[data-hsw-wishlist]');
		if (page) {
			page.removeAttribute('data-hsw-ready');
			renderWishlist(page);
		}
	});

	function boot() {
		init(document);
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function () {
				init(document);
			});
		}
	}

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
