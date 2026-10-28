/**
 * HamiShop header widgets — vanilla JS.
 *
 * Handles:
 * - store mobile search panel
 * - category mega menu
 * - minimal glass search panel
 * - mobile drawer + submenu accordions
 * - sticky shadow state
 * - WooCommerce cart-count/subtotal refresh from native fragments
 *
 * No jQuery is required. WooCommerce's own fragment events are used when
 * jQuery is present, with a fetch fallback for the HSW AJAX buttons.
 */
(function () {
	'use strict';

	var faNumber = (function () {
		try {
			return new Intl.NumberFormat('fa-IR');
		} catch (e) {
			return null;
		}
	})();

	function formatCount(value) {
		var number = parseInt(value, 10) || 0;
		if (faNumber) {
			return faNumber.format(number);
		}
		return String(number);
	}

	function all(root, selector) {
		return Array.prototype.slice.call(root.querySelectorAll(selector));
	}

	function panelForButton(root, button) {
		var id = button.getAttribute('aria-controls');
		if (id) {
			var found = document.getElementById(id);
			if (found && root.contains(found)) {
				return found;
			}
		}
		return root.querySelector('[data-hsw-hd-search-panel]');
	}

	function setExpanded(button, open) {
		if (button) {
			button.setAttribute('aria-expanded', open ? 'true' : 'false');
		}
	}

	function togglePanel(panel, open) {
		if (!panel) {
			return;
		}
		panel.classList.toggle('is-open', !!open);
		panel.setAttribute('aria-hidden', open ? 'false' : 'true');
	}

	function syncScrollLock() {
		var root = document.documentElement;
		var anyOpen = document.querySelector('.hsw-hd__drawer.is-open, .hsw-hd__cartdrawer.is-open');
		if (anyOpen && !root.classList.contains('hsw-hd-lock')) {
			/* Reserve the scrollbar width before hiding it so opening a drawer
			   does not reflow the page underneath. */
			root.style.setProperty('--hsw-hd-scrollbar', (window.innerWidth - root.clientWidth) + 'px');
		}
		root.classList.toggle('hsw-hd-lock', !!anyOpen);
	}

	function parseMiniCart(html) {
		if (!html) {
			return null;
		}
		var wrap = document.createElement('div');
		wrap.innerHTML = html;
		var mini = wrap.querySelector('.widget_shopping_cart_content') || wrap;
		var items = mini.querySelectorAll('.woocommerce-mini-cart-item');

		if (!items.length) {
			if (mini.querySelector('.woocommerce-mini-cart')) {
				return { count: 0, total: '' };
			}
			return null;
		}

		var count = 0;
		items.forEach(function (item) {
			var qty = item.querySelector('input.qty');
			var value = qty ? parseInt(qty.value, 10) : 0;
			if (!value) {
				/* WC prints the quantity as "۳ × نام محصول" (Persian digits). */
				var label = item.querySelector('.quantity');
				var m = label ? label.textContent.match(/[0-9\u06F0-\u06F9\u0660-\u0669]+/) : null;
				if (m) {
					value = parseInt(m[0].replace(/[\u06F0-\u06F9]/g, function (d) {
						return String(d.charCodeAt(0) - 0x06F0);
					}), 10) || 0;
				}
			}
			count += value > 0 ? value : 1;
		});

		var totalEl = mini.querySelector('.woocommerce-mini-cart__total .amount, .woocommerce-mini-cart__total');
		return {
			count: count,
			total: totalEl ? totalEl.textContent.replace(/\s+/g, ' ').trim() : ''
		};
	}

	/*
	 * Pins the header to the viewport and slides it away on scroll-down.
	 *
	 * `position: sticky` cannot do this job here: inside Elementor the header
	 * sits in a widget wrapper that is exactly as tall as the header, so there
	 * is no travel distance and it scrolls off with the page. We measure the
	 * header, copy that height into the placeholder that holds its old spot,
	 * and switch to `position: fixed`.
	 *
	 * Hiding is direction-based: any downward scroll past the threshold slides
	 * it out, any upward scroll brings it straight back. It never hides while
	 * one of our panels is open, and it reveals on focus so a keyboard user can
	 * never end up behind an invisible header.
	 */
	function initPinAndHide(root) {
		if (!root.classList.contains('hsw-hd--sticky')) {
			return;
		}

		var autohide = root.classList.contains('hsw-hd--autohide');
		var offset = parseInt(root.getAttribute('data-hsw-hideoffset'), 10);
		if (isNaN(offset)) {
			offset = 80;
		}

		var ph = root.previousElementSibling;
		if (!ph || !ph.classList.contains('hsw-hd__ph')) {
			ph = document.createElement('div');
			ph.className = 'hsw-hd__ph';
			ph.setAttribute('aria-hidden', 'true');
			root.parentNode.insertBefore(ph, root);
		}

		var pinned = false;
		var raf = 0;

		/*
		 * Match the pinned header to the box the placeholder occupies, so a
		 * boxed (non full-bleed) header keeps its width and side offsets. Full
		 * width headers need no inline styles at all.
		 */
		function align() {
			var r = ph.getBoundingClientRect();

			/* The widget is hidden at this breakpoint (a site can ship a store
			   header for desktop and a minimal pill for mobile). Leave the CSS
			   alone instead of collapsing the header to zero width. */
			if (!r.width || !r.height) {
				return;
			}

			var vw = document.documentElement.clientWidth;
			if (Math.abs(r.left) < 1 && Math.abs(r.width - vw) < 1) {
				root.style.left = '';
				root.style.right = '';
				root.style.width = '';
				return;
			}
			root.style.left = r.left + 'px';
			root.style.right = 'auto';
			root.style.width = r.width + 'px';
		}

		function pin() {
			if (pinned) {
				return;
			}
			/* Not rendered yet (hidden container) — a resize will retry. */
			if (!root.offsetWidth) {
				return;
			}
			ph.style.height = root.offsetHeight + 'px';
			root.classList.add('is-fixed');
			pinned = true;
			align();
		}

		function sync() {
			raf = 0;
			if (!pinned) {
				pin();
				return;
			}
			/* Let the header reflow at the new viewport width before measuring. */
			root.style.left = '';
			root.style.right = '';
			root.style.width = '';
			ph.style.height = root.offsetHeight + 'px';
			align();
		}

		pin();

		window.addEventListener('resize', function () {
			if (!raf) {
				raf = window.requestAnimationFrame(sync);
			}
		}, { passive: true });

		/* Web fonts change the header height after first paint. */
		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(sync);
		}

		if (!autohide) {
			return;
		}

		var hidden = false;
		var last = window.scrollY;
		var ticking = false;

		/*
		 * `instant` matters: a transform turns the header into the containing
		 * block for the `position: fixed` drawer nested inside it, so a panel
		 * that opens mid-transition would size itself to the header instead of
		 * the viewport. Every reveal that a panel can trigger is instant.
		 */
		function apply(value, instant) {
			if (value === hidden) {
				return;
			}
			hidden = value;
			if (!instant) {
				root.classList.toggle('is-hidden', value);
				return;
			}
			root.style.transition = 'none';
			root.classList.toggle('is-hidden', value);
			void root.offsetWidth;
			root.style.transition = '';
		}

		function busy() {
			/* Never slide away while the user is inside one of our own panels. */
			return !!(root.querySelector('.is-open') || root.querySelector('[aria-expanded="true"]'));
		}

		function update() {
			ticking = false;
			var y = window.scrollY;
			var delta = y - last;

			if (y <= offset || busy()) {
				apply(false, true);
			} else if (delta > 8) {
				apply(true, false);
			} else if (delta < -6) {
				apply(false, false);
			}
			last = y;
		}

		window.addEventListener('scroll', function () {
			if (!ticking) {
				ticking = true;
				window.requestAnimationFrame(update);
			}
		}, { passive: true });

		/* A hidden header must never swallow focus or a click target. */
		root.addEventListener('focusin', function () {
			apply(false, true);
		});
		root.addEventListener('pointerdown', function () {
			apply(false, true);
		});
	}

	function initHeader(root) {
		if (!root || root.getAttribute('data-hsw-init') === '1') {
			return;
		}
		root.setAttribute('data-hsw-init', '1');
		root.classList.add('hsw-hd__js');

		var searchButtons = all(root, '[data-hsw-hd-search-toggle]');
		var searchPanels = all(root, '[data-hsw-hd-search-panel]');
		var mega = root.querySelector('[data-hsw-hd-mega]');
		var megaButton = root.querySelector('[data-hsw-hd-mega-toggle]');
		var drawer = root.querySelector('[data-hsw-hd-drawer]');
		var drawerOpeners = all(root, '[data-hsw-hd-drawer-open]');
		var drawerClosers = all(root, '[data-hsw-hd-drawer-close]');
		var lastFocus = null;

		function closeSearch(except) {
			searchPanels.forEach(function (panel) {
				if (panel !== except) {
					togglePanel(panel, false);
				}
			});
			searchButtons.forEach(function (button) {
				var panel = panelForButton(root, button);
				if (panel !== except) {
					setExpanded(button, false);
				}
			});
		}

		searchButtons.forEach(function (button) {
			button.addEventListener('click', function (event) {
				event.preventDefault();
				var panel = panelForButton(root, button);
				if (!panel) {
					return;
				}
				var willOpen = !panel.classList.contains('is-open');
				closeSearch(panel);
				togglePanel(panel, willOpen);
				setExpanded(button, willOpen);
				if (willOpen) {
					var input = panel.querySelector('input[type="search"]');
					if (input) {
						window.setTimeout(function () {
							input.focus();
						}, 40);
					}
				}
			});
		});

		document.addEventListener('click', function (event) {
			searchPanels.forEach(function (panel) {
				if (!panel.classList.contains('is-open')) {
					return;
				}
				var onToggle = searchButtons.some(function (button) {
					return button.contains(event.target);
				});
				if (!panel.contains(event.target) && !onToggle) {
					togglePanel(panel, false);
					searchButtons.forEach(function (button) {
						if (panelForButton(root, button) === panel) {
							setExpanded(button, false);
						}
					});
				}
			});
		});

		if (mega && megaButton) {
			var megaPanel = mega.querySelector('[data-hsw-hd-mega-panel]');
			var megaCascade = mega.querySelector('[data-hsw-hd-mega-cascade]');
			var megaDepth = parseInt(megaPanel ? megaPanel.getAttribute('data-hsw-hd-mega-depth') : '', 10) || 3;
			var megaLimit = parseInt(megaPanel ? megaPanel.getAttribute('data-hsw-hd-mega-limit') : '', 10) || 4;
			var megaProducts = !!megaPanel && megaPanel.getAttribute('data-hsw-hd-mega-products') !== '0';
			var megaEndpoint = root.getAttribute('data-hsw-rest') || '';
			var megaError = megaPanel ? (megaPanel.getAttribute('data-hsw-hd-mega-error') || '') : '';
			var megaCache = {};
			var hoverNodeTimer = null;

			var cascadeColumns = function () {
				return megaCascade ? all(megaCascade, '[data-hsw-hd-mega-col]') : [];
			};

			var itemForNode = function (node) {
				return node ? node.closest('[data-hsw-hd-mega-item]') : null;
			};

			var setNodeOpen = function (node, open) {
				if (!node) {
					return;
				}
				var item = itemForNode(node);
				if (item) {
					item.classList.toggle('is-open', open);
				}
				setExpanded(node, open);
			};

			var resetCascade = function () {
				all(mega, '[data-hsw-hd-mega-node]').forEach(function (node) {
					setNodeOpen(node, false);
				});
				var cols = cascadeColumns();
				for (var i = cols.length - 1; i > 0; i--) {
					if (cols[i].parentNode && cols[i].parentNode.contains(cols[i])) {
						cols[i].parentNode.removeChild(cols[i]);
					}
				}
			};

			var removeAfter = function (col) {
				var cols = cascadeColumns();
				var index = cols.indexOf(col);
				if (index < 0) {
					return;
				}
				for (var i = cols.length - 1; i > index; i--) {
					var child = cols[i];
					if (child._hswParentNode) {
						setNodeOpen(child._hswParentNode, false);
					}
					if (child.parentNode && child.parentNode.contains(child)) {
						child.parentNode.removeChild(child);
					}
				}
			};

			var collapseSiblings = function (node, col) {
				all(col, '.hsw-hd__cascadeitem.is-open').forEach(function (item) {
					var button = item.querySelector('[data-hsw-hd-mega-node]');
					if (button && button !== node) {
						setNodeOpen(button, false);
					}
				});
			};

			var focusColumn = function (col) {
				/* Prefer a category/product row over the column's "view all" link. */
				var first = col.querySelector('[data-hsw-hd-mega-node], a.hsw-hd__cascadenode')
					|| col.querySelector('.hsw-hd__cascadeproduct, .hsw-hd__cascadeend')
					|| col.querySelector('.hsw-hd__cascadeall');
				if (!first) {
					return;
				}
				try {
					first.focus({ preventScroll: true });
				} catch (e) {
					first.focus();
				}
			};

			var insertColumn = function (html, node, col, focusFirst) {
				var holder = document.createElement('div');
				holder.innerHTML = html || '';
				var newCol = holder.firstElementChild;
				var loading = col.nextElementSibling;

				if (!newCol || !newCol.classList || !newCol.classList.contains('hsw-hd__cascadecol')) {
					if (loading && loading.getAttribute('data-hsw-hd-mega-loading') === '1' && loading.parentNode) {
						loading.parentNode.removeChild(loading);
					}
					setNodeOpen(node, false);
					return;
				}

				newCol._hswParentNode = node;
				if (loading && loading.getAttribute('data-hsw-hd-mega-loading') === '1' && loading.parentNode) {
					loading.parentNode.replaceChild(newCol, loading);
				} else {
					col.parentNode.insertBefore(newCol, col.nextSibling);
				}

				newCol.classList.add('is-entering');
				window.requestAnimationFrame(function () {
					newCol.classList.remove('is-entering');
				});

				if (focusFirst) {
					focusColumn(newCol);
				}
			};

			var loadingColumn = function (node, col) {
				var loading = document.createElement('div');
				loading.className = 'hsw-hd__cascadecol hsw-hd__cascadecol--loading';
				loading.setAttribute('data-hsw-hd-mega-col', '');
				loading.setAttribute('data-hsw-hd-mega-loading', '1');
				loading.setAttribute('aria-hidden', 'true');
				loading._hswParentNode = node;
				loading.innerHTML = '<div class="hsw-hd__cascadehead"><span class="hsw-hd__cascadetitle">&hellip;</span></div>'
					+ '<div class="hsw-hd__cascadeloading" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>';
				col.parentNode.insertBefore(loading, col.nextSibling);
				return loading;
			};

			var requestColumn = function (node, col, focusFirst) {
				var catId = node.getAttribute('data-hsw-hd-mega-cat');
				var level = parseInt(node.getAttribute('data-hsw-hd-mega-level'), 10) || 1;

				if (!catId || !megaEndpoint) {
					setNodeOpen(node, false);
					return;
				}

				var key = catId + '|' + level + '|' + megaDepth + '|' + megaLimit + '|' + (megaProducts ? '1' : '0');
				if (Object.prototype.hasOwnProperty.call(megaCache, key)) {
					insertColumn(megaCache[key], node, col, focusFirst);
					return;
				}

				var loading = loadingColumn(node, col);
				loading.setAttribute('data-hsw-hd-mega-key', key);

				fetch(megaEndpoint + (megaEndpoint.indexOf('?') === -1 ? '?' : '&') +
					'cat=' + encodeURIComponent(catId) +
					'&level=' + encodeURIComponent(level) +
					'&depth=' + encodeURIComponent(megaDepth) +
					'&limit=' + encodeURIComponent(megaLimit) +
					'&products=' + (megaProducts ? '1' : '0'), {
					credentials: 'same-origin',
					headers: { Accept: 'application/json' },
				})
					.then(function (response) {
						if (!response.ok) {
							throw new Error('cascade request failed');
						}
						return response.json();
					})
					.then(function (data) {
						if (!loading.parentNode) {
							return;
						}
						var html = data && data.html ? data.html : '';
						if (html) {
							megaCache[key] = html;
						}
						insertColumn(html, node, col, focusFirst);
					})
					.catch(function () {
						if (!loading.parentNode) {
							return;
						}
						setNodeOpen(node, false);
						loading.classList.remove('hsw-hd__cascadecol--loading');
						loading.classList.add('hsw-hd__cascadecol--error');
						loading.removeAttribute('aria-hidden');
						loading.innerHTML = '<div class="hsw-hd__cascadehead"><span class="hsw-hd__cascadetitle">&hellip;</span></div>'
							+ '<p class="hsw-hd__cascadeempty"></p>';
						var message = loading.querySelector('.hsw-hd__cascadeempty');
						if (message) {
							message.textContent = megaError || '—';
						}
					});
			};

			var openNode = function (node, focusFirst, toggle) {
				var col = node.closest('[data-hsw-hd-mega-col]');
				if (!col) {
					return;
				}

				var isOpen = node.getAttribute('aria-expanded') === 'true';
				if (isOpen) {
					if (toggle) {
						removeAfter(col);
						setNodeOpen(node, false);
					}
					return;
				}

				removeAfter(col);
				collapseSiblings(node, col);
				setNodeOpen(node, true);
				requestColumn(node, col, focusFirst);
			};

			mega.addEventListener('click', function (event) {
				var node = event.target.closest ? event.target.closest('[data-hsw-hd-mega-node]') : null;
				if (!node || !mega.contains(node)) {
					return;
				}
				event.preventDefault();
				openNode(node, event.detail === 0, true);
			});

			var setMegaOpen = function (open) {
				if (!open) {
					clearTimeout(hoverNodeTimer);
					clearTimeout(hoverTimer);
					if (megaPanel && megaPanel.contains(document.activeElement)) {
						try {
							megaButton.focus({ preventScroll: true });
						} catch (e) {
							megaButton.focus();
						}
					}
					resetCascade();
				}
				mega.classList.toggle('is-open', open);
				setExpanded(megaButton, open);
				if (megaPanel) {
					megaPanel.setAttribute('aria-hidden', open ? 'false' : 'true');
				}
			};

			megaButton.addEventListener('click', function (event) {
				event.preventDefault();
				setMegaOpen(!mega.classList.contains('is-open'));
			});

			/*
			 * Hover mode (widget setting "با هاور"). A short grace period
			 * bridges the gap between the trigger row and the panel, so the
			 * menu does not snap shut while the pointer travels down into it.
			 * Deeper columns open only after the pointer settles on a row;
			 * keyboard users open them with Enter/Space.
			 */
			if (mega.classList.contains('hsw-hd__mega--hover')) {
				var hoverTimer = null;
				var openOnHover = function () {
					clearTimeout(hoverTimer);
					setMegaOpen(true);
				};
				var closeOnHover = function () {
					clearTimeout(hoverTimer);
					hoverTimer = setTimeout(function () {
						setMegaOpen(false);
					}, 180);
				};

				[megaButton, megaPanel].forEach(function (node) {
					if (!node) {
						return;
					}
					node.addEventListener('mouseenter', openOnHover);
					node.addEventListener('mouseleave', closeOnHover);
				});

				mega.addEventListener('mouseover', function (event) {
					var node = event.target.closest ? event.target.closest('[data-hsw-hd-mega-node]') : null;
					if (!node || !mega.contains(node)) {
						clearTimeout(hoverNodeTimer);
						return;
					}
					if (event.relatedTarget && node.contains(event.relatedTarget)) {
						return;
					}
					clearTimeout(hoverNodeTimer);
					hoverNodeTimer = setTimeout(function () {
						openNode(node, false, false);
					}, 70);
				});

				mega.addEventListener('mouseleave', function () {
					clearTimeout(hoverNodeTimer);
				});

				/*
				 * Defer this check: removing a focused column moves focus to the
				 * document body for one tick, and closing immediately would race
				 * with the keydown handler that is about to focus the parent row.
				 */
				mega.addEventListener('focusout', function () {
					window.setTimeout(function () {
						if (mega.classList.contains('is-open') && !mega.contains(document.activeElement)) {
							setMegaOpen(false);
						}
					}, 0);
				});
			}

			document.addEventListener('click', function (event) {
				if (mega.classList.contains('is-open') && !mega.contains(event.target)) {
					setMegaOpen(false);
				}
			});

			document.addEventListener('keydown', function (event) {
				if ('Escape' !== event.key || !mega.classList.contains('is-open')) {
					return;
				}

				var cols = cascadeColumns();
				if (cols.length > 1) {
					var last = cols[cols.length - 1];
					var parentNode = last._hswParentNode;
					if (last.parentNode && last.parentNode.contains(last)) {
						last.parentNode.removeChild(last);
					}
					if (parentNode) {
						setNodeOpen(parentNode, false);
						try {
							parentNode.focus({ preventScroll: true });
						} catch (e) {
							parentNode.focus();
						}
					}
					return;
				}

				var hadFocus = mega.contains(document.activeElement);
				setMegaOpen(false);
				if (hadFocus) {
					megaButton.focus();
				}
			});
		}

		function openDrawer() {
			if (!drawer) {
				return;
			}
			closeSearch(null);
			if (mega && megaButton && mega.classList.contains('is-open') && typeof setMegaOpen === 'function') {
				setMegaOpen(false);
			}
			lastFocus = document.activeElement;
			drawer.classList.add('is-open');
			drawer.setAttribute('aria-hidden', 'false');
			drawerOpeners.forEach(function (button) {
				setExpanded(button, true);
			});
			syncScrollLock();

			var closeButton = drawer.querySelector('[data-hsw-hd-drawer-close]');
			if (closeButton) {
				window.setTimeout(function () {
					closeButton.focus();
				}, 40);
			}
		}

		function closeDrawer(restoreFocus) {
			if (!drawer) {
				return;
			}
			drawer.classList.remove('is-open');
			drawer.setAttribute('aria-hidden', 'true');
			drawerOpeners.forEach(function (button) {
				setExpanded(button, false);
			});
			syncScrollLock();
			if (restoreFocus && lastFocus && typeof lastFocus.focus === 'function') {
				lastFocus.focus();
			}
		}

		drawerOpeners.forEach(function (button) {
			button.addEventListener('click', function (event) {
				event.preventDefault();
				openDrawer();
			});
		});

		drawerClosers.forEach(function (button) {
			button.addEventListener('click', function (event) {
				event.preventDefault();
				closeDrawer(true);
			});
		});

		if (drawer) {
			/* Accordion toggles are injected so the parent link still works. */
			all(drawer, '.menu-item-has-children').forEach(function (item) {
				var link = null;
				var submenu = null;
				Array.prototype.forEach.call(item.children, function (child) {
					if (!link && child.tagName === 'A') {
						link = child;
					}
					if (child.classList && child.classList.contains('sub-menu')) {
						submenu = child;
					}
				});
				if (!link || !submenu || item.querySelector(':scope > .hsw-hd__subtoggle')) {
					return;
				}

				var toggle = document.createElement('button');
				toggle.type = 'button';
				toggle.className = 'hsw-hd__subtoggle';
				toggle.setAttribute('aria-label', 'باز کردن زیرمنو');
				toggle.setAttribute('aria-expanded', 'false');
				link.insertAdjacentElement('afterend', toggle);

				toggle.addEventListener('click', function (event) {
					event.preventDefault();
					var open = !item.classList.contains('is-open');
					item.classList.toggle('is-open', open);
					toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
				});
			});

			drawer.addEventListener('keydown', function (event) {
				if (event.key !== 'Tab' || !drawer.classList.contains('is-open')) {
					return;
				}
				var focusable = all(
					drawer,
					'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
				).filter(function (el) {
					return el.offsetParent !== null;
				});
				if (!focusable.length) {
					return;
				}
				var first = focusable[0];
				var last = focusable[focusable.length - 1];
				if (event.shiftKey && document.activeElement === first) {
					event.preventDefault();
					last.focus();
				} else if (!event.shiftKey && document.activeElement === last) {
					event.preventDefault();
					first.focus();
				}
			});
		}

		document.addEventListener('keydown', function (event) {
			if (event.key !== 'Escape') {
				return;
			}
			if (drawer && drawer.classList.contains('is-open')) {
				closeDrawer(true);
				return;
			}
			if (mega && mega.classList.contains('is-open')) {
				/* The cascade handler above owns Escape for the new panel. */
				if (mega.querySelector('[data-hsw-hd-mega-cascade]')) {
					return;
				}
				mega.classList.remove('is-open');
				setExpanded(megaButton, false);
				return;
			}
			closeSearch(null);
		});

		window.addEventListener('resize', function () {
			if (window.innerWidth > 1024 && drawer && drawer.classList.contains('is-open')) {
				closeDrawer(false);
			}
		}, { passive: true });

		/* Sticky shadow state */
		if (root.classList.contains('hsw-hd--sticky')) {
			var onScroll = function () {
				root.classList.toggle('is-stuck', window.scrollY > 6);
			};
			window.addEventListener('scroll', onScroll, { passive: true });
			onScroll();
		}

		initPinAndHide(root);

		/* Cart count / subtotal */
		var countEls = all(root, '[data-hsw-cart-count]');
		var totalEls = all(root, '[data-hsw-cart-total]');
		var cartLinks = all(root, '.hsw-hd__cart');
		var cartRefreshSeq = 0;

		function applyCart(count, total) {
			/*
			 * WooCommerce fragments replace the badge node itself, so the
			 * arrays captured at boot can point at detached elements after
			 * the first AJAX add. Re-querying keeps every later update live.
			 */
			countEls = all(root, '[data-hsw-cart-count]');
			totalEls = all(root, '[data-hsw-cart-total]');
			cartLinks = all(root, '.hsw-hd__cart');
			countEls.forEach(function (el) {
				el.textContent = formatCount(count);
				/* Keep the plain integer in sync so later reads stay truthful. */
				el.setAttribute('data-count', String(parseInt(count, 10) || 0));
			});
			cartLinks.forEach(function (el) {
				el.classList.toggle('is-empty', !count);
			});
			if (totalEls.length) {
				var value = count ? total : 'خالی';
				if (value) {
					totalEls.forEach(function (el) {
						el.textContent = value;
					});
				}
			}
		}

		function updateFromFragmentHtml(html) {
			/*
			 * Our own fragment already contains the exact count WooCommerce
			 * reports (total quantity). Parsing the mini-cart instead gave the
			 * number of *lines*, because WC's mini-cart prints "3 × product"
			 * as text, not an input.qty — that is why the badge flickered from
			 * 3 to 2 on every reload.
			 */
			var wrap = document.createElement('div');
			wrap.innerHTML = html;
			var badge = wrap.querySelector('[data-hsw-cart-count]');
			if (badge) {
				var raw = parseInt(badge.getAttribute('data-count') || '0', 10) || 0;
				var totalEl = wrap.querySelector('[data-hsw-cart-total]');
				applyCart(raw, totalEl ? totalEl.textContent.replace(/\s+/g, ' ').trim() : '');
				return;
			}

			var data = parseMiniCart(html);
			if (data) {
				applyCart(data.count, data.total);
			}
		}

		function applyDrawerFragments(fragments) {
			var drawer = root.querySelector('[data-hsw-cart-drawer]');
			if (!drawer || !fragments) {
				return;
			}

			/*
			 * WooCommerce fragments replace the selected node itself, not its
			 * children. Both selectors therefore carry the complete wrapper.
			 */
			['[data-hsw-cart-drawer-body]', '[data-hsw-cart-drawer-count]'].forEach(function (selector) {
				var markup = fragments[selector];
				if (!markup) {
					return;
				}
				drawer.querySelectorAll(selector).forEach(function (node) {
					node.outerHTML = markup;
				});
			});
		}

		function refreshCart() {
			var url = root.getAttribute('data-hsw-fragments');
			if (!url || !window.fetch) {
				return;
			}
			var seq = ++cartRefreshSeq;
			window.fetch(url, { credentials: 'same-origin' })
				.then(function (response) {
					return response.json();
				})
				.then(function (json) {
					/* A newer refresh or a quantity action has superseded this
					   response; applying it would overwrite the newer cart. */
					if (seq !== cartRefreshSeq) {
						return;
					}
					if (!json || !json.fragments) {
						return;
					}
					var html = Object.keys(json.fragments).map(function (key) {
						return json.fragments[key];
					}).join('');
					applyDrawerFragments(json.fragments);
					updateFromFragmentHtml(html);
				})
				.catch(function () {});
		}

		document.addEventListener('hsw_added_to_cart', refreshCart);

		/*
		 * Page-cache safety: the badge is printed server-side, so a cached
		 * copy shows the cart of whoever warmed the cache. Re-sync on load:
		 * WooCommerce sets `woocommerce_items_in_cart` only for visitors with
		 * a cart, so everyone else is reset to empty without a request.
		 */
		if (/(?:^|;\s*)woocommerce_items_in_cart=/.test(document.cookie)) {
			refreshCart();
		} else if (countEls.length && parseInt(countEls[0].getAttribute('data-count') || '0', 10) > 0) {
			applyCart(0, '');
		}

		if (window.jQuery) {
			var $body = window.jQuery(document.body);
			$body.on('added_to_cart', function (event, fragments) {
				if (fragments) {
					if (typeof fragments === 'object') {
						applyDrawerFragments(fragments);
					}
					var html = typeof fragments === 'string' ? fragments : Object.keys(fragments).map(function (key) {
						return fragments[key];
					}).join('');
					updateFromFragmentHtml(html);
				} else {
					refreshCart();
				}
			});
			$body.on('wc_fragments_refreshed wc_fragments_loaded', function () {
				var mini = document.querySelector('.widget_shopping_cart_content');
				if (mini && mini.querySelector('.woocommerce-mini-cart')) {
					var items = mini.querySelectorAll('.woocommerce-mini-cart-item');
					if (items.length) {
						updateFromFragmentHtml(mini.outerHTML);
					}
				}
			});
		}

		/* ---------- cart sidebar ---------- */
		var cartDrawer = root.querySelector('[data-hsw-cart-drawer]');
		if (cartDrawer) {
			var cartOpeners = all(root, '[data-hsw-cart-open]');
			var cartClosers = all(cartDrawer, '[data-hsw-cart-close]');
			var cartLastFocus = null;
			var cartBusy = false;

			function setCartExpanded(open) {
				cartOpeners.forEach(function (button) {
					button.setAttribute('aria-expanded', open ? 'true' : 'false');
				});
			}

			function cartFocusable() {
				return all(
					cartDrawer,
					'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
				).filter(function (el) {
					return el.offsetParent !== null;
				});
			}

			function openCart() {
				if (drawer && drawer.classList.contains('is-open')) {
					closeDrawer(false);
				}
				cartLastFocus = document.activeElement;
				cartDrawer.classList.add('is-open');
				cartDrawer.setAttribute('aria-hidden', 'false');
				setCartExpanded(true);
				syncScrollLock();
				/* Pull a fresh body so a page-cached drawer never shows another
				   visitor's cart. Cheap when the session already matches. */
				refreshCart();
				var closeButton = cartDrawer.querySelector('.hsw-hd__cartclose');
				if (closeButton) {
					window.setTimeout(function () {
						closeButton.focus();
					}, 40);
				}
			}

			function closeCart(restoreFocus) {
				cartDrawer.classList.remove('is-open');
				cartDrawer.setAttribute('aria-hidden', 'true');
				setCartExpanded(false);
				syncScrollLock();
				if (restoreFocus) {
					var target = cartLastFocus;
					if (!target || target.offsetParent === null) {
						target = root.querySelector('.hsw-hd__cart');
					}
					if (target && typeof target.focus === 'function') {
						target.focus();
					}
				}
			}

			function setCartBusy(busy) {
				cartBusy = !!busy;
				cartDrawer.classList.toggle('is-busy', !!busy);
			}

			function showCartError(message) {
				if (window.HSWFeedback && typeof window.HSWFeedback.toast === 'function') {
					window.HSWFeedback.toast(message, { tone: 'error' });
				}
			}

			function postCartQty(productId, variationId, quantity, focusTarget) {
				var data = window.hswHeaderData || {};
				if (cartBusy || !data.qtyAjax || !data.qtyNonce) {
					return;
				}

				cartRefreshSeq++;
				setCartBusy(true);
				var payload = new URLSearchParams();
				payload.set('product_id', String(productId));
				payload.set('variation_id', String(variationId || 0));
				payload.set('quantity', String(quantity));
				payload.set('security', data.qtyNonce);

				window.fetch(data.qtyAjax, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: payload.toString()
				})
					.then(function (response) {
						return response.json();
					})
					.then(function (json) {
						setCartBusy(false);
						if (!json || !json.success) {
							showCartError((json && json.data && json.data.message) || data.errorText || '');
							refreshCart();
							return;
						}
						var fragments = json.data && json.data.fragments ? json.data.fragments : {};
						applyDrawerFragments(fragments);
						var html = Object.keys(fragments).map(function (key) {
							return fragments[key];
						}).join('');
						updateFromFragmentHtml(html);
						/*
						 * The product page has its own add-to-cart state. Tell it
						 * that a drawer quantity/removal changed this line so its
						 * CTA and stepper do not stay out of sync.
						 */
						document.dispatchEvent(new CustomEvent('hsw_cart_drawer_updated', {
							detail: {
								productId: productId,
								variationId: variationId,
								qty: json.data && typeof json.data.quantity !== 'undefined' ? json.data.quantity : quantity
							}
						}));
						/* Fragment replacement destroys the pressed button; put
						   focus back on the equivalent control (or the close
						   button after a removal) so keyboard users keep context. */
						window.setTimeout(function () {
							var target = null;
							if (focusTarget && 'close' !== focusTarget) {
								var pid = parseInt(productId, 10) || 0;
								var vid = parseInt(variationId, 10) || 0;
								var line = cartDrawer.querySelector('[data-hsw-cart-line][data-product-id="' + pid + '"][data-variation-id="' + vid + '"]');
								if (line) {
									target = line.querySelector('-1' === focusTarget ? '[data-hsw-cart-qty="-1"]' : '[data-hsw-cart-qty="1"]');
								}
								if (target && target.disabled) {
									target = null;
								}
							}
							if (!target) {
								target = cartDrawer.querySelector('.hsw-hd__cartclose');
							}
							if (target) {
								target.focus();
							}
						}, 40);
					})
					.catch(function () {
						setCartBusy(false);
						showCartError(data.errorText || '');
					});
			}

			cartOpeners.forEach(function (button) {
				button.addEventListener('click', function (event) {
					if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
						return;
					}
					event.preventDefault();
					if (cartDrawer.classList.contains('is-open')) {
						closeCart(true);
					} else {
						openCart();
					}
				});
			});

			cartClosers.forEach(function (button) {
				button.addEventListener('click', function (event) {
					event.preventDefault();
					closeCart(true);
				});
			});

			cartDrawer.addEventListener('click', function (event) {
				var qtyButton = event.target.closest ? event.target.closest('[data-hsw-cart-qty]') : null;
				if (qtyButton) {
					event.preventDefault();
					var line = qtyButton.closest('[data-hsw-cart-line]');
					var delta = parseInt(qtyButton.getAttribute('data-hsw-cart-qty'), 10) || 0;
					var current = line ? parseInt(line.getAttribute('data-qty') || '1', 10) || 1 : 1;
					var next = Math.max(0, current + delta);
					if (next === current) {
						return;
					}
					postCartQty(
						qtyButton.getAttribute('data-product-id'),
						qtyButton.getAttribute('data-variation-id'),
						next,
						delta > 0 ? '1' : '-1'
					);
					return;
				}

				var removeButton = event.target.closest ? event.target.closest('[data-hsw-cart-remove]') : null;
				if (removeButton) {
					event.preventDefault();
					postCartQty(
						removeButton.getAttribute('data-product-id'),
						removeButton.getAttribute('data-variation-id'),
						0,
						'close'
					);
				}
			});

			cartDrawer.addEventListener('keydown', function (event) {
				if (event.key !== 'Tab' || !cartDrawer.classList.contains('is-open')) {
					return;
				}
				var focusable = cartFocusable();
				if (!focusable.length) {
					return;
				}
				var first = focusable[0];
				var last = focusable[focusable.length - 1];
				if (event.shiftKey && document.activeElement === first) {
					event.preventDefault();
					last.focus();
				} else if (!event.shiftKey && document.activeElement === last) {
					event.preventDefault();
					first.focus();
				}
			});

			document.addEventListener('keydown', function (event) {
				if (event.key === 'Escape' && cartDrawer.classList.contains('is-open')) {
					closeCart(true);
				}
			});
		}
	}

	function boot() {
		all(document, '[data-hsw-header]').forEach(initHeader);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}

	/* Elementor editor / live preview re-renders. */
	if (window.jQuery) {
		window.jQuery(window).on('elementor/frontend/init', function () {
			if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
				return;
			}
			['hsw-header-store', 'hsw-header-minimal'].forEach(function (widget) {
				window.elementorFrontend.hooks.addAction(
					'frontend/element_ready/' + widget + '.default',
					function ($scope) {
						all($scope[0], '[data-hsw-header]').forEach(initHeader);
					}
				);
			});
		});
	}
})();
